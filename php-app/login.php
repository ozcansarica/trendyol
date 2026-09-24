<?php
// ============================================================
//  Giriş / Kayıt
//  - Kurulum yapılmamışsa (DB yok ya da admin yok) kurulum.php'ye gider.
//  - Kayıt, admin panelindeki "Yeni üye kaydı açık" ayarına bağlıdır.
//  - Aynı e-posta + IP'den art arda hatalı girişler geçici olarak engellenir.
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/arayuz.php';

if (authUser()) yonlendir('index.php');

try {
    DB::get();
    tumSemayiKur();
    if (!gercekAdminVarMi()) yonlendir('kurulum.php');
} catch (PDOException $e) {
    yonlendir('kurulum.php');
}

$kayitAcik = ayar('kayit_acik') === '1';
$sekme     = ($_GET['tab'] ?? 'giris') === 'kayit' && $kayitAcik ? 'kayit' : 'giris';
$hata      = '';
if (($_GET['durum'] ?? '') === 'pasif') $hata = 'Hesabınız pasif durumda. Lütfen yöneticiyle iletişime geçin.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';
    if (!csrfDogrula()) {
        $hata = 'Oturum süresi doldu, lütfen tekrar deneyin.';
    }
    // ---- GİRİŞ ----
    elseif ($form === 'giris') {
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $sifre = (string)($_POST['sifre'] ?? '');
        if (girisKilitliMi($email)) {
            $hata = 'Çok fazla hatalı deneme. ' . GIRIS_KILIT_DAKIKA . ' dakika sonra tekrar deneyin.';
        } else {
            $k = DB::row("SELECT * FROM kullanicilar WHERE email=?", [$email]);
            if ($k && password_verify($sifre, $k['sifre'])) {
                if (!(int)$k['aktif']) {
                    $hata = 'Hesabınız pasif durumda. Lütfen yöneticiyle iletişime geçin.';
                } else {
                    girisDenemeleriniSil($email);
                    if (password_needs_rehash($k['sifre'], PASSWORD_DEFAULT)) {
                        DB::exec("UPDATE kullanicilar SET sifre=? WHERE id=?", [password_hash($sifre, PASSWORD_DEFAULT), $k['id']]);
                    }
                    oturumAc($k);
                    // Tek mağaza varsa doğrudan gir; yoksa seçim/ekleme ekranı
                    $magazalar = DB::rows("SELECT * FROM magazalar WHERE kullanici_id=? AND aktif=1", [$k['id']]);
                    if (count($magazalar) === 1) {
                        $_SESSION['magaza'] = $magazalar[0];
                        yonlendir('index.php');
                    }
                    yonlendir($k['rol'] === 'admin' && !$magazalar ? 'admin.php' : 'magaza_sec.php');
                }
            } else {
                hataliGirisKaydet($email);
                $hata = 'E-posta veya şifre hatalı.';
            }
        }
    }
    // ---- KAYIT ----
    elseif ($form === 'kayit' && $kayitAcik) {
        $sekme   = 'kayit';
        $email   = mb_strtolower(trim($_POST['email'] ?? ''));
        $adSoyad = trim($_POST['ad_soyad'] ?? '');
        $sifre   = (string)($_POST['sifre'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $hata = 'Geçerli bir e-posta girin.';
        elseif ($e = sifreDogrula($sifre, (string)($_POST['sifre2'] ?? ''))) $hata = $e;
        elseif (DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE email=?", [$email])) $hata = 'Bu e-posta zaten kayıtlı.';
        else {
            DB::exec("INSERT INTO kullanicilar (email, sifre, ad_soyad, rol, aktif) VALUES (?,?,?,'uye',1)",
                     [$email, password_hash($sifre, PASSWORD_DEFAULT), $adSoyad]);
            oturumAc(DB::row("SELECT * FROM kullanicilar WHERE id=?", [DB::lastId()]));
            yonlendir('magaza_sec.php');
        }
    }
}

kutuBasla('Kar/Zarar Yönetim Paneli');
if ($hata): ?><div class="alert alert-danger"><?= h($hata) ?></div><?php endif; ?>
<style>
.tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:22px;}
.tab{flex:1;text-align:center;padding:10px;cursor:pointer;font-size:14px;color:var(--text2);border-bottom:2px solid transparent;text-decoration:none;}
.tab.active{color:var(--primary);border-bottom-color:var(--primary);}
</style>
<?php if ($kayitAcik): ?>
<div class="tabs">
    <a class="tab <?= $sekme === 'giris' ? 'active' : '' ?>" href="?tab=giris">Giriş Yap</a>
    <a class="tab <?= $sekme === 'kayit' ? 'active' : '' ?>" href="?tab=kayit">Kayıt Ol</a>
</div>
<?php endif; ?>

<?php if ($sekme === 'giris'): ?>
<form method="post">
    <?= csrfAlan() ?><input type="hidden" name="form" value="giris">
    <div class="form-group"><label>E-posta</label><input type="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>" placeholder="ornek@email.com" autocomplete="username"></div>
    <div class="form-group"><label>Şifre</label><input type="password" name="sifre" required placeholder="••••••••" autocomplete="current-password"></div>
    <button class="btn btn-primary btn-blok">Giriş Yap →</button>
</form>
<?php if (!$kayitAcik): ?><p class="muted" style="text-align:center;margin-top:16px">Yeni üyelik şu an kapalı. Hesap için yöneticiyle iletişime geçin.</p><?php endif; ?>
<?php else: ?>
<form method="post">
    <?= csrfAlan() ?><input type="hidden" name="form" value="kayit">
    <div class="form-group"><label>Ad Soyad</label><input type="text" name="ad_soyad" value="<?= h($_POST['ad_soyad'] ?? '') ?>" placeholder="Ad Soyad"></div>
    <div class="form-group"><label>E-posta</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>" placeholder="ornek@email.com" autocomplete="username"></div>
    <div class="form-group"><label>Şifre</label><input type="password" name="sifre" required minlength="8" placeholder="En az 8 karakter" autocomplete="new-password"></div>
    <div class="form-group"><label>Şifre Tekrar</label><input type="password" name="sifre2" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-primary btn-blok">Kayıt Ol →</button>
</form>
<?php endif;
kutuBitir();
