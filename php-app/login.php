<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Zaten girişli ise yönlendir
if (!empty($_SESSION['user'])) {
    header('Location: index.php'); exit;
}

$tab   = $_GET['tab'] ?? 'giris';
$error = '';
$success = '';

// DB bağlantı testi + tablo kurulumu
$dbOk = false;
try {
    DB::get();
    $sql = file_get_contents(__DIR__ . '/install.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt) try { DB::get()->exec($stmt); } catch(PDOException $e) {}
    }
    $dbOk = true;
    // İlk admin yoksa oluştur seçeneği
    // Migrasyon: dummy placeholder admin varsa say
    $adminVar = (int)DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE rol='admin' AND sifre != '\$2y\$10\$dummy.hash.placeholder.not.valid'");
    $hasDummy  = (int)DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE sifre = '\$2y\$10\$dummy.hash.placeholder.not.valid'");
} catch (PDOException $e) {
    $error = "Veritabanı bağlantı hatası: " . $e->getMessage();
}

// ---- GİRİŞ ----
if ($dbOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'giris') {
    $email = trim($_POST['email'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $user  = DB::row("SELECT * FROM kullanicilar WHERE email=? AND aktif=1", [$email]);
    if ($user && password_verify($sifre, $user['sifre'])) {
        $_SESSION['user'] = ['id'=>$user['id'],'email'=>$user['email'],'ad'=>$user['ad_soyad'],'rol'=>$user['rol']];
        // Mağaza seçimi: birden fazla mağaza varsa seçtir, tek ise direkt gir
        $magazalar = DB::rows("SELECT * FROM magazalar WHERE kullanici_id=? AND aktif=1", [$user['id']]);
        if (count($magazalar) === 1) {
            $_SESSION['magaza'] = $magazalar[0];
            header('Location: index.php'); exit;
        }
        header('Location: magaza_sec.php'); exit;
    } else {
        $error = "E-posta veya şifre hatalı.";
    }
}

// ---- KAYIT ----
if ($dbOk && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'kayit') {
    $email   = trim($_POST['email']   ?? '');
    $sifre   = $_POST['sifre']        ?? '';
    $sifre2  = $_POST['sifre2']       ?? '';
    $adSoyad = trim($_POST['ad_soyad']?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = "Geçerli bir e-posta girin."; }
    elseif (strlen($sifre) < 6) { $error = "Şifre en az 6 karakter olmalı."; }
    elseif ($sifre !== $sifre2) { $error = "Şifreler eşleşmiyor."; }
    elseif (DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE email=?", [$email])) { $error = "Bu e-posta zaten kayıtlı."; }
    else {
        $hash = password_hash($sifre, PASSWORD_DEFAULT);
        $rol  = (!isset($adminVar) || $adminVar === 0) ? 'admin' : 'uye';
        // Migrasyon: dummy admin varsa onu gerçek hesapla güncelle
        if (isset($hasDummy) && $hasDummy > 0 && $rol === 'admin') {
            DB::exec("UPDATE kullanicilar SET email=?,sifre=?,ad_soyad=?,rol='admin' WHERE sifre=?",
                [$email,$hash,$adSoyad,'\$2y\$10\$dummy.hash.placeholder.not.valid']);
        } else {
            DB::exec("INSERT INTO kullanicilar (email,sifre,ad_soyad,rol) VALUES (?,?,?,?)", [$email,$hash,$adSoyad,$rol]);
        }
        $success = "Hesap oluşturuldu! " . ($rol==='admin' ? '(Admin yetkisi verildi — mevcut verilerinize erişebilirsiniz)' : '') . " Giriş yapabilirsiniz.";
        $tab = 'giris';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trendyol Analiz — Giriş</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0f1117;color:#e8eaf6;font-family:'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.box{background:#1e2035;border:1px solid #2e3150;border-radius:16px;padding:36px;width:100%;max-width:420px;}
.logo{text-align:center;margin-bottom:28px;}
.logo h1{font-size:22px;color:#f27a1a;}
.logo p{color:#9099c4;font-size:13px;margin-top:4px;}
.tabs{display:flex;border-bottom:1px solid #2e3150;margin-bottom:24px;}
.tab{flex:1;text-align:center;padding:10px;cursor:pointer;font-size:14px;color:#9099c4;border-bottom:2px solid transparent;transition:.2s;}
.tab.active{color:#f27a1a;border-bottom-color:#f27a1a;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:12px;color:#9099c4;margin-bottom:6px;}
.form-group input{width:100%;background:#252840;border:1px solid #2e3150;color:#e8eaf6;padding:10px 14px;border-radius:8px;font-size:14px;outline:none;transition:.2s;}
.form-group input:focus{border-color:#f27a1a;}
.btn{width:100%;padding:12px;background:#f27a1a;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:.2s;}
.btn:hover{background:#d4610a;}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.alert-danger{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;}
.alert-success{background:rgba(46,204,113,.15);border:1px solid rgba(46,204,113,.3);color:#2ecc71;}
.alert-warning{background:rgba(241,196,15,.15);border:1px solid rgba(241,196,15,.3);color:#f1c40f;}
</style>
</head>
<body>
<div class="box">
    <div class="logo">
        <h1>🛒 Trendyol Analiz</h1>
        <p>Kar/Zarar Yönetim Paneli</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!$dbOk && !$error): ?><div class="alert alert-warning">⚠️ config.php dosyasındaki MySQL bilgilerini kontrol edin.</div><?php endif; ?>
    <?php if ($dbOk && isset($hasDummy) && $hasDummy > 0): ?>
    <div class="alert alert-warning">ℹ️ Sistem migrasyon yapıldı. <strong>Şimdi kayıt olun</strong> — ilk kayıt otomatik admin yetkisi alır ve mevcut verilerinize erişebilirsiniz.</div>
    <?php elseif ($dbOk && isset($adminVar) && $adminVar === 0): ?>
    <div class="alert alert-warning">ℹ️ Henüz admin yok. İlk kayıt olan kullanıcı admin yetkisi alır.</div>
    <?php endif; ?>

    <div class="tabs">
        <div class="tab <?= $tab==='giris'?'active':'' ?>" onclick="showTab('giris')">Giriş Yap</div>
        <div class="tab <?= $tab==='kayit'?'active':'' ?>" onclick="showTab('kayit')">Kayıt Ol</div>
    </div>

    <div id="tab-giris" style="display:<?= $tab==='giris'?'block':'none' ?>">
        <form method="POST">
            <input type="hidden" name="form" value="giris">
            <div class="form-group"><label>E-posta</label><input type="email" name="email" required autofocus placeholder="ornek@email.com"></div>
            <div class="form-group"><label>Şifre</label><input type="password" name="sifre" required placeholder="••••••••"></div>
            <button type="submit" class="btn">Giriş Yap →</button>
        </form>
    </div>

    <div id="tab-kayit" style="display:<?= $tab==='kayit'?'block':'none' ?>">
        <form method="POST">
            <input type="hidden" name="form" value="kayit">
            <div class="form-group"><label>Ad Soyad</label><input type="text" name="ad_soyad" placeholder="Ad Soyad"></div>
            <div class="form-group"><label>E-posta</label><input type="email" name="email" required placeholder="ornek@email.com"></div>
            <div class="form-group"><label>Şifre</label><input type="password" name="sifre" required placeholder="En az 6 karakter"></div>
            <div class="form-group"><label>Şifre Tekrar</label><input type="password" name="sifre2" required placeholder="Şifrenizi tekrar girin"></div>
            <button type="submit" class="btn">Kayıt Ol →</button>
        </form>
    </div>
</div>
<script>
function showTab(t) {
    document.getElementById('tab-giris').style.display = t==='giris'?'block':'none';
    document.getElementById('tab-kayit').style.display = t==='kayit'?'block':'none';
    document.querySelectorAll('.tab').forEach((el,i)=>el.classList.toggle('active', i===(t==='giris'?0:1)));
}
</script>
</body>
</html>
