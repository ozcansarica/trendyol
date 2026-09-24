<?php
// ============================================================
//  Sistem çekirdeği: panelden yönetilen ayarlar, CSRF, bildirim,
//  giriş denemesi sınırlaması ve .env yazımı (kurulum sihirbazı).
//
//  Ayarlar `sistem_ayarlari` tablosunda tutulur ve admin panelinden
//  (admin.php → Sistem Ayarları) değiştirilir. Tabloda değer yoksa
//  .env'deki karşılığına, o da yoksa varsayılana düşülür — böylece
//  eski .env tabanlı kurulumlar da çalışmaya devam eder.
//  Yalnızca veritabanı bağlantı bilgileri .env'de kalmak zorundadır
//  (DB'ye ulaşmadan okunamazlar); onları da kurulum.php yazar.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Panelden yönetilen ayarlar: anahtar => [etiket, .env karşılığı, varsayılan, tür].
 * tür: metin | gizli (şifreli saklanır, formda gösterilmez) | evethayir | saat_dilimi
 */
const SISTEM_AYARLARI = [
    'site_adi'         => ['Site adı',                         '',                 'Trendyol Analiz', 'metin'],
    'kayit_acik'       => ['Yeni üye kaydı açık',               '',                 '1',               'evethayir'],
    'app_url'          => ['Site adresi (APP_URL)',             'APP_URL',          '',                'metin'],
    'app_timezone'     => ['Saat dilimi',                       'APP_TIMEZONE',     'Europe/Istanbul', 'saat_dilimi'],
    'fb_app_id'        => ['Facebook App ID',                   'FB_APP_ID',        '',                'metin'],
    'fb_app_secret'    => ['Facebook App Secret',               'FB_APP_SECRET',    '',                'gizli'],
    'fb_graph_version' => ['Facebook Graph API sürümü',         'FB_GRAPH_VERSION', 'v23.0',           'metin'],
    'web_cron'         => ['Paylaşımları site ziyaretlerinde de yayınla (cron gerekmez)', '', '1', 'evethayir'],
];

const GIRIS_MAX_HATALI   = 5;   // bu kadar hatalı denemeden sonra…
const GIRIS_KILIT_DAKIKA = 15;  // …bu süre giriş engellenir (e-posta + IP başına)

// ------------------------------------------------------------
//  Şema
// ------------------------------------------------------------
function sistemSemaKur(): void {
    static $kuruldu = false;
    if ($kuruldu) return;
    DB::get()->exec("CREATE TABLE IF NOT EXISTS `sistem_ayarlari` (
        `anahtar`    VARCHAR(64) PRIMARY KEY,
        `deger`      TEXT,
        `guncelleme` DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    DB::get()->exec("CREATE TABLE IF NOT EXISTS `giris_denemeleri` (
        `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `email`   VARCHAR(150),
        `ip`      VARCHAR(45),
        `zaman`   DATETIME,
        INDEX `idx_email_ip` (`email`, `ip`, `zaman`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    kolonEkle('kullanicilar', 'son_giris', 'DATETIME NULL');
    kolonEkle('magazalar', 'anthropic_api_key', 'VARCHAR(200) DEFAULT NULL');
    $kuruldu = true;
}

/** Tabloda kolon yoksa ekler (MySQL'de ADD COLUMN IF NOT EXISTS olmadığı için). */
function kolonEkle(string $tablo, string $kolon, string $tanim): void {
    try {
        $var = DB::scalar("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?", [$tablo, $kolon]);
        if (!$var) DB::get()->exec("ALTER TABLE `$tablo` ADD COLUMN `$kolon` $tanim");
    } catch (PDOException $e) { /* tablo henüz yoksa install.sql oluşturur */ }
}

// ------------------------------------------------------------
//  Ayarlar
// ------------------------------------------------------------
/** @var array<string,string>|null  null = henüz yüklenmedi, false durumu için ayrı bayrak */
$GLOBALS['__ayarlar'] = null;

function ayarlariYukle(bool $yenile = false): array {
    if ($GLOBALS['__ayarlar'] !== null && !$yenile) return $GLOBALS['__ayarlar'];
    $GLOBALS['__ayarlar'] = [];
    try {
        sistemSemaKur();
        foreach (DB::rows("SELECT anahtar, deger FROM sistem_ayarlari") as $r) {
            $GLOBALS['__ayarlar'][$r['anahtar']] = (string)$r['deger'];
        }
    } catch (Throwable $e) {
        // DB yok (ör. testler, kurulum öncesi) → .env / varsayılanlar kullanılır
    }
    return $GLOBALS['__ayarlar'];
}

/** Ayar değeri: DB → .env → varsayılan. Gizli ayarlar çözülmüş döner. */
function ayar(string $anahtar, ?string $varsayilan = null): string {
    $tanim = SISTEM_AYARLARI[$anahtar] ?? null;
    $db    = ayarlariYukle();
    if (isset($db[$anahtar]) && $db[$anahtar] !== '') {
        $deger = $db[$anahtar];
        if (($tanim[3] ?? '') === 'gizli') {
            try { return tokenCoz($deger); } catch (Throwable $e) { return ''; }
        }
        return $deger;
    }
    if ($tanim && $tanim[1] !== '' && env($tanim[1]) !== '') return env($tanim[1]);
    return $varsayilan ?? ($tanim[2] ?? '');
}

function ayarKaydet(string $anahtar, string $deger): void {
    if ((SISTEM_AYARLARI[$anahtar][3] ?? '') === 'gizli' && $deger !== '') {
        $deger = tokenSifrele($deger);
    }
    DB::exec("INSERT INTO sistem_ayarlari (anahtar, deger, guncelleme) VALUES (?,?,?)
              ON DUPLICATE KEY UPDATE deger=VALUES(deger), guncelleme=VALUES(guncelleme)",
             [$anahtar, $deger, date('Y-m-d H:i:s')]);
    ayarlariYukle(true);
}

/** Ayar değerinin nereden geldiği (panelde gösterilir). */
function ayarKaynagi(string $anahtar): string {
    $db = ayarlariYukle();
    if (isset($db[$anahtar]) && $db[$anahtar] !== '') return 'panel';
    $env = SISTEM_AYARLARI[$anahtar][1] ?? '';
    if ($env !== '' && env($env) !== '') return '.env';
    return 'varsayılan';
}

// ------------------------------------------------------------
//  Uygulama anahtarı (sosyal medya token'ları ve gizli ayarları şifreler)
//  Öncelik .env APP_KEY; yoksa ilk ihtiyaçta üretilip DB'ye yazılır,
//  böylece kurulum ek adım gerektirmez. (Anahtarın .env'de olması daha
//  güvenlidir: DB sızsa bile şifreli token'lar çözülemez.)
// ------------------------------------------------------------
function uygulamaAnahtari(): string {
    $env = env('APP_KEY');
    if ($env !== '') return $env;
    static $dbAnahtar = null;
    if ($dbAnahtar !== null) return $dbAnahtar;
    try {
        sistemSemaKur();
        $k = (string)DB::scalar("SELECT deger FROM sistem_ayarlari WHERE anahtar='__app_key'");
        if ($k === '') {
            $k = bin2hex(random_bytes(32));
            DB::exec("INSERT IGNORE INTO sistem_ayarlari (anahtar, deger, guncelleme) VALUES ('__app_key', ?, ?)",
                     [$k, date('Y-m-d H:i:s')]);
            // Aynı anda iki istek üretmiş olabilir — kazanan kayıt geçerli
            $k = (string)DB::scalar("SELECT deger FROM sistem_ayarlari WHERE anahtar='__app_key'");
        }
        return $dbAnahtar = $k;
    } catch (Throwable $e) {
        return '';
    }
}

// AES-256-GCM: sosyal medya token'ları ve gizli panel ayarları
function tokenSifrele(string $duz, ?string $anahtar = null): string {
    $anahtar = $anahtar ?? uygulamaAnahtari();
    if ($anahtar === '') throw new RuntimeException('Uygulama anahtarı alınamadı (veritabanı bağlantısını kontrol edin).');
    $k   = hash('sha256', $anahtar, true);
    $iv  = random_bytes(12);
    $tag = '';
    $sifreli = openssl_encrypt($duz, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
    if ($sifreli === false) throw new RuntimeException('Token şifrelenemedi.');
    return 'v1:' . base64_encode($iv . $tag . $sifreli);
}

function tokenCoz(string $kayit, ?string $anahtar = null): string {
    $anahtar = $anahtar ?? uygulamaAnahtari();
    if ($anahtar === '') throw new RuntimeException('Uygulama anahtarı alınamadı (veritabanı bağlantısını kontrol edin).');
    if (!str_starts_with($kayit, 'v1:')) throw new RuntimeException('Bilinmeyen token biçimi.');
    $ham = base64_decode(substr($kayit, 3), true);
    if ($ham === false || strlen($ham) < 29) throw new RuntimeException('Bozuk token kaydı.');
    $k   = hash('sha256', $anahtar, true);
    $duz = openssl_decrypt(substr($ham, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA,
                           substr($ham, 0, 12), substr($ham, 12, 16));
    if ($duz === false) throw new RuntimeException('Token çözülemedi (uygulama anahtarı değişmiş olabilir — hesabı yeniden bağlayın).');
    return $duz;
}

// ------------------------------------------------------------
//  Görünüm / form yardımcıları
// ------------------------------------------------------------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrfAlan(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrfToken()) . '">';
}
function csrfDogrula(): bool {
    return hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''));
}

function bildirim(string $tip, string $mesaj): void {
    $_SESSION['bildirim'] = [$tip, $mesaj];
}
function bildirimAl(): ?array {
    $b = $_SESSION['bildirim'] ?? null;
    unset($_SESSION['bildirim']);
    return $b;
}
function bildirimGoster(): string {
    $b = bildirimAl();
    if (!$b) return '';
    return '<div class="alert ' . ($b[0] === 'basari' ? 'alert-success' : 'alert-danger') . '">' . h($b[1]) . '</div>';
}
function yonlendir(string $url, ?string $tip = null, ?string $mesaj = null): never {
    if ($tip !== null) bildirim($tip, (string)$mesaj);
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------
//  Doğrulama (saf — tests/sistem_test.php)
// ------------------------------------------------------------
function sifreDogrula(string $sifre, string $tekrar): ?string {
    if (mb_strlen($sifre) < 8)  return 'Şifre en az 8 karakter olmalı.';
    if ($sifre !== $tekrar)     return 'Şifreler eşleşmiyor.';
    return null;
}

/**
 * .env içeriğindeki anahtarları günceller, diğer satırları (yorumlar dahil)
 * olduğu gibi bırakır; olmayan anahtarları sona ekler.
 */
function envIcerigiGuncelle(string $icerik, array $degerler): string {
    $satirlar = $icerik === '' ? [] : preg_split('/\r?\n/', rtrim($icerik, "\r\n"));
    $kalan = $degerler;
    foreach ($satirlar as $i => $satir) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $satir, $m) && array_key_exists($m[1], $kalan)) {
            $satirlar[$i] = $m[1] . '=' . envDegeri($kalan[$m[1]]);
            unset($kalan[$m[1]]);
        }
    }
    foreach ($kalan as $k => $v) $satirlar[] = $k . '=' . envDegeri($v);
    return implode("\n", $satirlar) . "\n";
}
function envDegeri(string $v): string {
    // config.php satırı "=" ile ikiye böler ve trim'ler; satır sonu olamaz
    return trim(str_replace(["\r", "\n"], '', $v));
}

// ------------------------------------------------------------
//  Giriş denemesi sınırlaması
// ------------------------------------------------------------
function istemciIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
function girisKilitliMi(string $email): bool {
    $n = (int)DB::scalar("SELECT COUNT(*) FROM giris_denemeleri WHERE email=? AND ip=? AND zaman > ?",
        [mb_strtolower($email), istemciIp(), date('Y-m-d H:i:s', time() - GIRIS_KILIT_DAKIKA * 60)]);
    return $n >= GIRIS_MAX_HATALI;
}
function hataliGirisKaydet(string $email): void {
    DB::exec("INSERT INTO giris_denemeleri (email, ip, zaman) VALUES (?,?,?)",
        [mb_strtolower($email), istemciIp(), date('Y-m-d H:i:s')]);
    // Eski kayıtları temizle
    DB::exec("DELETE FROM giris_denemeleri WHERE zaman < ?", [date('Y-m-d H:i:s', time() - 86400)]);
}
function girisDenemeleriniSil(string $email): void {
    DB::exec("DELETE FROM giris_denemeleri WHERE email=? AND ip=?", [mb_strtolower($email), istemciIp()]);
}

/** Oturumu açar (login.php ve kurulum.php ortak kullanır). */
function oturumAc(array $kullanici): void {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$kullanici['id'], 'email' => $kullanici['email'],
                         'ad' => $kullanici['ad_soyad'], 'rol' => $kullanici['rol']];
    unset($_SESSION['magaza']);
    try { DB::exec("UPDATE kullanicilar SET son_giris=? WHERE id=?", [date('Y-m-d H:i:s'), $kullanici['id']]); }
    catch (PDOException $e) {}
}

/** Gerçek (şifresi ayarlanmış) admin var mı? install.sql'deki yer tutucu admin sayılmaz. */
function gercekAdminVarMi(): bool {
    return (int)DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE rol='admin' AND aktif=1
                            AND sifre <> '\$2y\$10\$dummy.hash.placeholder.not.valid'") > 0;
}

/** install.sql + ek şemalar (sosyal, sistem). Kurulum ve login ilk açılışta çağırır. */
function tumSemayiKur(): void {
    $sql = file_get_contents(__DIR__ . '/install.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        try { DB::get()->exec($stmt); } catch (PDOException $e) {}
    }
    sistemSemaKur();
    require_once __DIR__ . '/SosyalMedya.php';
    sosyalSemaKur();
}

// ------------------------------------------------------------
//  Mağaza kaydı (Hesabım ve Admin paneli ortak kullanır)
//  Gizli alanlar (API secret, Anthropic anahtarı) boş bırakılırsa
//  mevcut değer korunur — form bu alanları hiçbir zaman geri göstermez.
// ------------------------------------------------------------
function magazaFormundanKaydet(array $f, int $sahipId, ?int $magazaId = null): int {
    $ad = trim((string)($f['magaza_adi'] ?? ''));
    if ($ad === '') throw new InvalidArgumentException('Mağaza adı boş olamaz.');
    $sid = trim((string)($f['ty_seller_id'] ?? ''));
    $key = trim((string)($f['ty_api_key'] ?? ''));
    $sec = trim((string)($f['ty_api_secret'] ?? ''));
    $ant = trim((string)($f['anthropic_api_key'] ?? ''));
    $aktif = isset($f['aktif']) ? ((int)$f['aktif'] ? 1 : 0) : 1;

    if ($magazaId === null) {
        DB::exec("INSERT INTO magazalar (kullanici_id, magaza_adi, ty_seller_id, ty_api_key, ty_api_secret, anthropic_api_key, aktif)
                  VALUES (?,?,?,?,?,?,?)", [$sahipId, $ad, $sid, $key, $sec, $ant ?: null, $aktif]);
        return (int)DB::lastId();
    }
    DB::exec("UPDATE magazalar SET kullanici_id=?, magaza_adi=?, ty_seller_id=?, ty_api_key=?,
                ty_api_secret=IF(?='', ty_api_secret, ?), anthropic_api_key=IF(?='', anthropic_api_key, ?), aktif=?
              WHERE id=?", [$sahipId, $ad, $sid, $key, $sec, $sec, $ant, $ant, $aktif, $magazaId]);
    return $magazaId;
}

/** Gizli değeri maskeli gösterir: "••••3f2a". */
function maskele(?string $s): string {
    $s = (string)$s;
    return $s === '' ? '' : '••••' . mb_substr($s, -4);
}
