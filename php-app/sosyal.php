<?php
// ============================================================
//  📣 Sosyal Paylaşım — hesap bağlama, paylaşım oluşturma/planlama,
//  paylaşım geçmişi. Yayını cron_paylasim.php yapar ("Hemen paylaş"
//  seçilirse bu sayfa da anında yayınlar). Hesaplar kullanıcıya aittir
//  (mağazadan bağımsız); ürün seçici o an seçili mağazanın ürünlerini kullanır.
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/SosyalMedya.php';
requireLogin();

$kullaniciId = (int)authUser()['id'];
$magaza      = authMagaza();
$bildirim    = $_SESSION['sosyal_bildirim'] ?? null;
unset($_SESSION['sosyal_bildirim']);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function yonlendir(string $tip, string $mesaj): never {
    $_SESSION['sosyal_bildirim'] = [$tip, $mesaj];
    header('Location: sosyal.php');
    exit;
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
    sosyalSemaKur();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Veritabanı hatası: ' . h($e->getMessage()));
}

// ---- Aksiyonlar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) yonlendir('hata', 'Oturum doğrulaması başarısız, sayfayı yenileyip tekrar deneyin.');
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'paylas') {
        $hesapIdler = array_map('intval', (array)($_POST['hesap_idler'] ?? []));
        $girdi = [
            'mesaj'       => (string)($_POST['mesaj'] ?? ''),
            'link'        => (string)($_POST['link'] ?? ''),
            'gorsel_url'  => (string)($_POST['gorsel_url'] ?? ''),
            'hesap_idler' => $hesapIdler,
        ];
        $hatalar = paylasimDogrula($girdi);
        $simdi   = simdi();
        $hemen   = ($_POST['zamanlama'] ?? 'hemen') === 'hemen';
        $zaman   = $hemen ? $simdi : planlananZamanCoz((string)($_POST['planlanan_zaman'] ?? ''), $simdi);
        if ($zaman === null) $hatalar[] = 'Planlanan zaman geçersiz.';

        // Yalnızca kullanıcının kendi aktif hesapları
        $gecerli = [];
        if ($hesapIdler) {
            $yer = implode(',', array_fill(0, count($hesapIdler), '?'));
            $gecerli = array_column(DB::rows(
                "SELECT id FROM sosyal_hesaplar WHERE kullanici_id=? AND durum='aktif' AND id IN ($yer)",
                array_merge([$kullaniciId], $hesapIdler)), 'id');
            if (count($gecerli) !== count(array_unique($hesapIdler))) $hatalar[] = 'Seçilen hesaplardan biri geçersiz ya da aktif değil.';
        }
        if ($hatalar) yonlendir('hata', implode(' ', $hatalar));

        $yeniIdler = [];
        foreach ($gecerli as $hid) {
            DB::exec(
                "INSERT INTO sosyal_paylasimlar (kullanici_id,hesap_id,mesaj,link,gorsel_url,planlanan_zaman,olusturma)
                 VALUES (?,?,?,?,?,?,?)",
                [$kullaniciId, $hid, trim($girdi['mesaj']), trim($girdi['link']) ?: null,
                 trim($girdi['gorsel_url']) ?: null, $zaman, $simdi]);
            $yeniIdler[] = (int)DB::lastId();
        }
        if ($hemen) {
            $ozet = paylasimKuyrugunuIsle(null, count($yeniIdler), $yeniIdler);
            $tip  = $ozet['gonderildi'] === count($yeniIdler) ? 'basari' : 'hata';
            yonlendir($tip, "Paylaşım sonucu: {$ozet['gonderildi']} yayınlandı"
                . ($ozet['ertelendi'] ? ", {$ozet['ertelendi']} tekrar denenecek" : '')
                . ($ozet['hata'] ? ", {$ozet['hata']} başarısız (ayrıntı aşağıda)" : '') . '.');
        }
        yonlendir('basari', '🗓️ ' . count($yeniIdler) . ' paylaşım ' . date('d.m.Y H:i', strtotime($zaman)) . ' için planlandı.');
    }
    elseif ($act === 'iptal') {
        DB::exec("UPDATE sosyal_paylasimlar SET durum='iptal' WHERE id=? AND kullanici_id=? AND durum='bekliyor'", [$id, $kullaniciId]);
        yonlendir('basari', 'Paylaşım iptal edildi.');
    }
    elseif ($act === 'tekrar') {
        DB::exec("UPDATE sosyal_paylasimlar SET durum='bekliyor', deneme_sayisi=0, hata_mesaji=NULL, planlanan_zaman=?
                  WHERE id=? AND kullanici_id=? AND durum IN ('hata','iptal')", [simdi(), $id, $kullaniciId]);
        yonlendir('basari', 'Paylaşım tekrar kuyruğa alındı; bir dakika içinde yayınlanır.');
    }
    elseif ($act === 'sil') {
        DB::exec("DELETE FROM sosyal_paylasimlar WHERE id=? AND kullanici_id=? AND durum<>'gonderiliyor'", [$id, $kullaniciId]);
        yonlendir('basari', 'Paylaşım kaydı silindi (Facebook\'taki gönderi etkilenmez).');
    }
    elseif ($act === 'hesap_durum') {
        DB::exec("UPDATE sosyal_hesaplar SET durum=IF(durum='aktif','pasif','aktif'), guncelleme=?
                  WHERE id=? AND kullanici_id=? AND durum IN ('aktif','pasif')", [simdi(), $id, $kullaniciId]);
        yonlendir('basari', 'Hesap durumu güncellendi.');
    }
    elseif ($act === 'hesap_sil') {
        DB::exec("DELETE FROM sosyal_hesaplar WHERE id=? AND kullanici_id=?", [$id, $kullaniciId]);
        yonlendir('basari', 'Hesap kaldırıldı; bekleyen paylaşımları da silindi.');
    }
    yonlendir('hata', 'Bilinmeyen işlem.');
}

// ---- Veriler ----
$hesaplar = DB::rows("SELECT id, platform, dis_id, ad, resim_url, durum, son_hata, baglanma
                      FROM sosyal_hesaplar WHERE kullanici_id=? ORDER BY ad", [$kullaniciId]);
$aktifHesaplar = array_values(array_filter($hesaplar, fn($x) => $x['durum'] === 'aktif'));
$paylasimlar = DB::rows(
    "SELECT p.*, h.ad AS hesap_ad FROM sosyal_paylasimlar p JOIN sosyal_hesaplar h ON h.id=p.hesap_id
     WHERE p.kullanici_id=? ORDER BY (p.durum IN ('bekliyor','gonderiliyor')) DESC, p.planlanan_zaman DESC LIMIT 200",
    [$kullaniciId]);
$sayac = array_count_values(array_column($paylasimlar, 'durum'));

$urunler = [];
if ($magaza) {
    try {
        $urunler = DB::rows(
            "SELECT id, title, brand, barcode, sale_price, image_url, product_url FROM trendyol_urunler
             WHERE magaza_id=? ORDER BY title LIMIT 2000", [(int)$magaza['id']]);
    } catch (PDOException $e) { /* tablo yoksa ürün seçici gizlenir */ }
}

$fbHazir   = FacebookGraph::yapilandirildi() && sosyalAnahtarVarMi();
$durumEtiket = [
    'bekliyor'     => ['🕒 Bekliyor', 'badge-orange'],
    'gonderiliyor' => ['⏳ Gönderiliyor', 'badge-blue'],
    'gonderildi'   => ['✅ Yayınlandı', 'badge-green'],
    'hata'         => ['❌ Hata', 'badge-red'],
    'iptal'        => ['⛔ İptal', 'badge-gray'],
];
$varsayilanSablon = "{urun_adi}\n\n💰 {fiyat}\n\n🛒 Hemen incele: {link}";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sosyal Paylaşım</title>
<style>
:root{--primary:#f27a1a;--bg:#0f1117;--bg2:#1a1d2e;--bg3:#252840;--card:#1e2035;--border:#2e3150;--text:#e8eaf6;--text2:#9099c4;--green:#2ecc71;--red:#e74c3c;--blue:#3498db;--fb:#1877f2;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;font-size:14px;padding:24px;max-width:1200px;margin:0 auto;}
a{color:var(--primary);}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;}
.page-title{font-size:22px;font-weight:700;}.page-title span{color:var(--primary);}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px;}
.card-title{font-size:15px;font-weight:600;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;line-height:1.5;}
.alert-success{background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.35);color:var(--green);}
.alert-danger{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.35);color:#ff8a7d;}
.alert-info{background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.3);color:#8fc7ee;}
.alert code{background:var(--bg3);padding:1px 5px;border-radius:4px;color:var(--text);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:13px;cursor:pointer;text-decoration:none;}
.btn:hover{border-color:var(--primary);}
.btn-primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.btn-fb{background:var(--fb);border-color:var(--fb);color:#fff;}
.btn-sm{padding:4px 9px;font-size:12px;}
.btn[disabled]{opacity:.5;cursor:not-allowed;}
.hesaplar{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
.hesap{display:flex;gap:10px;align-items:center;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px;}
.hesap img,.hesap .ph{width:40px;height:40px;border-radius:50%;flex-shrink:0;background:var(--fb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;}
.hesap .bilgi{flex:1;min-width:0;}
.hesap .ad{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.hesap .alt{font-size:11px;color:var(--text2);margin-top:2px;}
.hesap form{display:inline;}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;}
.badge-green{background:rgba(46,204,113,.15);color:var(--green);}.badge-red{background:rgba(231,76,60,.15);color:var(--red);}
.badge-orange{background:rgba(242,122,26,.15);color:var(--primary);}.badge-gray{background:rgba(144,153,196,.12);color:var(--text2);}
.badge-blue{background:rgba(52,152,219,.15);color:var(--blue);}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:12px;}
.form-group label{font-size:12px;color:var(--text2);font-weight:500;}
input[type=text],input[type=url],input[type=datetime-local],select,textarea{background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;font-size:13px;outline:none;font-family:inherit;width:100%;}
input:focus,select:focus,textarea:focus{border-color:var(--primary);}
textarea{min-height:140px;resize:vertical;}
.secim{display:flex;flex-wrap:wrap;gap:8px;}
.secim label{display:flex;align-items:center;gap:6px;background:var(--bg3);border:1px solid var(--border);padding:6px 10px;border-radius:8px;cursor:pointer;font-size:13px;color:var(--text);}
.ipucu{font-size:11px;color:var(--text2);}
.onizleme{background:#fff;color:#1c1e21;border-radius:10px;overflow:hidden;font-family:Helvetica,Arial,sans-serif;}
.onizleme .ust{padding:12px;display:flex;gap:8px;align-items:center;font-weight:600;font-size:14px;}
.onizleme .ust .ph{width:36px;height:36px;border-radius:50%;background:var(--fb);}
.onizleme .metin{padding:0 12px 12px;white-space:pre-wrap;word-break:break-word;font-size:14px;line-height:1.4;}
.onizleme img{width:100%;max-height:360px;object-fit:cover;display:block;}
.onizleme .link{padding:10px 12px;background:#f0f2f5;font-size:12px;color:#65676b;word-break:break-all;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:9px 10px;font-size:11px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);background:var(--bg3);white-space:nowrap;}
td{padding:9px 10px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:top;}
td .ozet{max-width:380px;white-space:pre-wrap;word-break:break-word;max-height:4.2em;overflow:hidden;}
td .hata{color:#ff8a7d;font-size:11px;margin-top:4px;max-width:380px;}
td img.kucuk{width:48px;height:48px;object-fit:cover;border-radius:6px;}
td form{display:inline;}
.tablo-kap{overflow-x:auto;}
.bos{color:var(--text2);text-align:center;padding:24px;}
@media(max-width:820px){body{padding:14px;}.grid2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<div class="top">
    <div class="page-title">📣 Sosyal <span>Paylaşım</span></div>
    <a href="index.php" class="btn">← Panele Dön</a>
</div>

<?php if ($bildirim): ?>
<div class="alert <?= $bildirim[0] === 'basari' ? 'alert-success' : 'alert-danger' ?>"><?= h($bildirim[1]) ?></div>
<?php endif; ?>

<?php if (!$fbHazir): ?>
<div class="alert alert-info">
    <strong>Kurulum gerekli:</strong> Facebook sayfası bağlanabilmesi için sunucudaki <code>.env</code> dosyasında
    <code>FB_APP_ID</code>, <code>FB_APP_SECRET</code> ve <code>APP_KEY</code> tanımlı olmalıdır.
    Facebook uygulamasının "Geçerli OAuth Yönlendirme URI'leri" alanına bu sitedeki <code>facebook_callback.php</code>
    adresi eklenmelidir. Otomatik yayın için cron: <code>* * * * * php <?= h(__DIR__) ?>/cron_paylasim.php</code>
</div>
<?php endif; ?>

<!-- ============ Hesaplar ============ -->
<div class="card">
    <div class="card-title">
        <span>🔗 Bağlı Hesaplar</span>
        <?php if ($fbHazir): ?>
            <a href="facebook_callback.php?baslat=1" class="btn btn-fb">ⓕ Facebook Sayfası Bağla</a>
        <?php else: ?>
            <button class="btn btn-fb" disabled>ⓕ Facebook Sayfası Bağla</button>
        <?php endif; ?>
    </div>
    <?php if (!$hesaplar): ?>
        <div class="bos">Henüz bağlı hesap yok. "Facebook Sayfası Bağla" ile yönettiğiniz sayfaları ekleyin.</div>
    <?php else: ?>
    <div class="hesaplar">
        <?php foreach ($hesaplar as $hs): ?>
        <div class="hesap">
            <?php if ($hs['resim_url']): ?><img src="<?= h($hs['resim_url']) ?>" alt=""><?php else: ?><div class="ph">f</div><?php endif; ?>
            <div class="bilgi">
                <div class="ad" title="<?= h($hs['ad']) ?>"><?= h($hs['ad']) ?></div>
                <div class="alt">
                    <?php if ($hs['durum'] === 'aktif'): ?><span class="badge badge-green">Aktif</span>
                    <?php elseif ($hs['durum'] === 'pasif'): ?><span class="badge badge-gray">Pasif</span>
                    <?php else: ?><span class="badge badge-red" title="<?= h($hs['son_hata']) ?>">Yeniden bağlanmalı</span><?php endif; ?>
                    Facebook Sayfası
                </div>
                <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap">
                    <?php if ($hs['durum'] === 'yeniden_baglan' && $fbHazir): ?>
                        <a href="facebook_callback.php?baslat=1" class="btn btn-sm btn-fb">Yeniden Bağla</a>
                    <?php elseif ($hs['durum'] !== 'yeniden_baglan'): ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="act" value="hesap_durum"><input type="hidden" name="id" value="<?= (int)$hs['id'] ?>">
                            <button class="btn btn-sm"><?= $hs['durum'] === 'aktif' ? '⏸ Pasifleştir' : '▶ Aktifleştir' ?></button></form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Hesap ve bu hesaba ait tüm paylaşım kayıtları silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="act" value="hesap_sil"><input type="hidden" name="id" value="<?= (int)$hs['id'] ?>">
                        <button class="btn btn-sm">🗑 Kaldır</button></form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ============ Yeni paylaşım ============ -->
<div class="card">
    <div class="card-title"><span>✍️ Yeni Paylaşım</span></div>
    <?php if (!$aktifHesaplar): ?>
        <div class="bos">Paylaşım yapmak için önce aktif bir hesap bağlayın.</div>
    <?php else: ?>
    <form method="post" id="paylasimForm">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="act" value="paylas">
        <div class="grid2">
            <div>
                <div class="form-group">
                    <label>Paylaşılacak hesaplar</label>
                    <div class="secim">
                        <?php foreach ($aktifHesaplar as $hs): ?>
                        <label><input type="checkbox" name="hesap_idler[]" value="<?= (int)$hs['id'] ?>" data-ad="<?= h($hs['ad']) ?>" <?= count($aktifHesaplar) === 1 ? 'checked' : '' ?>> <?= h($hs['ad']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($urunler): ?>
                <div class="form-group">
                    <label>Üründen doldur (<?= h($magaza['magaza_adi']) ?> — <?= count($urunler) ?> ürün)</label>
                    <input type="text" id="urunAra" list="urunListe" placeholder="Ürün adı veya barkod yazın…" autocomplete="off">
                    <datalist id="urunListe">
                        <?php foreach ($urunler as $i => $u): ?><option value="<?= h($u['barcode'] . ' — ' . $u['title']) ?>"></option><?php endforeach; ?>
                    </datalist>
                    <details style="margin-top:4px">
                        <summary class="ipucu" style="cursor:pointer">Şablonu düzenle — {urun_adi} {fiyat} {marka} {barkod} {link}</summary>
                        <textarea id="sablon" style="min-height:90px;margin-top:6px"><?= h($varsayilanSablon) ?></textarea>
                    </details>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Mesaj</label>
                    <textarea name="mesaj" id="mesaj" maxlength="<?= SOSYAL_MESAJ_MAX ?>" placeholder="Gönderi metni…"></textarea>
                </div>
                <div class="form-group">
                    <label>Link (isteğe bağlı)</label>
                    <input type="url" name="link" id="link" placeholder="https://www.trendyol.com/…">
                </div>
                <div class="form-group">
                    <label>Görsel URL (isteğe bağlı — girilirse fotoğraf gönderisi olur, link açıklamaya eklenir)</label>
                    <input type="url" name="gorsel_url" id="gorsel" placeholder="https://cdn.dsmcdn.com/…jpg">
                </div>
                <div class="form-group">
                    <label>Zamanlama</label>
                    <div class="secim">
                        <label><input type="radio" name="zamanlama" value="hemen" checked> ⚡ Hemen paylaş</label>
                        <label><input type="radio" name="zamanlama" value="planla"> 🗓️ İleri tarihe planla</label>
                    </div>
                    <input type="datetime-local" name="planlanan_zaman" id="planlananZaman" style="display:none;margin-top:6px"
                           min="<?= date('Y-m-d\TH:i') ?>" value="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
                    <span class="ipucu">Planlı paylaşımlar sunucudaki cron tarafından zamanı gelince otomatik yayınlanır (saat dilimi: <?= h(date_default_timezone_get()) ?>).</span>
                </div>
                <button class="btn btn-primary" id="gonderBtn">🚀 Paylaş</button>
            </div>
            <div>
                <div class="form-group">
                    <label>Önizleme</label>
                    <div class="onizleme">
                        <div class="ust"><div class="ph"></div><span id="onAd"><?= h($aktifHesaplar[0]['ad']) ?></span></div>
                        <div class="metin" id="onMetin"></div>
                        <img id="onGorsel" alt="" style="display:none">
                        <div class="link" id="onLink" style="display:none"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- ============ Paylaşımlar ============ -->
<div class="card">
    <div class="card-title">
        <span>🗂️ Paylaşımlar</span>
        <span class="ipucu">
            <?php foreach ($durumEtiket as $d => [$et]): if (!empty($sayac[$d])): ?><?= h($et) ?>: <?= (int)$sayac[$d] ?> &nbsp;<?php endif; endforeach; ?>
        </span>
    </div>
    <?php if (!$paylasimlar): ?>
        <div class="bos">Henüz paylaşım yok.</div>
    <?php else: ?>
    <div class="tablo-kap"><table>
        <thead><tr><th>Zaman</th><th>Hesap</th><th></th><th>İçerik</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($paylasimlar as $p): [$et, $cls] = $durumEtiket[$p['durum']] ?? [$p['durum'], 'badge-gray']; ?>
        <tr>
            <td style="white-space:nowrap">
                <?= h(date('d.m.Y H:i', strtotime($p['gonderim_zamani'] ?: $p['planlanan_zaman']))) ?>
                <?php if ($p['durum'] === 'bekliyor' && $p['deneme_sayisi'] > 0): ?><div class="ipucu"><?= (int)$p['deneme_sayisi'] ?>. deneme başarısız, tekrar denenecek</div><?php endif; ?>
            </td>
            <td><?= h($p['hesap_ad']) ?></td>
            <td><?php if ($p['gorsel_url']): ?><img class="kucuk" src="<?= h($p['gorsel_url']) ?>" alt="" loading="lazy"><?php endif; ?></td>
            <td>
                <div class="ozet"><?= h($p['mesaj']) ?: '<span class="ipucu">(metin yok)</span>' ?></div>
                <?php if ($p['link']): ?><div class="ipucu">🔗 <?= h($p['link']) ?></div><?php endif; ?>
                <?php if ($p['hata_mesaji']): ?><div class="hata">⚠️ <?= h($p['hata_mesaji']) ?></div><?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $cls ?>"><?= h($et) ?></span>
                <?php if ($p['dis_post_id']): ?><div style="margin-top:4px"><a href="https://www.facebook.com/<?= h($p['dis_post_id']) ?>" target="_blank" rel="noopener" style="font-size:12px">Gönderiyi aç ↗</a></div><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <?php if ($p['durum'] === 'bekliyor'): ?>
                    <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="act" value="iptal"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">⛔ İptal</button></form>
                <?php endif; ?>
                <?php if (in_array($p['durum'], ['hata', 'iptal'], true)): ?>
                    <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="act" value="tekrar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">🔁 Tekrar</button></form>
                <?php endif; ?>
                <?php if ($p['durum'] !== 'gonderiliyor'): ?>
                    <form method="post" onsubmit="return confirm('Kayıt silinsin mi?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="act" value="sil"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">🗑</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<script>
(function () {
    const form = document.getElementById('paylasimForm');
    if (!form) return;
    const $ = id => document.getElementById(id);
    const URUNLER = <?= json_encode(array_map(fn($u) => [
        'anahtar'     => $u['barcode'] . ' — ' . $u['title'],
        'title'       => $u['title'], 'brand' => $u['brand'], 'barcode' => $u['barcode'],
        'sale_price'  => $u['sale_price'], 'image_url' => $u['image_url'], 'product_url' => $u['product_url'],
    ], $urunler), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // paylasimSablonuUygula() (SosyalMedya.php) ile aynı yer tutucular
    function sablonUygula(sablon, u) {
        const fiyat = u.sale_price !== null && u.sale_price !== ''
            ? Number(u.sale_price).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺' : '';
        return sablon.replaceAll('{urun_adi}', u.title || '').replaceAll('{fiyat}', fiyat)
            .replaceAll('{marka}', u.brand || '').replaceAll('{barkod}', u.barcode || '').replaceAll('{link}', u.product_url || '');
    }

    function onizle() {
        const mesaj = $('mesaj').value, link = $('link').value.trim(), gorsel = $('gorsel').value.trim();
        let metin = mesaj;
        if (gorsel && link && !mesaj.includes(link)) metin = (mesaj + '\n\n' + link).trim();
        $('onMetin').textContent = metin;
        $('onGorsel').style.display = gorsel ? 'block' : 'none';
        if (gorsel) $('onGorsel').src = gorsel;
        $('onLink').style.display = (!gorsel && link) ? 'block' : 'none';
        $('onLink').textContent = link;
        const secili = form.querySelector('input[name="hesap_idler[]"]:checked');
        if (secili) $('onAd').textContent = secili.dataset.ad;
    }

    const ara = $('urunAra');
    if (ara) ara.addEventListener('change', () => {
        const u = URUNLER.find(x => x.anahtar === ara.value);
        if (!u) return;
        $('mesaj').value = sablonUygula($('sablon').value, u);
        $('link').value = u.product_url || '';
        $('gorsel').value = u.image_url || '';
        onizle();
    });

    form.querySelectorAll('input[name="zamanlama"]').forEach(r => r.addEventListener('change', () => {
        const planla = form.zamanlama.value === 'planla';
        $('planlananZaman').style.display = planla ? 'block' : 'none';
        $('gonderBtn').textContent = planla ? '🗓️ Planla' : '🚀 Paylaş';
    }));
    form.addEventListener('input', onizle);
    form.addEventListener('submit', e => {
        if (!form.querySelector('input[name="hesap_idler[]"]:checked')) { e.preventDefault(); alert('En az bir hesap seçin.'); return; }
        $('gonderBtn').disabled = true;
    });
    onizle();
})();
</script>
</body>
</html>
