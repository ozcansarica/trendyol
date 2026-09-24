<?php
// ============================================================
//  Kurulum sihirbazı — dosya düzenlemeden, tarayıcıdan kurulum.
//   1. adım: MySQL bağlantı bilgileri → test → .env'ye yazılır
//            (yalnızca .env henüz yoksa; var olan kurulum ele geçirilemesin)
//   2. adım: tablolar kurulur, ilk admin hesabı oluşturulur
//            (yalnızca sistemde gerçek admin yoksa)
//  Kurulum bittikten sonra bu sayfa giriş sayfasına yönlendirir.
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/arayuz.php';

$envYolu  = __DIR__ . '/.env';
$envVar   = is_file($envYolu);
$dbHata   = null;
try { DB::get(); } catch (PDOException $e) { $dbHata = $e->getMessage(); }

$hata = '';
$envMetni = null; // .env yazılamazsa kullanıcıya gösterilecek içerik

// ---------------- 1. adım: veritabanı ----------------
if ($dbHata !== null) {
    $formAcik = !$envVar;
    if ($formAcik && $_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula()) {
        $host = trim($_POST['db_host'] ?? 'localhost') ?: 'localhost';
        $port = (string)(int)($_POST['db_port'] ?? 3306) ?: '3306';
        $ad   = trim($_POST['db_name'] ?? '');
        $kul  = trim($_POST['db_user'] ?? '');
        $sif  = (string)($_POST['db_pass'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $ad)) {
            $hata = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi içerebilir.';
        } else {
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $kul, $sif,
                               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$ad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$ad`");
                $icerik = envIcerigiGuncelle('', [
                    'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_NAME' => $ad,
                    'DB_USER' => $kul,  'DB_PASS' => $sif,
                    'APP_KEY' => bin2hex(random_bytes(32)),
                ]);
                if (@file_put_contents($envYolu, $icerik, LOCK_EX) !== false) {
                    @chmod($envYolu, 0600);
                    yonlendir('kurulum.php', 'basari', '✅ Veritabanı bağlantısı kaydedildi. Şimdi yönetici hesabını oluşturun.');
                }
                $envMetni = $icerik;
                $hata = 'Bağlantı başarılı fakat .env dosyası yazılamadı (klasör yazma izni yok). Aşağıdaki içeriği '
                      . 'sunucuda php-app/.env dosyasına kaydedip sayfayı yenileyin.';
            } catch (PDOException $e) {
                $hata = 'Bağlanılamadı: ' . $e->getMessage();
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $hata = 'Oturum doğrulaması başarısız, sayfayı yenileyip tekrar deneyin.';
    }

    kutuBasla('Kurulum — 1/2 Veritabanı');
    if ($hata): ?><div class="alert alert-danger"><?= h($hata) ?></div><?php endif;
    if ($envMetni !== null): ?>
        <textarea readonly style="min-height:170px;font-family:monospace;font-size:12px"><?= h($envMetni) ?></textarea>
    <?php elseif (!$formAcik): ?>
        <div class="alert alert-danger">
            <strong>Veritabanına bağlanılamıyor:</strong> <?= h($dbHata) ?><br><br>
            Sunucuda bir <code>.env</code> dosyası zaten var; güvenlik nedeniyle kurulum sihirbazı var olan
            bağlantı ayarlarının üzerine yazmaz. MySQL sunucusunun çalıştığını ve <code>.env</code>'deki
            DB_HOST / DB_NAME / DB_USER / DB_PASS değerlerini kontrol edin; yeniden kurmak için <code>.env</code>'yi silin.
        </div>
    <?php else: ?>
        <div class="alert alert-info">MySQL bilgilerini girin. Veritabanı yoksa (kullanıcının yetkisi yetiyorsa) otomatik oluşturulur.</div>
        <form method="post">
            <?= csrfAlan() ?>
            <div style="display:grid;grid-template-columns:1fr 90px;gap:0 10px">
                <div class="form-group"><label>Sunucu</label><input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
                <div class="form-group"><label>Port</label><input type="number" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>"></div>
            </div>
            <div class="form-group"><label>Veritabanı adı</label><input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? 'trendyol_analiz') ?>" required></div>
            <div class="form-group"><label>Kullanıcı adı</label><input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required autocomplete="off"></div>
            <div class="form-group"><label>Şifre</label><input type="password" name="db_pass" autocomplete="new-password"></div>
            <button class="btn btn-primary btn-blok">Bağlantıyı Test Et ve Kaydet →</button>
        </form>
    <?php endif;
    kutuBitir();
    exit;
}

// ---------------- 2. adım: tablolar + ilk admin ----------------
tumSemayiKur();
if (gercekAdminVarMi()) {
    yonlendir('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adSoyad = trim($_POST['ad_soyad'] ?? '');
    $email   = mb_strtolower(trim($_POST['email'] ?? ''));
    $sifre   = (string)($_POST['sifre'] ?? '');
    $siteAdi = trim($_POST['site_adi'] ?? '');
    if (!csrfDogrula())                                 $hata = 'Oturum doğrulaması başarısız, tekrar deneyin.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $hata = 'Geçerli bir e-posta girin.';
    elseif ($e = sifreDogrula($sifre, (string)($_POST['sifre2'] ?? ''))) $hata = $e;
    else {
        $hash  = password_hash($sifre, PASSWORD_DEFAULT);
        $dummy = DB::scalar("SELECT id FROM kullanicilar WHERE sifre='\$2y\$10\$dummy.hash.placeholder.not.valid' LIMIT 1");
        $baska = DB::scalar("SELECT id FROM kullanicilar WHERE email=?", [$email]);
        if ($baska && (!$dummy || (int)$baska !== (int)$dummy)) {
            // Var olan bir üyenin hesabı buradan devralınamasın
            $hata = 'Bu e-posta ile kayıtlı bir üye var; farklı bir e-posta kullanın.';
        } elseif ($dummy) {
            // install.sql'in yer tutucu admini (id=1) mevcut verilerin sahibi → onu devral
            DB::exec("UPDATE kullanicilar SET email=?, sifre=?, ad_soyad=?, rol='admin', aktif=1 WHERE id=?",
                     [$email, $hash, $adSoyad, $dummy]);
            $id = (int)$dummy;
        } else {
            DB::exec("INSERT INTO kullanicilar (email, sifre, ad_soyad, rol, aktif) VALUES (?,?,?,'admin',1)",
                     [$email, $hash, $adSoyad]);
            $id = (int)DB::lastId();
        }
    }
    if ($hata === '') {
        if ($siteAdi !== '') ayarKaydet('site_adi', $siteAdi);
        oturumAc(DB::row("SELECT * FROM kullanicilar WHERE id=?", [$id]));
        yonlendir('admin.php', 'basari', '🎉 Kurulum tamamlandı. Tüm ayarları bu panelden yönetebilirsiniz.');
    }
}

kutuBasla('Kurulum — 2/2 Yönetici Hesabı');
if ($hata): ?><div class="alert alert-danger"><?= h($hata) ?></div><?php endif; ?>
<div class="alert alert-info">Veritabanı hazır. Sistemi yönetecek admin hesabını oluşturun.</div>
<form method="post">
    <?= csrfAlan() ?>
    <div class="form-group"><label>Site adı</label><input type="text" name="site_adi" value="<?= h($_POST['site_adi'] ?? ayar('site_adi')) ?>"></div>
    <div class="form-group"><label>Ad Soyad</label><input type="text" name="ad_soyad" value="<?= h($_POST['ad_soyad'] ?? '') ?>"></div>
    <div class="form-group"><label>E-posta</label><input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required></div>
    <div class="form-group"><label>Şifre</label><input type="password" name="sifre" required minlength="8" placeholder="En az 8 karakter" autocomplete="new-password"></div>
    <div class="form-group"><label>Şifre tekrar</label><input type="password" name="sifre2" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-primary btn-blok">Kurulumu Tamamla →</button>
</form>
<?php
kutuBitir();
