<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

$user     = authUser();
$error    = '';
$magazalar= DB::rows("SELECT * FROM magazalar WHERE kullanici_id=? AND aktif=1 ORDER BY magaza_adi", [$user['id']]);

// Mağaza seç
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid = (int)($_POST['magaza_id'] ?? 0);
    $m   = DB::row("SELECT * FROM magazalar WHERE id=? AND kullanici_id=?", [$mid, $user['id']]);
    if ($m) { $_SESSION['magaza'] = $m; header('Location: index.php'); exit; }
    else $error = "Geçersiz mağaza.";
}

// Mağaza ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'yeni_magaza') {
    $ad  = trim($_POST['magaza_adi']  ?? '');
    $sid = trim($_POST['ty_seller_id']?? '');
    $key = trim($_POST['ty_api_key']  ?? '');
    $sec = trim($_POST['ty_api_secret']??'');
    if (!$ad) $error = "Mağaza adı boş olamaz.";
    else {
        DB::exec("INSERT INTO magazalar (kullanici_id,magaza_adi,ty_seller_id,ty_api_key,ty_api_secret) VALUES (?,?,?,?,?)",
            [$user['id'], $ad, $sid, $key, $sec]);
        $mid = DB::lastId();
        $m   = DB::row("SELECT * FROM magazalar WHERE id=?", [$mid]);
        $_SESSION['magaza'] = $m;
        header('Location: index.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mağaza Seç</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0f1117;color:#e8eaf6;font-family:'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.box{background:#1e2035;border:1px solid #2e3150;border-radius:16px;padding:32px;width:100%;max-width:500px;}
h2{font-size:18px;margin-bottom:6px;color:#f27a1a;}
p{color:#9099c4;font-size:13px;margin-bottom:24px;}
.magaza-kart{background:#252840;border:1px solid #2e3150;border-radius:10px;padding:16px 20px;cursor:pointer;margin-bottom:10px;display:flex;align-items:center;gap:12px;transition:.2s;}
.magaza-kart:hover{border-color:#f27a1a;background:#2a2e4a;}
.magaza-kart .icon{font-size:24px;}
.magaza-kart .info strong{display:block;font-size:14px;}
.magaza-kart .info span{font-size:12px;color:#9099c4;}
.divider{border:none;border-top:1px solid #2e3150;margin:20px 0;}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:12px;color:#9099c4;margin-bottom:5px;}
.form-group input{width:100%;background:#1a1d2e;border:1px solid #2e3150;color:#e8eaf6;padding:9px 12px;border-radius:8px;font-size:13px;outline:none;}
.form-group input:focus{border-color:#f27a1a;}
.btn{width:100%;padding:11px;background:#f27a1a;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:#e74c3c;}
.logout{text-align:right;margin-bottom:16px;font-size:12px;color:#9099c4;}
.logout a{color:#f27a1a;text-decoration:none;}
.section-title{font-size:12px;text-transform:uppercase;color:#9099c4;letter-spacing:.5px;margin-bottom:12px;}
</style>
</head>
<body>
<div class="box">
    <div class="logout">👤 <?= htmlspecialchars($user['ad'] ?: $user['email']) ?> · <a href="logout.php">Çıkış</a></div>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($magazalar): ?>
    <h2>🏪 Mağaza Seçin</h2>
    <p>Analiz yapmak istediğiniz mağazayı seçin.</p>
    <form method="POST">
        <?php foreach ($magazalar as $m): ?>
        <button type="submit" name="magaza_id" value="<?= $m['id'] ?>" class="magaza-kart" style="width:100%;text-align:left;color:inherit;background:#252840;border:1px solid #2e3150;">
            <div class="icon">🛒</div>
            <div class="info">
                <strong><?= htmlspecialchars($m['magaza_adi']) ?></strong>
                <span><?= $m['ty_seller_id'] ? "Seller ID: ".$m['ty_seller_id'] : "API bilgisi girilmemiş" ?></span>
            </div>
        </button>
        <?php endforeach; ?>
    </form>
    <hr class="divider">
    <?php endif; ?>

    <div class="section-title"><?= $magazalar ? '+ Yeni Mağaza Ekle' : '🏪 İlk Mağazanızı Oluşturun' ?></div>
    <form method="POST">
        <input type="hidden" name="form" value="yeni_magaza">
        <div class="form-group"><label>Mağaza Adı *</label><input type="text" name="magaza_adi" required placeholder="Örn: Defterya Ana Mağaza"></div>
        <div class="form-group"><label>Trendyol Seller ID</label><input type="text" name="ty_seller_id" placeholder="Satıcı ID numarası"></div>
        <div class="form-group"><label>API Key</label><input type="text" name="ty_api_key" placeholder="Entegrasyon bilgilerinden"></div>
        <div class="form-group"><label>API Secret</label><input type="password" name="ty_api_secret" placeholder="Entegrasyon bilgilerinden"></div>
        <button type="submit" class="btn">✅ Mağaza Oluştur ve Devam Et</button>
    </form>
</div>
</body>
</html>
