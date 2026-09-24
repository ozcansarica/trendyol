<?php
// ============================================================
//  Kullanıcı paneli (Hesabım)
//   ?s=profil     profil bilgileri + özet
//   ?s=magazalar  kendi mağazaları: ekle / düzenle / API anahtarları / sil
//   ?s=sifre      şifre değiştir
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/arayuz.php';
require_once __DIR__ . '/SosyalMedya.php';
requireLogin();
tumSemayiKur();
webCronTetikle();

$uid   = (int)authUser()['id'];
$bolum = in_array($_GET['s'] ?? '', ['magazalar', 'sifre'], true) ? $_GET['s'] : 'profil';

// ---- Aksiyonlar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $geri = 'hesabim.php' . ($bolum !== 'profil' ? "?s=$bolum" : '');
    if (!csrfDogrula()) yonlendir($geri, 'hata', 'Oturum doğrulaması başarısız, tekrar deneyin.');
    $act = $_POST['act'] ?? '';
    $mid = (int)($_POST['id'] ?? 0);

    if ($act === 'profil') {
        $ad    = trim($_POST['ad_soyad'] ?? '');
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) yonlendir($geri, 'hata', 'Geçerli bir e-posta girin.');
        if (DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE email=? AND id<>?", [$email, $uid])) {
            yonlendir($geri, 'hata', 'Bu e-posta başka bir hesapta kayıtlı.');
        }
        DB::exec("UPDATE kullanicilar SET ad_soyad=?, email=? WHERE id=?", [$ad, $email, $uid]);
        yonlendir($geri, 'basari', 'Profil güncellendi.');
    }
    elseif ($act === 'sifre') {
        $k = DB::row("SELECT sifre FROM kullanicilar WHERE id=?", [$uid]);
        if (!password_verify((string)($_POST['mevcut'] ?? ''), $k['sifre'])) yonlendir($geri, 'hata', 'Mevcut şifre yanlış.');
        if ($e = sifreDogrula((string)($_POST['yeni'] ?? ''), (string)($_POST['yeni2'] ?? ''))) yonlendir($geri, 'hata', $e);
        DB::exec("UPDATE kullanicilar SET sifre=? WHERE id=?", [password_hash($_POST['yeni'], PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        yonlendir($geri, 'basari', '🔑 Şifreniz değiştirildi.');
    }
    elseif ($act === 'magaza_kaydet') {
        // Düzenlemede mağazanın bu kullanıcıya ait olduğu doğrulanır
        if ($mid && !DB::scalar("SELECT COUNT(*) FROM magazalar WHERE id=? AND kullanici_id=?", [$mid, $uid])) {
            yonlendir($geri, 'hata', 'Mağaza bulunamadı.');
        }
        try {
            $id = magazaFormundanKaydet($_POST, $uid, $mid ?: null);
        } catch (InvalidArgumentException $e) {
            yonlendir($geri, 'hata', $e->getMessage());
        }
        if (empty($_SESSION['magaza'])) {
            $_SESSION['magaza'] = DB::row("SELECT * FROM magazalar WHERE id=?", [$id]);
        }
        yonlendir($geri, 'basari', $mid ? 'Mağaza güncellendi.' : '🏪 Mağaza eklendi.');
    }
    elseif ($act === 'magaza_sec') {
        $m = DB::row("SELECT * FROM magazalar WHERE id=? AND kullanici_id=? AND aktif=1", [$mid, $uid]);
        if (!$m) yonlendir($geri, 'hata', 'Mağaza bulunamadı ya da pasif.');
        $_SESSION['magaza'] = $m;
        yonlendir('index.php');
    }
    elseif ($act === 'magaza_durum') {
        DB::exec("UPDATE magazalar SET aktif=1-aktif WHERE id=? AND kullanici_id=?", [$mid, $uid]);
        yonlendir($geri, 'basari', 'Mağaza durumu güncellendi.');
    }
    elseif ($act === 'magaza_sil') {
        $m = DB::row("SELECT magaza_adi FROM magazalar WHERE id=? AND kullanici_id=?", [$mid, $uid]);
        if (!$m) yonlendir($geri, 'hata', 'Mağaza bulunamadı.');
        if (trim($_POST['onay'] ?? '') !== $m['magaza_adi']) {
            yonlendir($geri, 'hata', 'Silmek için mağaza adını birebir yazmalısınız.');
        }
        DB::exec("DELETE FROM magazalar WHERE id=? AND kullanici_id=?", [$mid, $uid]);
        yonlendir($geri, 'basari', 'Mağaza ve tüm verileri silindi.');
    }
    yonlendir($geri, 'hata', 'Bilinmeyen işlem.');
}

// ---- Veriler ----
$ben = DB::row("SELECT * FROM kullanicilar WHERE id=?", [$uid]);
$magazalar = DB::rows("SELECT m.*,
        (SELECT COUNT(*) FROM siparisler s WHERE s.magaza_id=m.id) AS siparis_sayisi,
        (SELECT COUNT(*) FROM trendyol_urunler u WHERE u.magaza_id=m.id) AS urun_sayisi
    FROM magazalar m WHERE m.kullanici_id=? ORDER BY m.magaza_adi", [$uid]);
$sosyalHesap = (int)DB::scalar("SELECT COUNT(*) FROM sosyal_hesaplar WHERE kullanici_id=?", [$uid]);
$bekleyen    = (int)DB::scalar("SELECT COUNT(*) FROM sosyal_paylasimlar WHERE kullanici_id=? AND durum='bekliyor'", [$uid]);
$yayinlanan  = (int)DB::scalar("SELECT COUNT(*) FROM sosyal_paylasimlar WHERE kullanici_id=? AND durum='gonderildi'", [$uid]);
$seciliMagaza = (int)(authMagaza()['id'] ?? 0);

$basliklar = ['profil' => 'profil', 'magazalar' => 'magazalar', 'sifre' => 'sifre'];
panelBasla('👤 Hesabım', ayar('site_adi'), kullaniciMenusu(), $basliklar[$bolum]);
?>

<?php if ($bolum === 'profil'): ?>
<div class="page-title">👤 <span>Profilim</span></div>
<div class="kpi-grid">
    <div class="kpi"><div class="kpi-label">Mağaza</div><div class="kpi-value"><?= count($magazalar) ?></div></div>
    <div class="kpi"><div class="kpi-label">Sosyal Hesap</div><div class="kpi-value"><?= $sosyalHesap ?></div></div>
    <div class="kpi"><div class="kpi-label">Bekleyen Paylaşım</div><div class="kpi-value"><?= $bekleyen ?></div></div>
    <div class="kpi"><div class="kpi-label">Yayınlanan Paylaşım</div><div class="kpi-value"><?= $yayinlanan ?></div></div>
</div>
<div class="card" style="max-width:640px">
    <div class="card-title"><span>Profil Bilgileri</span>
        <span class="badge <?= $ben['rol'] === 'admin' ? 'badge-orange' : 'badge-blue' ?>"><?= $ben['rol'] === 'admin' ? 'Admin' : 'Üye' ?></span></div>
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="profil">
        <div class="form-group"><label>Ad Soyad</label><input type="text" name="ad_soyad" value="<?= h($ben['ad_soyad']) ?>"></div>
        <div class="form-group"><label>E-posta</label><input type="email" name="email" value="<?= h($ben['email']) ?>" required></div>
        <p class="muted" style="margin-bottom:12px">Kayıt: <?= tarihTr($ben['kayit_tarihi']) ?> · Son giriş: <?= tarihTr($ben['son_giris'] ?? null) ?></p>
        <button class="btn btn-primary">💾 Kaydet</button>
    </form>
</div>

<?php elseif ($bolum === 'sifre'): ?>
<div class="page-title">🔑 <span>Şifre Değiştir</span></div>
<div class="card" style="max-width:480px">
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="sifre">
        <div class="form-group"><label>Mevcut şifre</label><input type="password" name="mevcut" required autocomplete="current-password"></div>
        <div class="form-group"><label>Yeni şifre</label><input type="password" name="yeni" required minlength="8" autocomplete="new-password" placeholder="En az 8 karakter"></div>
        <div class="form-group"><label>Yeni şifre tekrar</label><input type="password" name="yeni2" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary">Şifreyi Değiştir</button>
    </form>
</div>

<?php else: ?>
<div class="page-title">🏪 <span>Mağazalarım</span></div>
<div class="card">
    <div class="card-title"><span>Mağazalar (<?= count($magazalar) ?>)</span>
        <button class="btn btn-primary" data-modal="magazaModal" onclick="magazaFormu(null)">➕ Mağaza Ekle</button></div>
    <?php if (!$magazalar): ?>
        <div class="bos">Henüz mağazanız yok. "Mağaza Ekle" ile Trendyol mağazanızı ve API bilgilerinizi girin.</div>
    <?php else: ?>
    <div class="tablo-kap"><table>
        <thead><tr><th>Mağaza</th><th>Seller ID</th><th>Trendyol API</th><th>AI Anahtarı</th><th>Sipariş</th><th>Ürün</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($magazalar as $m): ?>
        <tr>
            <td><strong><?= h($m['magaza_adi']) ?></strong><?= (int)$m['id'] === $seciliMagaza ? ' <span class="badge badge-orange">seçili</span>' : '' ?></td>
            <td><code><?= h($m['ty_seller_id'] ?: '—') ?></code></td>
            <td><?= ($m['ty_api_key'] && $m['ty_api_secret']) ? '<span class="badge badge-green">✓ Tanımlı</span>' : '<span class="badge badge-red">✗ Eksik</span>' ?></td>
            <td><?= !empty($m['anthropic_api_key']) ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-gray">—</span>' ?></td>
            <td><?= sayiTr($m['siparis_sayisi']) ?></td>
            <td><?= sayiTr($m['urun_sayisi']) ?></td>
            <td><span class="badge <?= $m['aktif'] ? 'badge-green' : 'badge-gray' ?>"><?= $m['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="islem">
                <?php if ($m['aktif']): ?>
                <form method="post"><?= csrfAlan() ?><input type="hidden" name="act" value="magaza_sec"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-primary">Aç →</button></form>
                <?php endif; ?>
                <button class="btn btn-sm" data-modal="magazaModal" onclick='magazaFormu(<?= json_encode([
                    'id' => (int)$m['id'], 'magaza_adi' => $m['magaza_adi'], 'ty_seller_id' => $m['ty_seller_id'],
                    'ty_api_key' => $m['ty_api_key'], 'secret' => maskele($m['ty_api_secret']), 'ant' => maskele($m['anthropic_api_key'] ?? ''),
                    'aktif' => (int)$m['aktif']], JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>✏️</button>
                <form method="post"><?= csrfAlan() ?><input type="hidden" name="act" value="magaza_durum"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm"><?= $m['aktif'] ? '⏸' : '▶' ?></button></form>
                <form method="post" onsubmit="const a=prompt('Mağaza, tüm sipariş/ürün/maliyet verileriyle kalıcı olarak silinecek.\nOnaylamak için mağaza adını yazın:');if(a===null)return false;this.onay.value=a;">
                    <?= csrfAlan() ?><input type="hidden" name="act" value="magaza_sil"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="onay">
                    <button class="btn btn-sm btn-danger">🗑</button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="modal" id="magazaModal"><div class="modal-box">
    <h3 id="magazaBaslik">Mağaza</h3>
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="magaza_kaydet"><input type="hidden" name="id" id="m_id">
        <div class="form-group"><label>Mağaza adı</label><input type="text" name="magaza_adi" id="m_ad" required></div>
        <div class="form-group"><label>Trendyol Seller ID</label><input type="text" name="ty_seller_id" id="m_sid"></div>
        <div class="form-group"><label>API Key</label><input type="text" name="ty_api_key" id="m_key" autocomplete="off"></div>
        <div class="form-group"><label>API Secret</label><input type="password" name="ty_api_secret" id="m_sec" autocomplete="new-password"><span class="ipucu" id="m_sec_ipucu"></span></div>
        <div class="form-group"><label>Anthropic API anahtarı (AI Analiz için, isteğe bağlı)</label><input type="password" name="anthropic_api_key" id="m_ant" autocomplete="new-password"><span class="ipucu" id="m_ant_ipucu"></span></div>
        <input type="hidden" name="aktif" id="m_aktif" value="1">
        <p class="ipucu muted" style="margin-bottom:12px">API bilgileri: Trendyol Satıcı Paneli → Hesap Bilgilerim → Entegrasyon Bilgileri.</p>
        <div style="display:flex;gap:8px"><button class="btn btn-primary">💾 Kaydet</button><button type="button" class="btn" data-kapat>İptal</button></div>
    </form>
</div></div>
<script>
function magazaFormu(m) {
    const $ = id => document.getElementById(id);
    $('magazaBaslik').textContent = m ? '✏️ Mağaza Düzenle' : '➕ Mağaza Ekle';
    $('m_id').value = m ? m.id : '';
    $('m_ad').value = m ? m.magaza_adi : '';
    $('m_sid').value = m ? (m.ty_seller_id || '') : '';
    $('m_key').value = m ? (m.ty_api_key || '') : '';
    $('m_sec').value = ''; $('m_ant').value = '';
    $('m_aktif').value = m ? m.aktif : 1;
    $('m_sec_ipucu').textContent = m && m.secret ? 'Kayıtlı: ' + m.secret + ' — değiştirmek için yeni değeri girin, boş bırakırsanız korunur.' : '';
    $('m_ant_ipucu').textContent = m && m.ant ? 'Kayıtlı: ' + m.ant + ' — boş bırakırsanız korunur.' : '';
}
</script>
<?php endif; ?>

<?php panelBitir();
