<?php
// ============================================================
//  Sosyal Medya Paylaşım Sistemi
//
//  Kullanıcılar kendi sosyal medya hesaplarını bağlar, paylaşım
//  oluşturur (hemen ya da ileri bir tarihe planlı) ve cron
//  (cron_paylasim.php) zamanı gelen paylaşımları otomatik yayınlar.
//
//  Şu an desteklenen platform: Facebook Sayfası (Graph API).
//  Yeni platform eklemek için: sosyal_hesaplar.platform'a değer ekle,
//  paylasimYayinla() içinde o platformun istemcisini çağır.
//
//  Güvenlik:
//   - Sayfa erişim token'ları DB'de AES-256-GCM ile şifreli saklanır
//     (anahtar: uygulamaAnahtari() — .env APP_KEY, yoksa otomatik üretilen).
//   - Graph çağrılarına appsecret_proof eklenir.
//   - Facebook uygulama bilgileri admin panelinden (Sistem Ayarları) girilir.
//   - Saat dilimi: tüm zamanlar PHP tarafında panel ayarı app_timezone ile üretilir
//     (MySQL NOW() kullanılmaz — sunucu/DB saat dilimi farkı sorun olmasın).
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sistem.php';

if (!@date_default_timezone_set(ayar('app_timezone'))) date_default_timezone_set('Europe/Istanbul');

const SOSYAL_MAX_DENEME        = 3;    // geçici hatada toplam deneme sayısı
const SOSYAL_DENEME_ARALIGI_DK = 5;    // n. denemeden sonra n × 5 dk bekle
const SOSYAL_KILIT_ZAMAN_ASIMI = 15;   // 'gonderiliyor'da takılı kalan kayıt (dk)
const SOSYAL_MESAJ_MAX         = 63206; // Facebook gönderi metni üst sınırı
const FB_IZINLER               = ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'];

// ------------------------------------------------------------
//  Şema (sosyal.php, facebook_callback.php ve cron her açılışta çağırır)
// ------------------------------------------------------------
function sosyalSemaKur(): void {
    DB::get()->exec("CREATE TABLE IF NOT EXISTS `sosyal_hesaplar` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `kullanici_id`   INT UNSIGNED NOT NULL,
        `platform`       VARCHAR(20)  NOT NULL,
        `dis_id`         VARCHAR(80)  NOT NULL,
        `ad`             VARCHAR(200),
        `resim_url`      VARCHAR(500),
        `access_token`   TEXT,
        `durum`          ENUM('aktif','pasif','yeniden_baglan') DEFAULT 'aktif',
        `son_hata`       VARCHAR(500),
        `baglanma`       DATETIME,
        `guncelleme`     DATETIME,
        UNIQUE KEY `uk_hesap` (`kullanici_id`, `platform`, `dis_id`),
        INDEX `idx_kullanici` (`kullanici_id`),
        FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    DB::get()->exec("CREATE TABLE IF NOT EXISTS `sosyal_paylasimlar` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `kullanici_id`    INT UNSIGNED NOT NULL,
        `hesap_id`        INT UNSIGNED NOT NULL,
        `mesaj`           TEXT,
        `link`            VARCHAR(1000),
        `gorsel_url`      VARCHAR(1000),
        `planlanan_zaman` DATETIME NOT NULL,
        `durum`           ENUM('bekliyor','gonderiliyor','gonderildi','hata','iptal') DEFAULT 'bekliyor',
        `deneme_sayisi`   INT DEFAULT 0,
        `dis_post_id`     VARCHAR(120),
        `hata_mesaji`     VARCHAR(1000),
        `kilit_zamani`    DATETIME,
        `gonderim_zamani` DATETIME,
        `olusturma`       DATETIME,
        INDEX `idx_kuyruk` (`durum`, `planlanan_zaman`),
        INDEX `idx_kullanici` (`kullanici_id`),
        FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`hesap_id`) REFERENCES `sosyal_hesaplar`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function simdi(): string {
    return date('Y-m-d H:i:s');
}

// ------------------------------------------------------------
//  Token şifreleme (AES-256-GCM)
// ------------------------------------------------------------
function sosyalAnahtarVarMi(): bool {
    return uygulamaAnahtari() !== '';
}

// tokenSifrele() / tokenCoz(): sistem.php (gizli panel ayarları da kullanır)

// ------------------------------------------------------------
//  Saf yardımcılar (tests/sosyal_test.php ile test edilir)
// ------------------------------------------------------------

/** Kullanıcı girdisini doğrular; hata mesajları dizisi döner (boş = geçerli). */
function paylasimDogrula(array $g): array {
    $hatalar = [];
    $mesaj = trim((string)($g['mesaj'] ?? ''));
    $link  = trim((string)($g['link'] ?? ''));
    $gorsel= trim((string)($g['gorsel_url'] ?? ''));

    if ($mesaj === '' && $link === '' && $gorsel === '') {
        $hatalar[] = 'Mesaj, link veya görselden en az biri girilmelidir.';
    }
    if (mb_strlen($mesaj) > SOSYAL_MESAJ_MAX) {
        $hatalar[] = 'Mesaj en fazla ' . SOSYAL_MESAJ_MAX . ' karakter olabilir.';
    }
    foreach (['link' => $link, 'gorsel_url' => $gorsel] as $alan => $url) {
        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            $hatalar[] = ($alan === 'link' ? 'Link' : 'Görsel adresi') . ' http:// veya https:// ile başlamalıdır.';
        } elseif ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $hatalar[] = ($alan === 'link' ? 'Link' : 'Görsel adresi') . ' geçerli bir URL değil.';
        }
    }
    if (empty($g['hesap_idler'])) {
        $hatalar[] = 'En az bir hesap seçilmelidir.';
    }
    return $hatalar;
}

/**
 * Formdaki zamanı ("2026-09-24T15:30" / boş) DB biçimine çevirir.
 * Boş ya da geçmiş zaman → hemen ($simdi). Geçersiz → null.
 */
function planlananZamanCoz(string $girdi, string $simdi): ?string {
    $girdi = trim($girdi);
    if ($girdi === '') return $simdi;
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $girdi)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $girdi)
       ?: DateTime::createFromFormat('Y-m-d H:i', $girdi);
    if (!$dt) return null;
    $z = $dt->format('Y-m-d H:i:s');
    return $z < $simdi ? $simdi : $z;
}

/** Ürün şablonu: {urun_adi} {fiyat} {marka} {barkod} {link} yer tutucuları. */
function paylasimSablonuUygula(string $sablon, array $urun): string {
    $fiyat = isset($urun['sale_price']) && $urun['sale_price'] !== ''
        ? number_format((float)$urun['sale_price'], 2, ',', '.') . ' ₺'
        : '';
    return strtr($sablon, [
        '{urun_adi}' => (string)($urun['title'] ?? ''),
        '{fiyat}'    => $fiyat,
        '{marka}'    => (string)($urun['brand'] ?? ''),
        '{barkod}'   => (string)($urun['barcode'] ?? ''),
        '{link}'     => (string)($urun['product_url'] ?? ''),
    ]);
}

/**
 * Paylaşım kaydından Facebook isteğini üretir: [kenar, parametreler].
 * Görsel varsa /photos (fotoğraf gönderisi; link eki desteklenmediği için
 * link açıklamanın sonuna eklenir), yoksa /feed (metin + link önizlemesi).
 */
function facebookIstegiOlustur(array $p): array {
    $mesaj  = trim((string)($p['mesaj'] ?? ''));
    $link   = trim((string)($p['link'] ?? ''));
    $gorsel = trim((string)($p['gorsel_url'] ?? ''));

    if ($gorsel !== '') {
        $aciklama = $mesaj;
        if ($link !== '' && !str_contains($mesaj, $link)) {
            $aciklama = trim($aciklama . "\n\n" . $link);
        }
        $params = ['url' => $gorsel];
        if ($aciklama !== '') $params['caption'] = $aciklama;
        return ['photos', $params];
    }
    $params = [];
    if ($mesaj !== '') $params['message'] = $mesaj;
    if ($link  !== '') $params['link']    = $link;
    return ['feed', $params];
}

/**
 * Başarısız bir denemeden sonra ne yapılacağı.
 * $deneme: bu deneme dahil yapılan deneme sayısı.
 * Dönüş: ['durum' => 'bekliyor'|'hata', 'gecikme_dk' => int]
 */
function yenidenDenemeKarari(int $deneme, bool $kaliciHata): array {
    if ($kaliciHata || $deneme >= SOSYAL_MAX_DENEME) {
        return ['durum' => 'hata', 'gecikme_dk' => 0];
    }
    return ['durum' => 'bekliyor', 'gecikme_dk' => SOSYAL_DENEME_ARALIGI_DK * $deneme];
}

// ------------------------------------------------------------
//  Facebook Graph API istemcisi
// ------------------------------------------------------------
class FacebookHata extends RuntimeException {
    public function __construct(string $mesaj, public readonly int $fbKod = 0,
                                public readonly int $fbAltKod = 0, public readonly int $httpKod = 0) {
        parent::__construct($mesaj);
    }

    /** Token geçersiz/iptal/süresi dolmuş → hesap yeniden bağlanmalı. */
    public function tokenHatasi(): bool {
        return in_array($this->fbKod, [102, 190], true);
    }

    /** Tekrar denemekle düzelmeyecek hatalar (izin, geçersiz parametre, token). */
    public function kalici(): bool {
        if ($this->tokenHatasi()) return true;
        if (in_array($this->fbKod, [10, 100, 200, 368], true)) return true;
        if ($this->fbKod >= 200 && $this->fbKod <= 299) return true; // izin hataları
        return false;
    }
}

class FacebookGraph {
    /** @var callable(string $metot, string $url, array $params): array{0:int,1:string} */
    private $http;

    public function __construct(
        private string $appId,
        private string $appSecret,
        private string $surum = 'v23.0',
        ?callable $http = null
    ) {
        $this->http = $http ?? [$this, 'curlIstek'];
    }

    public static function ayarlardan(): self {
        if (!self::yapilandirildi()) {
            throw new RuntimeException('Facebook uygulaması yapılandırılmamış (Admin → Sistem Ayarları: Facebook App ID / Secret).');
        }
        return new self(ayar('fb_app_id'), ayar('fb_app_secret'), ayar('fb_graph_version') ?: 'v23.0');
    }

    public static function yapilandirildi(): bool {
        return ayar('fb_app_id') !== '' && ayar('fb_app_secret') !== '';
    }

    public function girisUrl(string $yonlendirme, string $state): string {
        return "https://www.facebook.com/{$this->surum}/dialog/oauth?" . http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $yonlendirme,
            'state'         => $state,
            'scope'         => implode(',', FB_IZINLER),
            'response_type' => 'code',
        ]);
    }

    /** OAuth kodu → kısa ömürlü kullanıcı token'ı → uzun ömürlü (≈60 gün) kullanıcı token'ı. */
    public function koduTokenaCevir(string $kod, string $yonlendirme): string {
        $kisa = $this->istek('GET', 'oauth/access_token', [
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri'  => $yonlendirme,
            'code'          => $kod,
        ]);
        $uzun = $this->istek('GET', 'oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->appId,
            'client_secret'     => $this->appSecret,
            'fb_exchange_token' => $kisa['access_token'] ?? '',
        ]);
        if (empty($uzun['access_token'])) throw new FacebookHata('Facebook token alınamadı.');
        return $uzun['access_token'];
    }

    /**
     * Kullanıcının yönettiği sayfalar. Uzun ömürlü kullanıcı token'ıyla alınan
     * sayfa token'larının süresi dolmaz (şifre değişimi / izin iptali hariç).
     * Yalnızca paylaşım yetkisi (CREATE_CONTENT) olan sayfalar döner.
     */
    public function sayfalar(string $kullaniciToken): array {
        $sonuc = [];
        $params = ['fields' => 'id,name,access_token,tasks,picture{url}', 'limit' => 100];
        $kenar  = 'me/accounts';
        for ($sayfa = 0; $sayfa < 10; $sayfa++) {
            $yanit = $this->istek('GET', $kenar, $params, $kullaniciToken);
            foreach ($yanit['data'] ?? [] as $s) {
                $gorevler = $s['tasks'] ?? [];
                if ($gorevler && !in_array('CREATE_CONTENT', $gorevler, true)) continue;
                if (empty($s['access_token'])) continue;
                $sonuc[] = [
                    'id'           => (string)$s['id'],
                    'ad'           => (string)($s['name'] ?? ''),
                    'access_token' => (string)$s['access_token'],
                    'resim_url'    => (string)($s['picture']['data']['url'] ?? ''),
                ];
            }
            $sonraki = $yanit['paging']['cursors']['after'] ?? null;
            if (!$sonraki || empty($yanit['paging']['next'])) break;
            $params['after'] = $sonraki;
        }
        return $sonuc;
    }

    /** Sayfada gönderi yayınlar, gönderi id'sini döner. */
    public function sayfadaPaylas(string $sayfaId, string $sayfaToken, array $paylasim): string {
        [$kenar, $params] = facebookIstegiOlustur($paylasim);
        $yanit = $this->istek('POST', rawurlencode($sayfaId) . '/' . $kenar, $params, $sayfaToken);
        $id = $yanit['post_id'] ?? $yanit['id'] ?? '';
        if ($id === '') throw new FacebookHata('Facebook gönderi kimliği döndürmedi.');
        return (string)$id;
    }

    public function istek(string $metot, string $kenar, array $params, ?string $token = null): array {
        if ($token !== null) {
            $params['access_token']    = $token;
            $params['appsecret_proof'] = hash_hmac('sha256', $token, $this->appSecret);
        }
        $url = "https://graph.facebook.com/{$this->surum}/" . ltrim($kenar, '/');
        [$kod, $govde] = ($this->http)($metot, $url, $params);
        $veri = json_decode($govde, true);
        if (!is_array($veri)) {
            throw new FacebookHata("Facebook'tan geçersiz yanıt (HTTP $kod).", 0, 0, $kod);
        }
        if (isset($veri['error']) || $kod >= 400) {
            $e = $veri['error'] ?? [];
            throw new FacebookHata(
                (string)($e['error_user_msg'] ?? $e['message'] ?? "Facebook hatası (HTTP $kod)"),
                (int)($e['code'] ?? 0), (int)($e['error_subcode'] ?? 0), $kod
            );
        }
        return $veri;
    }

    private function curlIstek(string $metot, string $url, array $params): array {
        $ch = curl_init();
        $secenekler = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($metot === 'POST') {
            $secenekler[CURLOPT_URL]        = $url;
            $secenekler[CURLOPT_POST]       = true;
            $secenekler[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            $secenekler[CURLOPT_URL] = $url . '?' . http_build_query($params);
        }
        curl_setopt_array($ch, $secenekler);
        $govde = curl_exec($ch);
        $kod   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hata  = curl_error($ch);
        curl_close($ch);
        if ($govde === false) throw new FacebookHata("Facebook'a bağlanılamadı: $hata");
        return [$kod, (string)$govde];
    }
}

/** Facebook uygulamasındaki "Geçerli OAuth Yönlendirme URI'leri" ile birebir aynı olmalı. */
function facebookYonlendirmeUrl(): string {
    return siteTabanUrl() . '/facebook_callback.php';
}

/** Sitenin kök adresi: panel ayarı app_url, boşsa istekten türetilir. */
function siteTabanUrl(): string {
    $taban = rtrim(ayar('app_url'), '/');
    if ($taban === '' && isset($_SERVER['HTTP_HOST'])) {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
        $taban = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
               . rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    }
    return $taban;
}

// ------------------------------------------------------------
//  Hesap kaydı
// ------------------------------------------------------------
function facebookSayfalariniKaydet(int $kullaniciId, array $sayfalar): int {
    $n = 0;
    foreach ($sayfalar as $s) {
        $simdi = simdi();
        DB::exec(
            "INSERT INTO sosyal_hesaplar
               (kullanici_id, platform, dis_id, ad, resim_url, access_token, durum, son_hata, baglanma, guncelleme)
             VALUES (?, 'facebook', ?, ?, ?, ?, 'aktif', NULL, ?, ?)
             ON DUPLICATE KEY UPDATE ad=VALUES(ad), resim_url=VALUES(resim_url),
               access_token=VALUES(access_token), durum='aktif', son_hata=NULL, guncelleme=VALUES(guncelleme)",
            [$kullaniciId, $s['id'], $s['ad'], $s['resim_url'], tokenSifrele($s['access_token']), $simdi, $simdi]
        );
        $n++;
    }
    return $n;
}

// ------------------------------------------------------------
//  Kuyruk işleyici
// ------------------------------------------------------------

/**
 * Zamanı gelmiş paylaşımları yayınlar.
 * $sadeceIdler verilirse yalnızca o kayıtlar (ör. "Şimdi paylaş") işlenir.
 * Dönüş: ['gonderildi' => n, 'hata' => n, 'ertelendi' => n]
 */
function paylasimKuyrugunuIsle(?FacebookGraph $fb = null, int $limit = 25, array $sadeceIdler = []): array {
    $ozet  = ['gonderildi' => 0, 'hata' => 0, 'ertelendi' => 0];
    $simdi = simdi();

    // Yayın sırasında süreç ölmüşse kayıt 'gonderiliyor'da kalır. Facebook'a
    // ulaşmış olabileceği için tekrar denemek çift gönderi riski taşır →
    // otomatik tekrar yok, kullanıcıya kontrol etmesi söylenir.
    DB::exec(
        "UPDATE sosyal_paylasimlar SET durum='hata',
           hata_mesaji='Yayın yarıda kesildi; sayfada gönderinin olup olmadığını kontrol edin.'
         WHERE durum='gonderiliyor' AND kilit_zamani < ?",
        [date('Y-m-d H:i:s', time() - SOSYAL_KILIT_ZAMAN_ASIMI * 60)]
    );

    $sql = "SELECT p.*, h.platform, h.dis_id, h.access_token, h.durum AS hesap_durum
            FROM sosyal_paylasimlar p JOIN sosyal_hesaplar h ON h.id = p.hesap_id
            WHERE p.durum='bekliyor' AND p.planlanan_zaman <= ?";
    $params = [$simdi];
    if ($sadeceIdler) {
        $sql .= ' AND p.id IN (' . implode(',', array_fill(0, count($sadeceIdler), '?')) . ')';
        $params = array_merge($params, array_map('intval', $sadeceIdler));
    }
    $sql .= ' ORDER BY p.planlanan_zaman LIMIT ' . max(1, $limit);

    foreach (DB::rows($sql, $params) as $p) {
        // Kaydı kilitle — aynı anda çalışan başka bir süreç almışsa atla
        $alindi = DB::exec(
            "UPDATE sosyal_paylasimlar SET durum='gonderiliyor', kilit_zamani=?, deneme_sayisi=deneme_sayisi+1
             WHERE id=? AND durum='bekliyor'",
            [simdi(), $p['id']]
        );
        if ($alindi !== 1) continue;
        $deneme = (int)$p['deneme_sayisi'] + 1;

        try {
            if ($p['hesap_durum'] !== 'aktif') {
                throw new FacebookHata('Hesap ' . ($p['hesap_durum'] === 'pasif' ? 'pasif' : 'yeniden bağlanmalı') . '.', 190);
            }
            if ($p['platform'] !== 'facebook') {
                throw new FacebookHata('Desteklenmeyen platform: ' . $p['platform'], 100);
            }
            $fb = $fb ?? FacebookGraph::ayarlardan();
            $postId = $fb->sayfadaPaylas($p['dis_id'], tokenCoz((string)$p['access_token']), $p);
            DB::exec(
                "UPDATE sosyal_paylasimlar SET durum='gonderildi', dis_post_id=?, hata_mesaji=NULL, gonderim_zamani=?
                 WHERE id=?",
                [$postId, simdi(), $p['id']]
            );
            $ozet['gonderildi']++;
        } catch (Throwable $e) {
            $fbHata  = $e instanceof FacebookHata ? $e : null;
            // FB dışı hatalar (token çözülemedi vb.) kalıcıdır; DB hataları geçici sayılır
            $kalici  = $fbHata ? $fbHata->kalici() : !($e instanceof PDOException);
            $karar   = yenidenDenemeKarari($deneme, $kalici);
            $mesaj   = mb_substr($e->getMessage(), 0, 1000);

            if ($fbHata && $fbHata->tokenHatasi() && $p['hesap_durum'] === 'aktif') {
                DB::exec("UPDATE sosyal_hesaplar SET durum='yeniden_baglan', son_hata=?, guncelleme=? WHERE id=?",
                    [mb_substr($mesaj, 0, 500), simdi(), $p['hesap_id']]);
            }
            DB::exec(
                "UPDATE sosyal_paylasimlar SET durum=?, hata_mesaji=?, planlanan_zaman=? WHERE id=?",
                [$karar['durum'], $mesaj,
                 $karar['gecikme_dk'] ? date('Y-m-d H:i:s', time() + $karar['gecikme_dk'] * 60) : $p['planlanan_zaman'],
                 $p['id']]
            );
            $ozet[$karar['durum'] === 'hata' ? 'hata' : 'ertelendi']++;
        }
    }
    return $ozet;
}

// ------------------------------------------------------------
//  Zamanlayıcı: CLI cron, web cron URL'si ve site ziyaretleri
// ------------------------------------------------------------

/** Kuyruğu çalıştırır ve son çalışmayı admin panelinde göstermek için kaydeder. */
function kuyrukCalistir(string $kaynak, int $limit = 50): array {
    $ozet = paylasimKuyrugunuIsle(null, $limit);
    DB::exec("INSERT INTO sistem_ayarlari (anahtar, deger, guncelleme) VALUES ('__cron_son_calisma', ?, ?)
              ON DUPLICATE KEY UPDATE deger=VALUES(deger), guncelleme=VALUES(guncelleme)",
             [json_encode(['kaynak' => $kaynak] + $ozet), simdi()]);
    return $ozet;
}

/** Harici cron servisleri için gizli URL anahtarı (uygulama anahtarından türetilir). */
function webCronAnahtari(): string {
    $k = uygulamaAnahtari();
    return $k === '' ? '' : substr(hash_hmac('sha256', 'web-cron', $k), 0, 32);
}

/**
 * Sunucuda cron kurulmamışsa paylaşımlar site ziyaretleriyle yayınlanır:
 * en fazla dakikada bir, sayfa yanıtı gönderildikten sonra (PHP-FPM'de
 * fastcgi_finish_request). Panel ayarı `web_cron` ile kapatılabilir.
 */
function webCronTetikle(): void {
    register_shutdown_function('webCronCalistir');
}

/** webCronTetikle()'nin yanıt sonrası çalışan gövdesi. */
function webCronCalistir(): void {
    try {
        if (ayar('web_cron') !== '1') return;
        sosyalSemaKur();
        $simdi = time();
        DB::exec("INSERT IGNORE INTO sistem_ayarlari (anahtar, deger, guncelleme) VALUES ('__web_cron_tetik', '0', ?)", [simdi()]);
        // Atomik sahiplenme: aynı dakikada yalnızca bir istek çalıştırır
        $alindi = DB::exec("UPDATE sistem_ayarlari SET deger=?, guncelleme=?
                            WHERE anahtar='__web_cron_tetik' AND CAST(deger AS UNSIGNED) <= ?",
                           [(string)$simdi, simdi(), $simdi - 60]);
        if ($alindi !== 1) return;
        // Bekleyen iş yoksa hiç uğraşma
        $bekleyen = (int)DB::scalar("SELECT COUNT(*) FROM sosyal_paylasimlar WHERE durum='bekliyor' AND planlanan_zaman <= ?", [simdi()]);
        if ($bekleyen === 0) return;
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $arkaPlan = function_exists('fastcgi_finish_request') && fastcgi_finish_request();
        ignore_user_abort(true);
        kuyrukCalistir('ziyaret', $arkaPlan ? 25 : 5);
    } catch (Throwable $e) {
        error_log('webCronCalistir: ' . $e->getMessage());
    }
}
