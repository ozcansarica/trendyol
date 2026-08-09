<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireAdmin();

$user    = authUser();
$action  = $_GET['action'] ?? 'overview';
$message = ''; $error = '';

// ---- Aksiyonlar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Kullanıcı aktif/pasif toggle
    if ($act === 'toggle_user') {
        $uid = (int)$_POST['uid'];
        if ($uid !== $user['id']) { // admini kendini pasif yapmasın
            DB::exec("UPDATE kullanicilar SET aktif = 1-aktif WHERE id=?", [$uid]);
            $message = "Kullanıcı durumu güncellendi.";
        }
    }
    // Kullanıcı rolü değiştir
    elseif ($act === 'set_rol') {
        $uid = (int)$_POST['uid'];
        $rol = in_array($_POST['rol'], ['admin','uye']) ? $_POST['rol'] : 'uye';
        if ($uid !== $user['id']) {
            DB::exec("UPDATE kullanicilar SET rol=? WHERE id=?", [$rol, $uid]);
            $message = "Kullanıcı rolü güncellendi.";
        }
    }
    // Mağaza sil
    elseif ($act === 'del_magaza') {
        $mid = (int)$_POST['mid'];
        DB::exec("DELETE FROM magazalar WHERE id=?", [$mid]);
        $message = "Mağaza silindi.";
    }
    // Mağaza API bilgilerini güncelle
    elseif ($act === 'update_magaza') {
        $mid = (int)$_POST['mid'];
        DB::exec("UPDATE magazalar SET magaza_adi=?,ty_seller_id=?,ty_api_key=?,ty_api_secret=? WHERE id=?", [
            trim($_POST['magaza_adi']??''),
            trim($_POST['ty_seller_id']??''),
            trim($_POST['ty_api_key']??''),
            trim($_POST['ty_api_secret']??''),
            $mid
        ]);
        $message = "Mağaza bilgileri güncellendi.";
    }
    // Kullanıcı sil
    elseif ($act === 'del_user') {
        $uid = (int)$_POST['uid'];
        if ($uid !== $user['id']) {
            DB::exec("DELETE FROM kullanicilar WHERE id=?", [$uid]);
            $message = "Kullanıcı silindi.";
        } else $error = "Kendinizi silemezsiniz.";
    }
}

// ---- Veri ----
$kullanicilar = DB::rows("SELECT k.*, COUNT(m.id) as magaza_sayisi
    FROM kullanicilar k LEFT JOIN magazalar m ON k.id=m.kullanici_id
    GROUP BY k.id ORDER BY k.kayit_tarihi DESC");

$magazalar = DB::rows("SELECT m.*, k.email, k.ad_soyad,
    (SELECT COUNT(*) FROM siparisler WHERE magaza_id=m.id) as siparis_sayisi,
    (SELECT COUNT(*) FROM trendyol_urunler WHERE magaza_id=m.id) as urun_sayisi
    FROM magazalar m
    JOIN kullanicilar k ON m.kullanici_id=k.id
    ORDER BY m.olusturma DESC");

$toplamSiparis = (int)DB::scalar("SELECT COUNT(*) FROM siparisler");
$toplamUrun    = (int)DB::scalar("SELECT COUNT(*) FROM trendyol_urunler");

function fmt2($n,$d=2){return number_format((float)$n,$d,',','.');}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel</title>
<style>
:root{--primary:#f27a1a;--bg:#0f1117;--bg2:#1a1d2e;--bg3:#252840;--card:#1e2035;--border:#2e3150;--text:#e8eaf6;--text2:#9099c4;--green:#2ecc71;--red:#e74c3c;--blue:#3498db;--yellow:#f1c40f;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;font-size:14px;}
.sidebar{position:fixed;left:0;top:0;width:200px;height:100vh;background:var(--bg2);border-right:1px solid var(--border);padding:20px 0;}
.sidebar .logo{padding:10px 20px 20px;font-size:15px;font-weight:700;color:var(--primary);border-bottom:1px solid var(--border);margin-bottom:12px;}
.sidebar .logo span{display:block;font-size:10px;color:var(--text2);font-weight:400;margin-top:2px;}
.sidebar a{display:flex;align-items:center;gap:8px;padding:9px 20px;color:var(--text2);text-decoration:none;font-size:13px;transition:.2s;}
.sidebar a:hover,.sidebar a.active{background:rgba(242,122,26,.15);color:var(--primary);}
.main{margin-left:200px;padding:25px;}
.page-title{font-size:20px;font-weight:700;margin-bottom:20px;}
.page-title span{color:var(--primary);}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px;}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;}
.kpi-label{font-size:11px;color:var(--text2);text-transform:uppercase;margin-bottom:6px;}
.kpi-value{font-size:22px;font-weight:700;}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:18px;}
.card-title{font-size:14px;font-weight:600;margin-bottom:14px;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:9px 12px;font-size:11px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);background:var(--bg3);}
td{padding:9px 12px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle;}
tr:hover td{background:rgba(255,255,255,.02);}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-green{background:rgba(46,204,113,.15);color:var(--green);}
.badge-red{background:rgba(231,76,60,.15);color:var(--red);}
.badge-blue{background:rgba(52,152,219,.15);color:var(--blue);}
.badge-orange{background:rgba(242,122,26,.15);color:var(--primary);}
.badge-gray{background:rgba(144,153,196,.12);color:var(--text2);}
.btn{padding:6px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;transition:.2s;text-decoration:none;display:inline-block;}
.btn-sm{padding:3px 9px;font-size:11px;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-danger{background:rgba(231,76,60,.2);color:var(--red);border:1px solid rgba(231,76,60,.3);}
.btn-success{background:rgba(46,204,113,.2);color:var(--green);border:1px solid rgba(46,204,113,.3);}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.alert-success{background:rgba(46,204,113,.15);border:1px solid rgba(46,204,113,.3);color:var(--green);}
.alert-danger{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:var(--red);}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;width:420px;max-width:92vw;}
.form-group{margin-bottom:12px;}
.form-group label{display:block;font-size:12px;color:var(--text2);margin-bottom:5px;}
.form-group input,.form-group select{width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:8px 11px;border-radius:7px;font-size:13px;outline:none;}
.form-group input:focus{border-color:var(--primary);}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo">⚙️ Admin Panel<span>Trendyol Analiz</span></div>
    <a href="admin.php" class="active">📊 Genel Bakış</a>
    <a href="index.php">🏠 Uygulamaya Dön</a>
    <a href="logout.php" style="margin-top:auto;position:absolute;bottom:20px">🚪 Çıkış</a>
</div>
<div class="main">
    <div class="page-title">⚙️ <span>Admin Paneli</span></div>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">Toplam Kullanıcı</div><div class="kpi-value"><?= count($kullanicilar) ?></div></div>
        <div class="kpi"><div class="kpi-label">Toplam Mağaza</div><div class="kpi-value"><?= count($magazalar) ?></div></div>
        <div class="kpi"><div class="kpi-label">Toplam Sipariş</div><div class="kpi-value"><?= number_format($toplamSiparis,0,',','.') ?></div></div>
        <div class="kpi"><div class="kpi-label">Toplam API Ürünü</div><div class="kpi-value"><?= number_format($toplamUrun,0,',','.') ?></div></div>
    </div>

    <!-- Kullanıcılar -->
    <div class="card">
        <div class="card-title">👥 Kullanıcılar (<?= count($kullanicilar) ?>)</div>
        <div style="overflow-x:auto"><table>
        <thead><tr><th>#</th><th>Ad Soyad / E-posta</th><th>Rol</th><th>Mağaza</th><th>Kayıt Tarihi</th><th>Durum</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($kullanicilar as $k): ?>
        <tr>
            <td style="color:var(--text2)"><?= $k['id'] ?></td>
            <td>
                <div style="font-weight:500"><?= htmlspecialchars($k['ad_soyad'] ?: '—') ?></div>
                <div style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($k['email']) ?></div>
            </td>
            <td>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="act" value="set_rol">
                    <input type="hidden" name="uid" value="<?= $k['id'] ?>">
                    <select name="rol" onchange="this.form.submit()" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:3px 6px;border-radius:5px;font-size:11px;cursor:pointer" <?= $k['id']==$user['id']?'disabled':'' ?>>
                        <option value="uye" <?= $k['rol']==='uye'?'selected':'' ?>>Üye</option>
                        <option value="admin" <?= $k['rol']==='admin'?'selected':'' ?>>Admin</option>
                    </select>
                </form>
            </td>
            <td><span class="badge badge-blue"><?= $k['magaza_sayisi'] ?> mağaza</span></td>
            <td style="font-size:11px;color:var(--text2)"><?= $k['kayit_tarihi'] ?></td>
            <td>
                <span class="badge <?= $k['aktif']?'badge-green':'badge-red' ?>"><?= $k['aktif']?'Aktif':'Pasif' ?></span>
            </td>
            <td style="display:flex;gap:5px">
                <?php if ($k['id'] != $user['id']): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="act" value="toggle_user">
                    <input type="hidden" name="uid" value="<?= $k['id'] ?>">
                    <button class="btn btn-sm <?= $k['aktif']?'btn-danger':'btn-success' ?>"><?= $k['aktif']?'Pasif Et':'Aktif Et' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Bu kullanıcıyı ve tüm mağaza verilerini sil?')">
                    <input type="hidden" name="act" value="del_user">
                    <input type="hidden" name="uid" value="<?= $k['id'] ?>">
                    <button class="btn btn-sm btn-danger">🗑</button>
                </form>
                <?php else: ?>
                <span style="font-size:11px;color:var(--text2)">(siz)</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>

    <!-- Mağazalar -->
    <div class="card">
        <div class="card-title">🏪 Mağazalar (<?= count($magazalar) ?>)</div>
        <div style="overflow-x:auto"><table>
        <thead><tr><th>#</th><th>Mağaza</th><th>Sahibi</th><th>Seller ID</th><th>API</th><th>Sipariş</th><th>Ürün</th><th>Oluşturma</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($magazalar as $m): ?>
        <tr>
            <td style="color:var(--text2)"><?= $m['id'] ?></td>
            <td><strong><?= htmlspecialchars($m['magaza_adi']) ?></strong></td>
            <td style="font-size:12px;color:var(--text2)">
                <?= htmlspecialchars($m['ad_soyad']?:$m['email']) ?>
                <div style="font-size:10px"><?= htmlspecialchars($m['email']) ?></div>
            </td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($m['ty_seller_id']??'—') ?></td>
            <td><?= ($m['ty_api_key'] && $m['ty_api_secret']) ? '<span class="badge badge-green">✓ Var</span>' : '<span class="badge badge-red">✗ Yok</span>' ?></td>
            <td><?= number_format($m['siparis_sayisi'],0,',','.') ?></td>
            <td><?= number_format($m['urun_sayisi'],0,',','.') ?></td>
            <td style="font-size:11px;color:var(--text2)"><?= substr($m['olusturma'],0,10) ?></td>
            <td style="display:flex;gap:5px">
                <button class="btn btn-sm btn-primary" onclick="editMagaza(<?= htmlspecialchars(json_encode($m)) ?>)">✏️</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Mağaza ve tüm veriler silinecek. Emin misiniz?')">
                    <input type="hidden" name="act" value="del_magaza">
                    <input type="hidden" name="mid" value="<?= $m['id'] ?>">
                    <button class="btn btn-sm btn-danger">🗑</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>

<!-- Mağaza Düzenle Modal -->
<div id="editModal" class="modal" style="display:none">
<div class="modal-box">
    <h3 style="margin-bottom:16px">✏️ Mağaza Düzenle</h3>
    <form method="POST">
        <input type="hidden" name="act" value="update_magaza">
        <input type="hidden" name="mid" id="edit_mid">
        <div class="form-group"><label>Mağaza Adı</label><input type="text" name="magaza_adi" id="edit_ad" required></div>
        <div class="form-group"><label>Seller ID</label><input type="text" name="ty_seller_id" id="edit_sid"></div>
        <div class="form-group"><label>API Key</label><input type="text" name="ty_api_key" id="edit_key"></div>
        <div class="form-group"><label>API Secret</label><input type="password" name="ty_api_secret" id="edit_sec" placeholder="Değiştirmek için girin"></div>
        <div style="display:flex;gap:8px;margin-top:14px">
            <button type="submit" class="btn btn-primary">💾 Kaydet</button>
            <button type="button" class="btn" style="background:var(--bg3);color:var(--text2)" onclick="document.getElementById('editModal').style.display='none'">İptal</button>
        </div>
    </form>
</div>
</div>

<script>
function editMagaza(m) {
    document.getElementById('edit_mid').value = m.id;
    document.getElementById('edit_ad').value  = m.magaza_adi;
    document.getElementById('edit_sid').value = m.ty_seller_id||'';
    document.getElementById('edit_key').value = m.ty_api_key||'';
    document.getElementById('edit_sec').value = '';
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>
