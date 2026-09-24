<?php
// ============================================================
//  Ortak arayüz iskeleti: admin paneli, kullanıcı paneli (Hesabım)
//  ve giriş/kurulum kutusu aynı görünümü buradan alır.
// ============================================================
require_once __DIR__ . '/sistem.php';

function ortakStil(): string {
    return <<<'CSS'
:root{--primary:#f27a1a;--primary-dark:#d4610a;--bg:#0f1117;--bg2:#1a1d2e;--bg3:#252840;--card:#1e2035;--border:#2e3150;--text:#e8eaf6;--text2:#9099c4;--green:#2ecc71;--red:#e74c3c;--blue:#3498db;--yellow:#f1c40f;--fb:#1877f2;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;font-size:14px;}
a{color:var(--primary);}
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg2);border-right:1px solid var(--border);padding:20px 0;overflow-y:auto;z-index:100;display:flex;flex-direction:column;}
.sidebar .logo{padding:10px 20px 20px;font-size:15px;font-weight:700;color:var(--primary);border-bottom:1px solid var(--border);margin-bottom:12px;}
.sidebar .logo span{display:block;font-size:11px;color:var(--text2);font-weight:400;margin-top:3px;}
.sidebar a{display:flex;align-items:center;gap:9px;padding:10px 20px;color:var(--text2);text-decoration:none;font-size:13px;transition:.2s;}
.sidebar a:hover,.sidebar a.active{background:rgba(242,122,26,.15);color:var(--primary);border-right:3px solid var(--primary);}
.sidebar .sep{padding:10px 20px 4px;font-size:10px;text-transform:uppercase;color:var(--text2);opacity:.6;letter-spacing:1px;}
.sidebar .alt{margin-top:auto;border-top:1px solid var(--border);padding-top:8px;}
.main{margin-left:220px;padding:26px;max-width:1400px;}
.hamburger{display:none;position:fixed;top:12px;left:12px;z-index:200;background:var(--bg2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:6px 10px;font-size:18px;cursor:pointer;}
.page-title{font-size:21px;font-weight:700;margin-bottom:20px;}.page-title span{color:var(--primary);}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:20px;}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;}
.kpi-label{font-size:11px;color:var(--text2);text-transform:uppercase;margin-bottom:6px;letter-spacing:.4px;}
.kpi-value{font-size:22px;font-weight:700;}.kpi-sub{font-size:11px;color:var(--text2);margin-top:4px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px;}
.card-title{font-size:15px;font-weight:600;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.tablo-kap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:9px 11px;font-size:11px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);background:var(--bg3);white-space:nowrap;}
td{padding:9px 11px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle;}
tr:hover td{background:rgba(255,255,255,.02);}
td form{display:inline;}
.islem{white-space:nowrap;}
.muted{color:var(--text2);font-size:12px;}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;}
.badge-green{background:rgba(46,204,113,.15);color:var(--green);}.badge-red{background:rgba(231,76,60,.15);color:var(--red);}
.badge-blue{background:rgba(52,152,219,.15);color:var(--blue);}.badge-orange{background:rgba(242,122,26,.15);color:var(--primary);}
.badge-gray{background:rgba(144,153,196,.12);color:var(--text2);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit;}
.btn:hover{border-color:var(--primary);}
.btn-sm{padding:4px 9px;font-size:12px;}
.btn-primary{background:var(--primary);border-color:var(--primary);color:#fff;}.btn-primary:hover{background:var(--primary-dark);}
.btn-danger{background:rgba(231,76,60,.15);color:var(--red);border-color:rgba(231,76,60,.35);}
.btn-success{background:rgba(46,204,113,.15);color:var(--green);border-color:rgba(46,204,113,.35);}
.btn-fb{background:var(--fb);border-color:var(--fb);color:#fff;}
.btn-blok{width:100%;justify-content:center;padding:12px;font-weight:600;}
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:16px;line-height:1.5;}
.alert-success{background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.35);color:var(--green);}
.alert-danger{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.35);color:#ff8a7d;}
.alert-warning{background:rgba(241,196,15,.1);border:1px solid rgba(241,196,15,.3);color:var(--yellow);}
.alert-info{background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.3);color:#8fc7ee;}
code{background:var(--bg3);padding:1px 6px;border-radius:4px;color:var(--text);font-size:12px;word-break:break-all;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:4px 16px;}
.form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.form-group label{font-size:12px;color:var(--text2);font-weight:500;}
.form-group .ipucu{font-size:11px;color:var(--text2);opacity:.85;}
input[type=text],input[type=email],input[type=password],input[type=url],input[type=number],select,textarea{background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;font-size:13px;outline:none;font-family:inherit;width:100%;}
input:focus,select:focus,textarea:focus{border-color:var(--primary);}
.onay{display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:12px;}
.sekme{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px;flex-wrap:wrap;}
.sekme a{padding:9px 14px;color:var(--text2);text-decoration:none;border-bottom:2px solid transparent;font-size:13px;}
.sekme a.active{color:var(--primary);border-bottom-color:var(--primary);}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;padding:16px;}
.modal.acik{display:flex;}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px;width:480px;max-width:100%;max-height:92vh;overflow-y:auto;}
.modal-box h3{margin-bottom:16px;font-size:16px;}
.bos{color:var(--text2);text-align:center;padding:22px;}
.kutu-govde{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.kutu{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:34px;width:100%;max-width:440px;}
.kutu .logo{text-align:center;margin-bottom:26px;}
.kutu .logo h1{font-size:22px;color:var(--primary);}
.kutu .logo p{color:var(--text2);font-size:13px;margin-top:4px;}
@media(max-width:820px){
  .hamburger{display:block;}
  .sidebar{transform:translateX(-100%);transition:transform .2s;}
  .sidebar.acik{transform:none;}
  .main{margin-left:0;padding:60px 14px 20px;}
}
CSS;
}

/**
 * Panel sayfasını başlatır.
 * $menu: [[href, ikon, etiket, anahtar] | ['sep', 'Başlık']]
 */
function panelBasla(string $baslik, string $altBaslik, array $menu, string $aktif): void {
    $kullanici = authUser();
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($baslik) ?> — <?= h(ayar('site_adi')) ?></title>
<style><?= ortakStil() ?></style>
</head>
<body>
<button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('acik')" aria-label="Menü">☰</button>
<nav class="sidebar" id="sidebar">
    <div class="logo"><?= h($baslik) ?><span><?= h($altBaslik) ?></span></div>
    <?php foreach ($menu as $m): ?>
        <?php if ($m[0] === 'sep'): ?><div class="sep"><?= h($m[1]) ?></div>
        <?php else: ?><a href="<?= h($m[0]) ?>" class="<?= ($m[3] ?? '') === $aktif ? 'active' : '' ?>"><span><?= $m[1] ?></span> <?= h($m[2]) ?></a><?php endif; ?>
    <?php endforeach; ?>
    <div class="alt">
        <div style="padding:6px 20px;font-size:11px;color:var(--text2)">👤 <?= h($kullanici['ad'] ?: $kullanici['email']) ?></div>
        <a href="logout.php"><span>🚪</span> Çıkış Yap</a>
    </div>
</nav>
<main class="main">
<?= bildirimGoster() ?>
<?php
}

function panelBitir(): void {
    ?>
</main>
<script>
// Modal yardımcıları: data-modal="id" açar, .modal dışına / [data-kapat] tıklayınca kapanır
document.addEventListener('click', e => {
    const ac = e.target.closest('[data-modal]');
    if (ac) { document.getElementById(ac.dataset.modal).classList.add('acik'); return; }
    if (e.target.classList.contains('modal') || e.target.closest('[data-kapat]')) {
        e.target.closest('.modal')?.classList.remove('acik');
    }
});
</script>
</body>
</html>
<?php
}

function kutuBasla(string $baslik): void {
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($baslik) ?></title>
<style><?= ortakStil() ?></style>
</head>
<body>
<div class="kutu-govde"><div class="kutu">
    <div class="logo"><h1>🛒 <?= h(ayar('site_adi')) ?></h1><p><?= h($baslik) ?></p></div>
    <?= bildirimGoster() ?>
<?php
}

function kutuBitir(): void {
    echo "</div></div>\n</body>\n</html>\n";
}

function tarihTr(?string $t, bool $saat = true): string {
    if (!$t) return '—';
    return date($saat ? 'd.m.Y H:i' : 'd.m.Y', strtotime($t));
}
function sayiTr($n, int $ondalik = 0): string {
    return number_format((float)$n, $ondalik, ',', '.');
}

/** Kullanıcı paneli menüsü (Hesabım, Sosyal Paylaşım). */
function kullaniciMenusu(): array {
    $m = [
        ['sep', 'Uygulama'],
        ['index.php', '📊', 'Analiz Paneli', 'analiz'],
        ['sosyal.php', '📣', 'Sosyal Paylaşım', 'sosyal'],
        ['sep', 'Hesabım'],
        ['hesabim.php', '👤', 'Profilim', 'profil'],
        ['hesabim.php?s=magazalar', '🏪', 'Mağazalarım', 'magazalar'],
        ['hesabim.php?s=sifre', '🔑', 'Şifre Değiştir', 'sifre'],
    ];
    if (isAdmin()) {
        $m[] = ['sep', 'Yönetim'];
        $m[] = ['admin.php', '⚙️', 'Admin Paneli', 'admin'];
    }
    return $m;
}
