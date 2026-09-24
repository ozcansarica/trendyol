<?php
// ============================================================
//  Admin paneli — tüm sistem buradan yönetilir.
//   ?s=genel        özet, uyarılar, son kayıtlar
//   ?s=kullanicilar ekle / düzenle / rol / aktiflik / şifre sıfırla / sil
//   ?s=magazalar    tüm mağazalar: ekle / düzenle (sahip, API) / aktiflik / sil
//   ?s=sosyal       tüm sosyal hesaplar ve paylaşımlar
//   ?s=ayarlar      sistem ayarları (Facebook, site, kayıt, zamanlayıcı), sistem bilgisi
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/arayuz.php';
require_once __DIR__ . '/SosyalMedya.php';
requireAdmin();
tumSemayiKur();
webCronTetikle();

$ben    = authUser();
$benId  = (int)$ben['id'];
$bolum  = $_GET['s'] ?? 'genel';
if (!in_array($bolum, ['genel', 'kullanicilar', 'magazalar', 'sosyal', 'ayarlar'], true)) $bolum = 'genel';
$geri   = 'admin.php?s=' . $bolum . (isset($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '')
        . (isset($_GET['durum']) ? '&durum=' . urlencode($_GET['durum']) : '');

function aktifAdminSayisi(): int {
    return (int)DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE rol='admin' AND aktif=1");
}

// ============================================================
//  Aksiyonlar
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfDogrula()) yonlendir($geri, 'hata', 'Oturum doğrulaması başarısız, tekrar deneyin.');
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    switch ($act) {
        // ---------- Kullanıcılar ----------
        case 'kullanici_kaydet': {
            $email = mb_strtolower(trim($_POST['email'] ?? ''));
            $ad    = trim($_POST['ad_soyad'] ?? '');
            $rol   = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'uye';
            $aktif = (int)($_POST['aktif'] ?? 1) ? 1 : 0;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) yonlendir($geri, 'hata', 'Geçerli bir e-posta girin.');
            if (DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE email=? AND id<>?", [$email, $id])) {
                yonlendir($geri, 'hata', 'Bu e-posta başka bir hesapta kayıtlı.');
            }
            if ($id) {
                $eski = DB::row("SELECT rol, aktif FROM kullanicilar WHERE id=?", [$id]);
                if (!$eski) yonlendir($geri, 'hata', 'Kullanıcı bulunamadı.');
                if ($id === $benId && ($rol !== 'admin' || !$aktif)) {
                    yonlendir($geri, 'hata', 'Kendi admin yetkinizi kaldıramaz ya da hesabınızı pasif edemezsiniz.');
                }
                if ($eski['rol'] === 'admin' && (int)$eski['aktif'] && ($rol !== 'admin' || !$aktif) && aktifAdminSayisi() <= 1) {
                    yonlendir($geri, 'hata', 'Sistemde en az bir aktif admin kalmalı.');
                }
                DB::exec("UPDATE kullanicilar SET email=?, ad_soyad=?, rol=?, aktif=? WHERE id=?", [$email, $ad, $rol, $aktif, $id]);
                yonlendir($geri, 'basari', 'Kullanıcı güncellendi.');
            }
            if ($e = sifreDogrula((string)($_POST['sifre'] ?? ''), (string)($_POST['sifre'] ?? ''))) yonlendir($geri, 'hata', $e);
            DB::exec("INSERT INTO kullanicilar (email, sifre, ad_soyad, rol, aktif) VALUES (?,?,?,?,?)",
                     [$email, password_hash($_POST['sifre'], PASSWORD_DEFAULT), $ad, $rol, $aktif]);
            yonlendir($geri, 'basari', "👤 Kullanıcı oluşturuldu: $email");
        }
        case 'kullanici_sifre': {
            if ($e = sifreDogrula((string)($_POST['sifre'] ?? ''), (string)($_POST['sifre'] ?? ''))) yonlendir($geri, 'hata', $e);
            DB::exec("UPDATE kullanicilar SET sifre=? WHERE id=?", [password_hash($_POST['sifre'], PASSWORD_DEFAULT), $id]);
            DB::exec("DELETE FROM giris_denemeleri WHERE email=(SELECT email FROM kullanicilar WHERE id=?)", [$id]);
            yonlendir($geri, 'basari', '🔑 Şifre sıfırlandı; kullanıcıya yeni şifresini iletin.');
        }
        case 'kullanici_durum': {
            if ($id === $benId) yonlendir($geri, 'hata', 'Kendi hesabınızı pasif edemezsiniz.');
            $k = DB::row("SELECT rol, aktif FROM kullanicilar WHERE id=?", [$id]);
            if ($k && $k['rol'] === 'admin' && (int)$k['aktif'] && aktifAdminSayisi() <= 1) yonlendir($geri, 'hata', 'Sistemde en az bir aktif admin kalmalı.');
            DB::exec("UPDATE kullanicilar SET aktif=1-aktif WHERE id=?", [$id]);
            yonlendir($geri, 'basari', 'Kullanıcı durumu güncellendi.');
        }
        case 'kullanici_sil': {
            if ($id === $benId) yonlendir($geri, 'hata', 'Kendinizi silemezsiniz.');
            $k = DB::row("SELECT email, rol, aktif FROM kullanicilar WHERE id=?", [$id]);
            if (!$k) yonlendir($geri, 'hata', 'Kullanıcı bulunamadı.');
            if ($k['rol'] === 'admin' && (int)$k['aktif'] && aktifAdminSayisi() <= 1) yonlendir($geri, 'hata', 'Sistemde en az bir aktif admin kalmalı.');
            if (mb_strtolower(trim($_POST['onay'] ?? '')) !== mb_strtolower($k['email'])) {
                yonlendir($geri, 'hata', 'Silmek için kullanıcının e-postasını birebir yazmalısınız.');
            }
            DB::exec("DELETE FROM kullanicilar WHERE id=?", [$id]);
            yonlendir($geri, 'basari', 'Kullanıcı ve tüm verileri (mağazalar, sosyal hesaplar) silindi.');
        }

        // ---------- Mağazalar ----------
        case 'magaza_kaydet': {
            $sahip = (int)($_POST['kullanici_id'] ?? 0);
            if (!DB::scalar("SELECT COUNT(*) FROM kullanicilar WHERE id=?", [$sahip])) yonlendir($geri, 'hata', 'Geçerli bir mağaza sahibi seçin.');
            if ($id && !DB::scalar("SELECT COUNT(*) FROM magazalar WHERE id=?", [$id])) yonlendir($geri, 'hata', 'Mağaza bulunamadı.');
            try { magazaFormundanKaydet($_POST, $sahip, $id ?: null); }
            catch (InvalidArgumentException $e) { yonlendir($geri, 'hata', $e->getMessage()); }
            yonlendir($geri, 'basari', $id ? 'Mağaza güncellendi.' : '🏪 Mağaza eklendi.');
        }
        case 'magaza_durum':
            DB::exec("UPDATE magazalar SET aktif=1-aktif WHERE id=?", [$id]);
            yonlendir($geri, 'basari', 'Mağaza durumu güncellendi.');
        case 'magaza_sil': {
            $m = DB::row("SELECT magaza_adi FROM magazalar WHERE id=?", [$id]);
            if (!$m) yonlendir($geri, 'hata', 'Mağaza bulunamadı.');
            if (trim($_POST['onay'] ?? '') !== $m['magaza_adi']) yonlendir($geri, 'hata', 'Silmek için mağaza adını birebir yazmalısınız.');
            DB::exec("DELETE FROM magazalar WHERE id=?", [$id]);
            yonlendir($geri, 'basari', 'Mağaza ve tüm verileri silindi.');
        }

        // ---------- Sosyal ----------
        case 'hesap_durum':
            DB::exec("UPDATE sosyal_hesaplar SET durum=IF(durum='aktif','pasif','aktif'), guncelleme=? WHERE id=? AND durum IN ('aktif','pasif')", [simdi(), $id]);
            yonlendir($geri, 'basari', 'Sosyal hesap durumu güncellendi.');
        case 'hesap_sil':
            DB::exec("DELETE FROM sosyal_hesaplar WHERE id=?", [$id]);
            yonlendir($geri, 'basari', 'Sosyal hesap ve paylaşım kayıtları silindi.');
        case 'paylasim_iptal':
            DB::exec("UPDATE sosyal_paylasimlar SET durum='iptal' WHERE id=? AND durum='bekliyor'", [$id]);
            yonlendir($geri, 'basari', 'Paylaşım iptal edildi.');
        case 'paylasim_tekrar':
            DB::exec("UPDATE sosyal_paylasimlar SET durum='bekliyor', deneme_sayisi=0, hata_mesaji=NULL, planlanan_zaman=?
                      WHERE id=? AND durum IN ('hata','iptal')", [simdi(), $id]);
            yonlendir($geri, 'basari', 'Paylaşım yeniden kuyruğa alındı.');
        case 'paylasim_sil':
            DB::exec("DELETE FROM sosyal_paylasimlar WHERE id=? AND durum<>'gonderiliyor'", [$id]);
            yonlendir($geri, 'basari', 'Paylaşım kaydı silindi.');

        // ---------- Ayarlar ----------
        case 'ayarlar_kaydet': {
            foreach (SISTEM_AYARLARI as $anahtar => [$etiket, $envAdi, $vars, $tur]) {
                if ($tur === 'evethayir') {
                    ayarKaydet($anahtar, isset($_POST[$anahtar]) ? '1' : '0');
                    continue;
                }
                if (!array_key_exists($anahtar, $_POST)) continue;
                $deger = trim((string)$_POST[$anahtar]);
                if ($tur === 'gizli') {
                    if (!empty($_POST[$anahtar . '__sil'])) { ayarKaydet($anahtar, ''); continue; }
                    if ($deger === '') continue; // boş = mevcut değeri koru
                }
                if ($tur === 'saat_dilimi' && !in_array($deger, timezone_identifiers_list(), true)) {
                    yonlendir($geri, 'hata', 'Geçersiz saat dilimi.');
                }
                if ($anahtar === 'app_url' && $deger !== '' && !preg_match('~^https?://[^\s]+$~i', $deger)) {
                    yonlendir($geri, 'hata', 'Site adresi http:// veya https:// ile başlamalı.');
                }
                if ($anahtar === 'fb_graph_version' && $deger !== '' && !preg_match('/^v\d+\.\d+$/', $deger)) {
                    yonlendir($geri, 'hata', 'Graph API sürümü "v23.0" biçiminde olmalı.');
                }
                ayarKaydet($anahtar, rtrim($deger, $anahtar === 'app_url' ? '/' : ''));
            }
            yonlendir($geri, 'basari', '⚙️ Ayarlar kaydedildi.');
        }
        case 'kuyruk_calistir': {
            $o = kuyrukCalistir('admin', 50);
            yonlendir($geri, 'basari', "Kuyruk çalıştırıldı: {$o['gonderildi']} yayınlandı, {$o['ertelendi']} ertelendi, {$o['hata']} hata.");
        }
        case 'facebook_test': {
            try {
                $fb = FacebookGraph::ayarlardan();
                $fb->istek('GET', 'oauth/access_token', [
                    'client_id' => ayar('fb_app_id'), 'client_secret' => ayar('fb_app_secret'), 'grant_type' => 'client_credentials']);
                yonlendir($geri, 'basari', '✅ Facebook uygulama bilgileri doğru.');
            } catch (Throwable $e) {
                yonlendir($geri, 'hata', 'Facebook testi başarısız: ' . $e->getMessage());
            }
        }
    }
    yonlendir($geri, 'hata', 'Bilinmeyen işlem.');
}

// ============================================================
//  Görünüm
// ============================================================
$menu = [
    ['sep', 'Yönetim'],
    ['admin.php', '📊', 'Genel Bakış', 'genel'],
    ['admin.php?s=kullanicilar', '👥', 'Kullanıcılar', 'kullanicilar'],
    ['admin.php?s=magazalar', '🏪', 'Mağazalar', 'magazalar'],
    ['admin.php?s=sosyal', '📣', 'Sosyal Paylaşım', 'sosyal'],
    ['admin.php?s=ayarlar', '⚙️', 'Sistem Ayarları', 'ayarlar'],
    ['sep', 'Uygulama'],
    ['index.php', '🏠', 'Analiz Paneli', ''],
    ['hesabim.php', '👤', 'Hesabım', ''],
];
panelBasla('⚙️ Admin Paneli', ayar('site_adi'), $menu, $bolum);

$durumEtiket = [
    'bekliyor' => ['🕒 Bekliyor', 'badge-orange'], 'gonderiliyor' => ['⏳ Gönderiliyor', 'badge-blue'],
    'gonderildi' => ['✅ Yayınlandı', 'badge-green'], 'hata' => ['❌ Hata', 'badge-red'], 'iptal' => ['⛔ İptal', 'badge-gray'],
];
$cronSon = DB::row("SELECT deger, guncelleme FROM sistem_ayarlari WHERE anahtar='__cron_son_calisma'");

/** Tek butonlu POST formu. */
function islemButonu(string $act, int $id, string $etiket, string $sinif = '', string $onay = ''): string {
    $js = $onay !== '' ? ' onsubmit="return confirm(' . h(json_encode($onay, JSON_UNESCAPED_UNICODE)) . ')"' : '';
    return '<form method="post"' . $js . '>' . csrfAlan() . '<input type="hidden" name="act" value="' . h($act) . '">'
         . '<input type="hidden" name="id" value="' . $id . '"><button class="btn btn-sm ' . $sinif . '">' . $etiket . '</button></form>';
}
/** Adını/e-postasını yazdırarak onay isteyen silme formu. */
function silmeButonu(string $act, int $id, string $soru): string {
    return '<form method="post" onsubmit="const a=prompt(' . h(json_encode($soru, JSON_UNESCAPED_UNICODE)) . ');if(a===null)return false;this.onay.value=a;">'
         . csrfAlan() . '<input type="hidden" name="act" value="' . h($act) . '"><input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="onay"><button class="btn btn-sm btn-danger">🗑</button></form>';
}
$jsonBayrak = JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>

<?php /* ======================= GENEL ======================= */ if ($bolum === 'genel'):
    $k = DB::row("SELECT COUNT(*) toplam, SUM(aktif=1) aktif, SUM(rol='admin') admin,
                  SUM(kayit_tarihi > ?) yeni FROM kullanicilar", [date('Y-m-d H:i:s', time() - 30 * 86400)]);
    $m = DB::row("SELECT COUNT(*) toplam, SUM(aktif=1) aktif, SUM(ty_api_key<>'' AND ty_api_secret<>'') api FROM magazalar");
    $s = DB::row("SELECT SUM(durum='bekliyor') bekleyen, SUM(durum='hata') hata,
                  SUM(durum='gonderildi' AND gonderim_zamani > ?) hafta FROM sosyal_paylasimlar", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    $sh = (int)DB::scalar("SELECT COUNT(*) FROM sosyal_hesaplar");
    $siparis = (int)DB::scalar("SELECT COUNT(*) FROM siparisler");
    $urun    = (int)DB::scalar("SELECT COUNT(*) FROM trendyol_urunler");
    $sonKullanicilar = DB::rows("SELECT id, email, ad_soyad, rol, aktif, kayit_tarihi, son_giris FROM kullanicilar ORDER BY kayit_tarihi DESC LIMIT 8");
    $uyarilar = [];
    if (!FacebookGraph::yapilandirildi()) $uyarilar[] = ['warning', 'Facebook uygulaması yapılandırılmamış — kullanıcılar sayfa bağlayamaz. <a href="admin.php?s=ayarlar">Sistem Ayarları →</a>'];
    if ((int)$s['hata'] > 0) $uyarilar[] = ['danger', (int)$s['hata'] . ' paylaşım hata ile sonuçlandı. <a href="admin.php?s=sosyal&durum=hata">İncele →</a>'];
    if ((int)$s['bekleyen'] > 0 && (!$cronSon || strtotime($cronSon['guncelleme']) < time() - 600) && ayar('web_cron') !== '1') {
        $uyarilar[] = ['warning', 'Bekleyen paylaşımlar var ama zamanlayıcı son 10 dakikada çalışmadı. <a href="admin.php?s=ayarlar#zamanlayici">Zamanlayıcı →</a>'];
    }
?>
<div class="page-title">📊 <span>Genel Bakış</span></div>
<?php foreach ($uyarilar as [$tip, $html]): ?><div class="alert alert-<?= $tip ?>"><?= $html ?></div><?php endforeach; ?>
<div class="kpi-grid">
    <div class="kpi"><div class="kpi-label">Kullanıcı</div><div class="kpi-value"><?= sayiTr($k['toplam']) ?></div><div class="kpi-sub"><?= sayiTr($k['aktif']) ?> aktif · <?= sayiTr($k['admin']) ?> admin · son 30 gün +<?= sayiTr($k['yeni']) ?></div></div>
    <div class="kpi"><div class="kpi-label">Mağaza</div><div class="kpi-value"><?= sayiTr($m['toplam']) ?></div><div class="kpi-sub"><?= sayiTr($m['aktif']) ?> aktif · <?= sayiTr($m['api']) ?> API bağlı</div></div>
    <div class="kpi"><div class="kpi-label">Sipariş / Ürün</div><div class="kpi-value"><?= sayiTr($siparis) ?></div><div class="kpi-sub"><?= sayiTr($urun) ?> API ürünü</div></div>
    <div class="kpi"><div class="kpi-label">Sosyal Hesap</div><div class="kpi-value"><?= sayiTr($sh) ?></div><div class="kpi-sub">bağlı Facebook sayfası</div></div>
    <div class="kpi"><div class="kpi-label">Paylaşımlar</div><div class="kpi-value"><?= sayiTr($s['bekleyen']) ?></div><div class="kpi-sub">bekliyor · son 7 gün <?= sayiTr($s['hafta']) ?> yayın · <?= sayiTr($s['hata']) ?> hata</div></div>
    <div class="kpi"><div class="kpi-label">Zamanlayıcı</div><div class="kpi-value" style="font-size:16px"><?= $cronSon ? tarihTr($cronSon['guncelleme']) : 'Hiç çalışmadı' ?></div><div class="kpi-sub"><?= $cronSon ? h(json_decode($cronSon['deger'], true)['kaynak'] ?? '') : '' ?> · son çalışma</div></div>
</div>
<div class="card">
    <div class="card-title"><span>🆕 Son Kayıtlar</span><a href="admin.php?s=kullanicilar" class="btn btn-sm">Tümü →</a></div>
    <div class="tablo-kap"><table>
        <thead><tr><th>Kullanıcı</th><th>Rol</th><th>Kayıt</th><th>Son giriş</th><th>Durum</th></tr></thead>
        <tbody><?php foreach ($sonKullanicilar as $u): ?>
            <tr><td><strong><?= h($u['ad_soyad'] ?: '—') ?></strong><div class="muted"><?= h($u['email']) ?></div></td>
                <td><span class="badge <?= $u['rol'] === 'admin' ? 'badge-orange' : 'badge-blue' ?>"><?= $u['rol'] === 'admin' ? 'Admin' : 'Üye' ?></span></td>
                <td class="muted"><?= tarihTr($u['kayit_tarihi']) ?></td><td class="muted"><?= tarihTr($u['son_giris'] ?? null) ?></td>
                <td><span class="badge <?= $u['aktif'] ? 'badge-green' : 'badge-red' ?>"><?= $u['aktif'] ? 'Aktif' : 'Pasif' ?></span></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>

<?php /* ======================= KULLANICILAR ======================= */ elseif ($bolum === 'kullanicilar'):
    $q = trim($_GET['q'] ?? '');
    $kullanicilar = DB::rows("SELECT k.*,
            (SELECT COUNT(*) FROM magazalar m WHERE m.kullanici_id=k.id) magaza_sayisi,
            (SELECT COUNT(*) FROM sosyal_hesaplar s WHERE s.kullanici_id=k.id) sosyal_sayisi
        FROM kullanicilar k
        WHERE (? = '' OR k.email LIKE ? OR k.ad_soyad LIKE ?)
        ORDER BY k.kayit_tarihi DESC", [$q, "%$q%", "%$q%"]);
?>
<div class="page-title">👥 <span>Kullanıcılar</span></div>
<div class="card">
    <div class="card-title">
        <form method="get" style="display:flex;gap:8px;flex:1;max-width:420px"><input type="hidden" name="s" value="kullanicilar">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Ad veya e-posta ara…"><button class="btn">Ara</button></form>
        <button class="btn btn-primary" data-modal="kullaniciModal" onclick="kullaniciFormu(null)">➕ Kullanıcı Ekle</button>
    </div>
    <div class="tablo-kap"><table>
        <thead><tr><th>#</th><th>Kullanıcı</th><th>Rol</th><th>Mağaza</th><th>Sosyal</th><th>Kayıt</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($kullanicilar as $u): $benim = (int)$u['id'] === $benId; ?>
        <tr>
            <td class="muted"><?= (int)$u['id'] ?></td>
            <td><strong><?= h($u['ad_soyad'] ?: '—') ?></strong><?= $benim ? ' <span class="muted">(siz)</span>' : '' ?><div class="muted"><?= h($u['email']) ?></div></td>
            <td><span class="badge <?= $u['rol'] === 'admin' ? 'badge-orange' : 'badge-blue' ?>"><?= $u['rol'] === 'admin' ? 'Admin' : 'Üye' ?></span></td>
            <td><a href="admin.php?s=magazalar&q=<?= urlencode($u['email']) ?>"><?= (int)$u['magaza_sayisi'] ?></a></td>
            <td><?= (int)$u['sosyal_sayisi'] ?></td>
            <td class="muted"><?= tarihTr($u['kayit_tarihi'], false) ?></td>
            <td class="muted"><?= tarihTr($u['son_giris'] ?? null) ?></td>
            <td><span class="badge <?= $u['aktif'] ? 'badge-green' : 'badge-red' ?>"><?= $u['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="islem">
                <button class="btn btn-sm" title="Düzenle" data-modal="kullaniciModal" onclick='kullaniciFormu(<?= json_encode(['id' => (int)$u['id'], 'email' => $u['email'], 'ad_soyad' => $u['ad_soyad'], 'rol' => $u['rol'], 'aktif' => (int)$u['aktif']], $jsonBayrak) ?>)'>✏️</button>
                <button class="btn btn-sm" title="Şifre sıfırla" data-modal="sifreModal" onclick='sifreFormu(<?= (int)$u['id'] ?>, <?= json_encode($u['email'], $jsonBayrak) ?>)'>🔑</button>
                <?php if (!$benim): ?>
                    <?= islemButonu('kullanici_durum', (int)$u['id'], $u['aktif'] ? '⏸' : '▶', '', $u['aktif'] ? 'Kullanıcı pasif edilsin mi? Oturumu hemen kapanır.' : '') ?>
                    <?= silmeButonu('kullanici_sil', (int)$u['id'], "Kullanıcı; tüm mağazaları, sipariş/ürün verileri ve sosyal hesaplarıyla kalıcı olarak silinecek.\nOnaylamak için e-postasını yazın:") ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$kullanicilar): ?><tr><td colspan="9" class="bos">Sonuç yok.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="modal" id="kullaniciModal"><div class="modal-box">
    <h3 id="kBaslik">Kullanıcı</h3>
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="kullanici_kaydet"><input type="hidden" name="id" id="k_id">
        <div class="form-group"><label>Ad Soyad</label><input type="text" name="ad_soyad" id="k_ad"></div>
        <div class="form-group"><label>E-posta</label><input type="email" name="email" id="k_email" required></div>
        <div class="form-group" id="k_sifre_kap"><label>Şifre</label><input type="text" name="sifre" id="k_sifre" minlength="8" autocomplete="off" placeholder="En az 8 karakter"></div>
        <div class="form-grid">
            <div class="form-group"><label>Rol</label><select name="rol" id="k_rol"><option value="uye">Üye</option><option value="admin">Admin</option></select></div>
            <div class="form-group"><label>Durum</label><select name="aktif" id="k_aktif"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
        </div>
        <div style="display:flex;gap:8px"><button class="btn btn-primary">💾 Kaydet</button><button type="button" class="btn" data-kapat>İptal</button></div>
    </form>
</div></div>
<div class="modal" id="sifreModal"><div class="modal-box">
    <h3>🔑 Şifre Sıfırla</h3>
    <p class="muted" id="sKim" style="margin-bottom:12px"></p>
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="kullanici_sifre"><input type="hidden" name="id" id="s_id">
        <div class="form-group"><label>Yeni şifre</label>
            <div style="display:flex;gap:6px"><input type="text" name="sifre" id="s_sifre" required minlength="8" autocomplete="off">
            <button type="button" class="btn" onclick="document.getElementById('s_sifre').value=rastgeleSifre()">🎲</button></div></div>
        <div style="display:flex;gap:8px"><button class="btn btn-primary">Sıfırla</button><button type="button" class="btn" data-kapat>İptal</button></div>
    </form>
</div></div>
<script>
function rastgeleSifre() {
    const h = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', a = new Uint32Array(12);
    crypto.getRandomValues(a); return Array.from(a, x => h[x % h.length]).join('');
}
function kullaniciFormu(u) {
    const $ = id => document.getElementById(id);
    $('kBaslik').textContent = u ? '✏️ Kullanıcı Düzenle' : '➕ Kullanıcı Ekle';
    $('k_id').value = u ? u.id : ''; $('k_ad').value = u ? (u.ad_soyad || '') : ''; $('k_email').value = u ? u.email : '';
    $('k_rol').value = u ? u.rol : 'uye'; $('k_aktif').value = u ? u.aktif : 1;
    $('k_sifre_kap').style.display = u ? 'none' : ''; $('k_sifre').required = !u; $('k_sifre').value = u ? '' : rastgeleSifre();
}
function sifreFormu(id, email) {
    document.getElementById('s_id').value = id; document.getElementById('sKim').textContent = email;
    document.getElementById('s_sifre').value = rastgeleSifre();
}
</script>

<?php /* ======================= MAĞAZALAR ======================= */ elseif ($bolum === 'magazalar'):
    $q = trim($_GET['q'] ?? '');
    $magazalar = DB::rows("SELECT m.*, k.email, k.ad_soyad,
            (SELECT COUNT(*) FROM siparisler s WHERE s.magaza_id=m.id) siparis_sayisi,
            (SELECT COUNT(*) FROM trendyol_urunler u WHERE u.magaza_id=m.id) urun_sayisi
        FROM magazalar m JOIN kullanicilar k ON k.id=m.kullanici_id
        WHERE (? = '' OR m.magaza_adi LIKE ? OR k.email LIKE ? OR m.ty_seller_id LIKE ?)
        ORDER BY m.olusturma DESC", [$q, "%$q%", "%$q%", "%$q%"]);
    $sahipler = DB::rows("SELECT id, email, ad_soyad FROM kullanicilar ORDER BY email");
?>
<div class="page-title">🏪 <span>Mağazalar</span></div>
<div class="card">
    <div class="card-title">
        <form method="get" style="display:flex;gap:8px;flex:1;max-width:420px"><input type="hidden" name="s" value="magazalar">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Mağaza, sahip e-postası veya Seller ID…"><button class="btn">Ara</button></form>
        <button class="btn btn-primary" data-modal="magazaModal" onclick="magazaFormu(null)">➕ Mağaza Ekle</button>
    </div>
    <div class="tablo-kap"><table>
        <thead><tr><th>#</th><th>Mağaza</th><th>Sahibi</th><th>Seller ID</th><th>API</th><th>AI</th><th>Sipariş</th><th>Ürün</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($magazalar as $m): ?>
        <tr>
            <td class="muted"><?= (int)$m['id'] ?></td>
            <td><strong><?= h($m['magaza_adi']) ?></strong><div class="muted"><?= tarihTr($m['olusturma'], false) ?></div></td>
            <td><?= h($m['ad_soyad'] ?: '—') ?><div class="muted"><?= h($m['email']) ?></div></td>
            <td><code><?= h($m['ty_seller_id'] ?: '—') ?></code></td>
            <td><?= ($m['ty_api_key'] && $m['ty_api_secret']) ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-red">✗</span>' ?></td>
            <td><?= !empty($m['anthropic_api_key']) ? '<span class="badge badge-green">✓</span>' : '<span class="badge badge-gray">—</span>' ?></td>
            <td><?= sayiTr($m['siparis_sayisi']) ?></td>
            <td><?= sayiTr($m['urun_sayisi']) ?></td>
            <td><span class="badge <?= $m['aktif'] ? 'badge-green' : 'badge-gray' ?>"><?= $m['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
            <td class="islem">
                <button class="btn btn-sm" data-modal="magazaModal" onclick='magazaFormu(<?= json_encode([
                    'id' => (int)$m['id'], 'kullanici_id' => (int)$m['kullanici_id'], 'magaza_adi' => $m['magaza_adi'],
                    'ty_seller_id' => $m['ty_seller_id'], 'ty_api_key' => $m['ty_api_key'],
                    'secret' => maskele($m['ty_api_secret']), 'ant' => maskele($m['anthropic_api_key'] ?? ''), 'aktif' => (int)$m['aktif']], $jsonBayrak) ?>)'>✏️</button>
                <?= islemButonu('magaza_durum', (int)$m['id'], $m['aktif'] ? '⏸' : '▶') ?>
                <?= silmeButonu('magaza_sil', (int)$m['id'], "Mağaza tüm sipariş/ürün/maliyet verileriyle kalıcı olarak silinecek.\nOnaylamak için mağaza adını yazın:") ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$magazalar): ?><tr><td colspan="10" class="bos">Sonuç yok.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<div class="modal" id="magazaModal"><div class="modal-box">
    <h3 id="mBaslik">Mağaza</h3>
    <form method="post">
        <?= csrfAlan() ?><input type="hidden" name="act" value="magaza_kaydet"><input type="hidden" name="id" id="m_id">
        <div class="form-group"><label>Sahibi</label><select name="kullanici_id" id="m_sahip" required>
            <?php foreach ($sahipler as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['email'] . ($s['ad_soyad'] ? ' — ' . $s['ad_soyad'] : '')) ?></option><?php endforeach; ?>
        </select></div>
        <div class="form-group"><label>Mağaza adı</label><input type="text" name="magaza_adi" id="m_ad" required></div>
        <div class="form-group"><label>Trendyol Seller ID</label><input type="text" name="ty_seller_id" id="m_sid"></div>
        <div class="form-group"><label>API Key</label><input type="text" name="ty_api_key" id="m_key" autocomplete="off"></div>
        <div class="form-group"><label>API Secret</label><input type="password" name="ty_api_secret" id="m_sec" autocomplete="new-password"><span class="ipucu" id="m_sec_ipucu"></span></div>
        <div class="form-group"><label>Anthropic API anahtarı</label><input type="password" name="anthropic_api_key" id="m_ant" autocomplete="new-password"><span class="ipucu" id="m_ant_ipucu"></span></div>
        <div class="form-group"><label>Durum</label><select name="aktif" id="m_aktif"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
        <div style="display:flex;gap:8px"><button class="btn btn-primary">💾 Kaydet</button><button type="button" class="btn" data-kapat>İptal</button></div>
    </form>
</div></div>
<script>
function magazaFormu(m) {
    const $ = id => document.getElementById(id);
    $('mBaslik').textContent = m ? '✏️ Mağaza Düzenle' : '➕ Mağaza Ekle';
    $('m_id').value = m ? m.id : ''; $('m_ad').value = m ? m.magaza_adi : '';
    if (m) $('m_sahip').value = m.kullanici_id;
    $('m_sid').value = m ? (m.ty_seller_id || '') : ''; $('m_key').value = m ? (m.ty_api_key || '') : '';
    $('m_sec').value = ''; $('m_ant').value = ''; $('m_aktif').value = m ? m.aktif : 1;
    $('m_sec_ipucu').textContent = m && m.secret ? 'Kayıtlı: ' + m.secret + ' — boş bırakırsanız korunur.' : '';
    $('m_ant_ipucu').textContent = m && m.ant ? 'Kayıtlı: ' + m.ant + ' — boş bırakırsanız korunur.' : '';
}
</script>

<?php /* ======================= SOSYAL ======================= */ elseif ($bolum === 'sosyal'):
    $durumFiltre = array_key_exists($_GET['durum'] ?? '', $durumEtiket) ? $_GET['durum'] : '';
    $hesaplar = DB::rows("SELECT h.*, k.email,
            (SELECT COUNT(*) FROM sosyal_paylasimlar p WHERE p.hesap_id=h.id AND p.durum='gonderildi') yayin
        FROM sosyal_hesaplar h JOIN kullanicilar k ON k.id=h.kullanici_id ORDER BY h.baglanma DESC");
    $paylasimlar = DB::rows("SELECT p.*, h.ad hesap_ad, k.email FROM sosyal_paylasimlar p
        JOIN sosyal_hesaplar h ON h.id=p.hesap_id JOIN kullanicilar k ON k.id=p.kullanici_id
        WHERE (? = '' OR p.durum = ?) ORDER BY p.planlanan_zaman DESC LIMIT 300", [$durumFiltre, $durumFiltre]);
    $sayac = [];
    foreach (DB::rows("SELECT durum, COUNT(*) n FROM sosyal_paylasimlar GROUP BY durum") as $r) $sayac[$r['durum']] = (int)$r['n'];
?>
<div class="page-title">📣 <span>Sosyal Paylaşım</span></div>
<div class="card">
    <div class="card-title"><span>🔗 Bağlı Hesaplar (<?= count($hesaplar) ?>)</span></div>
    <div class="tablo-kap"><table>
        <thead><tr><th>Sayfa</th><th>Kullanıcı</th><th>Bağlanma</th><th>Yayın</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($hesaplar as $hs): ?>
        <tr>
            <td><strong><?= h($hs['ad']) ?></strong><div class="muted">Facebook · <?= h($hs['dis_id']) ?></div></td>
            <td class="muted"><?= h($hs['email']) ?></td>
            <td class="muted"><?= tarihTr($hs['baglanma']) ?></td>
            <td><?= (int)$hs['yayin'] ?></td>
            <td><?php if ($hs['durum'] === 'aktif'): ?><span class="badge badge-green">Aktif</span>
                <?php elseif ($hs['durum'] === 'pasif'): ?><span class="badge badge-gray">Pasif</span>
                <?php else: ?><span class="badge badge-red" title="<?= h($hs['son_hata']) ?>">Yeniden bağlanmalı</span><?php endif; ?></td>
            <td class="islem">
                <?php if ($hs['durum'] !== 'yeniden_baglan'): ?><?= islemButonu('hesap_durum', (int)$hs['id'], $hs['durum'] === 'aktif' ? '⏸' : '▶') ?><?php endif; ?>
                <?= islemButonu('hesap_sil', (int)$hs['id'], '🗑', 'btn-danger', 'Hesap ve tüm paylaşım kayıtları silinsin mi?') ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$hesaplar): ?><tr><td colspan="6" class="bos">Henüz bağlı hesap yok.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-title"><span>🗂️ Paylaşımlar</span></div>
    <div class="sekme">
        <a href="admin.php?s=sosyal" class="<?= $durumFiltre === '' ? 'active' : '' ?>">Tümü (<?= array_sum($sayac) ?>)</a>
        <?php foreach ($durumEtiket as $d => [$et]): ?><a href="admin.php?s=sosyal&durum=<?= $d ?>" class="<?= $durumFiltre === $d ? 'active' : '' ?>"><?= h($et) ?> (<?= $sayac[$d] ?? 0 ?>)</a><?php endforeach; ?>
    </div>
    <div class="tablo-kap"><table>
        <thead><tr><th>Zaman</th><th>Kullanıcı / Sayfa</th><th>İçerik</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($paylasimlar as $p): [$et, $cls] = $durumEtiket[$p['durum']]; ?>
        <tr>
            <td class="muted" style="white-space:nowrap"><?= tarihTr($p['gonderim_zamani'] ?: $p['planlanan_zaman']) ?></td>
            <td><?= h($p['hesap_ad']) ?><div class="muted"><?= h($p['email']) ?></div></td>
            <td style="max-width:420px"><div style="white-space:pre-wrap;max-height:3em;overflow:hidden"><?= h(mb_substr((string)$p['mesaj'], 0, 200)) ?></div>
                <?php if ($p['hata_mesaji']): ?><div style="color:#ff8a7d;font-size:11px">⚠️ <?= h($p['hata_mesaji']) ?></div><?php endif; ?></td>
            <td><span class="badge <?= $cls ?>"><?= h($et) ?></span>
                <?php if ($p['dis_post_id']): ?><div><a href="https://www.facebook.com/<?= h($p['dis_post_id']) ?>" target="_blank" rel="noopener" style="font-size:12px">Aç ↗</a></div><?php endif; ?></td>
            <td class="islem">
                <?php if ($p['durum'] === 'bekliyor'): ?><?= islemButonu('paylasim_iptal', (int)$p['id'], '⛔') ?><?php endif; ?>
                <?php if (in_array($p['durum'], ['hata', 'iptal'], true)): ?><?= islemButonu('paylasim_tekrar', (int)$p['id'], '🔁') ?><?php endif; ?>
                <?php if ($p['durum'] !== 'gonderiliyor'): ?><?= islemButonu('paylasim_sil', (int)$p['id'], '🗑', 'btn-danger', 'Kayıt silinsin mi?') ?><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$paylasimlar): ?><tr><td colspan="5" class="bos">Kayıt yok.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php /* ======================= AYARLAR ======================= */ else:
    $webCronUrl = siteTabanUrl() . '/cron_paylasim.php?anahtar=' . webCronAnahtari();
    $gruplar = [
        '🌐 Genel'              => ['site_adi', 'app_url', 'app_timezone', 'kayit_acik'],
        'ⓕ Facebook Uygulaması' => ['fb_app_id', 'fb_app_secret', 'fb_graph_version'],
        '⏱️ Zamanlayıcı'        => ['web_cron'],
    ];
    $ipuclari = [
        'app_url' => 'Boş bırakılırsa tarayıcıdaki adresten türetilir. Facebook yönlendirme adresi bundan oluşur.',
        'kayit_acik' => 'Kapalıyken yeni üyeleri yalnızca admin ekleyebilir.',
        'fb_app_secret' => 'Şifreli saklanır.',
        'web_cron' => 'Açıkken zamanı gelen paylaşımlar site ziyaret edildikçe yayınlanır (dakikada en fazla bir kez). Sunucu cron\'u kurduysanız kapatabilirsiniz.',
    ];
    $mysqlSurum = (string)DB::scalar("SELECT VERSION()");
?>
<div class="page-title">⚙️ <span>Sistem Ayarları</span></div>
<form method="post" class="card">
    <?= csrfAlan() ?><input type="hidden" name="act" value="ayarlar_kaydet">
    <?php foreach ($gruplar as $grup => $anahtarlar): ?>
        <div class="card-title" style="margin-top:6px"><span><?= h($grup) ?></span></div>
        <div class="form-grid" style="margin-bottom:10px">
        <?php foreach ($anahtarlar as $a): [$etiket, $envAdi, $vars, $tur] = SISTEM_AYARLARI[$a]; $kaynak = ayarKaynagi($a); ?>
            <?php if ($tur === 'evethayir'): ?>
                <div class="form-group"><label>&nbsp;</label>
                    <label class="onay"><input type="checkbox" name="<?= $a ?>" value="1" <?= ayar($a) === '1' ? 'checked' : '' ?>> <?= h($etiket) ?></label>
                    <?php if (isset($ipuclari[$a])): ?><span class="ipucu"><?= h($ipuclari[$a]) ?></span><?php endif; ?></div>
            <?php else: ?>
                <div class="form-group"><label><?= h($etiket) ?> <span class="badge badge-gray" title="Değerin kaynağı"><?= h($kaynak) ?></span></label>
                <?php if ($tur === 'gizli'): ?>
                    <input type="password" name="<?= $a ?>" autocomplete="new-password" placeholder="<?= ayar($a) !== '' ? h(maskele(ayar($a))) . ' — değiştirmek için yazın' : 'Girilmedi' ?>">
                    <?php if ($kaynak === 'panel'): ?><label class="onay" style="margin:0;font-size:12px"><input type="checkbox" name="<?= $a ?>__sil" value="1"> Kayıtlı değeri sil</label><?php endif; ?>
                <?php elseif ($tur === 'saat_dilimi'): ?>
                    <select name="<?= $a ?>"><?php foreach (timezone_identifiers_list() as $tz): ?><option <?= $tz === ayar($a) ? 'selected' : '' ?>><?= h($tz) ?></option><?php endforeach; ?></select>
                <?php else: ?>
                    <input type="text" name="<?= $a ?>" value="<?= h($kaynak === 'panel' ? ayar($a) : '') ?>" placeholder="<?= h(ayar($a)) ?>">
                <?php endif; ?>
                <?php if (isset($ipuclari[$a])): ?><span class="ipucu"><?= h($ipuclari[$a]) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-primary">💾 Ayarları Kaydet</button>
</form>

<div class="card">
    <div class="card-title"><span>ⓕ Facebook kurulum rehberi</span>
        <?php if (FacebookGraph::yapilandirildi()): ?><form method="post"><?= csrfAlan() ?><input type="hidden" name="act" value="facebook_test"><button class="btn btn-sm">🧪 Bilgileri Test Et</button></form><?php endif; ?></div>
    <ol style="padding-left:18px;line-height:1.9;font-size:13px">
        <li><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> → <em>Uygulama Oluştur</em> → tür olarak <em>İşletme</em>.</li>
        <li><em>Ürün ekle</em> → <strong>Facebook Girişi</strong> → Ayarlar → <em>Geçerli OAuth Yönlendirme URI'leri</em>: <code><?= h(facebookYonlendirmeUrl()) ?></code></li>
        <li><em>Uygulama Ayarları → Temel</em>'deki <strong>Uygulama Kimliği</strong> ve <strong>Uygulama Gizli Anahtarı</strong>'nı yukarıya girin.</li>
        <li>Uygulamaya izinleri ekleyin: <code><?= h(implode(', ', FB_IZINLER)) ?></code>. Geliştirme modunda yalnızca uygulamada rolü olan kişiler bağlanabilir; tüm üyeler için <em>Uygulama İncelemesi</em>'nden bu izinler onaylatılıp uygulama <em>Canlı</em>'ya alınmalı.</li>
    </ol>
</div>

<div class="card" id="zamanlayici">
    <div class="card-title"><span>⏱️ Zamanlayıcı</span>
        <form method="post"><?= csrfAlan() ?><input type="hidden" name="act" value="kuyruk_calistir"><button class="btn btn-sm btn-primary">▶ Kuyruğu Şimdi Çalıştır</button></form></div>
    <p style="margin-bottom:10px">Son çalışma: <strong><?= $cronSon ? tarihTr($cronSon['guncelleme']) : 'hiç' ?></strong>
        <?php if ($cronSon): $o = json_decode($cronSon['deger'], true) ?: []; ?><span class="muted">(<?= h($o['kaynak'] ?? '') ?> · <?= (int)($o['gonderildi'] ?? 0) ?> yayın, <?= (int)($o['hata'] ?? 0) ?> hata)</span><?php endif; ?></p>
    <p class="muted" style="margin-bottom:8px">Ek kurulum gerekmez — "site ziyaretlerinde yayınla" açıkken paylaşımlar kendiliğinden gider. Ziyaretten bağımsız, dakikası dakikasına yayın için aşağıdakilerden biri kullanılabilir:</p>
    <div class="form-group"><label>Harici cron servisi (cron-job.org vb.) — her dakika çağrılacak URL</label><code><?= h($webCronUrl) ?></code></div>
    <div class="form-group"><label>Sunucu cron'u</label><code>* * * * * php <?= h(__DIR__) ?>/cron_paylasim.php</code></div>
</div>

<div class="card">
    <div class="card-title"><span>🖥️ Sistem Bilgisi</span></div>
    <div class="tablo-kap"><table><tbody>
        <tr><td class="muted">PHP</td><td><?= h(PHP_VERSION) ?></td></tr>
        <tr><td class="muted">MySQL</td><td><?= h($mysqlSurum) ?></td></tr>
        <tr><td class="muted">Veritabanı</td><td><code><?= h(DB_NAME) ?></code> @ <?= h(DB_HOST) ?></td></tr>
        <tr><td class="muted">Şifreleme anahtarı</td><td><?= env('APP_KEY') !== '' ? '<span class="badge badge-green">.env</span>' : '<span class="badge badge-orange">veritabanı</span> <span class="muted">— daha güvenli olması için .env\'ye APP_KEY eklenebilir (mevcut şifreli değerler yeniden girilmelidir)</span>' ?></td></tr>
        <tr><td class="muted">Saat</td><td><?= h(date('d.m.Y H:i:s')) ?> (<?= h(date_default_timezone_get()) ?>)</td></tr>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php panelBitir();
