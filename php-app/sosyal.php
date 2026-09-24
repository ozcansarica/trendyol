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
require_once __DIR__ . '/arayuz.php';
requireLogin();

$kullaniciId = (int)authUser()['id'];
$magaza      = authMagaza();
$csrf        = csrfToken();

try {
    tumSemayiKur();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Veritabanı hatası: ' . h($e->getMessage()));
}
webCronTetikle();

// ---- Aksiyonlar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfDogrula()) yonlendir('sosyal.php', 'hata', 'Oturum doğrulaması başarısız, sayfayı yenileyip tekrar deneyin.');
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
        if ($hatalar) yonlendir('sosyal.php', 'hata', implode(' ', $hatalar));

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
            yonlendir('sosyal.php', $tip, "Paylaşım sonucu: {$ozet['gonderildi']} yayınlandı"
                . ($ozet['ertelendi'] ? ", {$ozet['ertelendi']} tekrar denenecek" : '')
                . ($ozet['hata'] ? ", {$ozet['hata']} başarısız (ayrıntı aşağıda)" : '') . '.');
        }
        yonlendir('sosyal.php', 'basari', '🗓️ ' . count($yeniIdler) . ' paylaşım ' . date('d.m.Y H:i', strtotime($zaman)) . ' için planlandı.');
    }
    elseif ($act === 'iptal') {
        DB::exec("UPDATE sosyal_paylasimlar SET durum='iptal' WHERE id=? AND kullanici_id=? AND durum='bekliyor'", [$id, $kullaniciId]);
        yonlendir('sosyal.php', 'basari', 'Paylaşım iptal edildi.');
    }
    elseif ($act === 'tekrar') {
        DB::exec("UPDATE sosyal_paylasimlar SET durum='bekliyor', deneme_sayisi=0, hata_mesaji=NULL, planlanan_zaman=?
                  WHERE id=? AND kullanici_id=? AND durum IN ('hata','iptal')", [simdi(), $id, $kullaniciId]);
        yonlendir('sosyal.php', 'basari', 'Paylaşım tekrar kuyruğa alındı; bir dakika içinde yayınlanır.');
    }
    elseif ($act === 'sil') {
        DB::exec("DELETE FROM sosyal_paylasimlar WHERE id=? AND kullanici_id=? AND durum<>'gonderiliyor'", [$id, $kullaniciId]);
        yonlendir('sosyal.php', 'basari', 'Paylaşım kaydı silindi (Facebook\'taki gönderi etkilenmez).');
    }
    elseif ($act === 'hesap_durum') {
        DB::exec("UPDATE sosyal_hesaplar SET durum=IF(durum='aktif','pasif','aktif'), guncelleme=?
                  WHERE id=? AND kullanici_id=? AND durum IN ('aktif','pasif')", [simdi(), $id, $kullaniciId]);
        yonlendir('sosyal.php', 'basari', 'Hesap durumu güncellendi.');
    }
    elseif ($act === 'hesap_sil') {
        DB::exec("DELETE FROM sosyal_hesaplar WHERE id=? AND kullanici_id=?", [$id, $kullaniciId]);
        yonlendir('sosyal.php', 'basari', 'Hesap kaldırıldı; bekleyen paylaşımları da silindi.');
    }
    yonlendir('sosyal.php', 'hata', 'Bilinmeyen işlem.');
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
<?php panelBasla('📣 Sosyal Paylaşım', ayar('site_adi'), kullaniciMenusu(), 'sosyal'); ?>
<style>
.hesaplar{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
.hesap{display:flex;gap:10px;align-items:center;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px;}
.hesap img,.hesap .ph{width:40px;height:40px;border-radius:50%;flex-shrink:0;background:var(--fb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;}
.hesap .bilgi{flex:1;min-width:0;}
.hesap .ad{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.hesap .alt{font-size:11px;color:var(--text2);margin-top:2px;}
.hesap form{display:inline;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
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
td .ozet{max-width:380px;white-space:pre-wrap;word-break:break-word;max-height:4.2em;overflow:hidden;}
td .hata{color:#ff8a7d;font-size:11px;margin-top:4px;max-width:380px;}
td img.kucuk{width:48px;height:48px;object-fit:cover;border-radius:6px;}
td form{display:inline;}
@media(max-width:820px){.grid2{grid-template-columns:1fr;}}
</style>

<div class="page-title">📣 Sosyal <span>Paylaşım</span></div>

<?php if (!$fbHazir): ?>
<div class="alert alert-info">
    <strong>Facebook bağlantısı henüz yapılandırılmamış.</strong>
    <?php if (isAdmin()): ?>
        <a href="admin.php?s=ayarlar">Admin → Sistem Ayarları</a>'ndan Facebook App ID ve App Secret'ı girin; adımlar orada anlatılıyor.
    <?php else: ?>
        Site yöneticisinin Facebook uygulama bilgilerini girmesi gerekiyor.
    <?php endif; ?>
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
                    <span class="ipucu">Planlı paylaşımlar zamanı gelince otomatik yayınlanır (saat dilimi: <?= h(date_default_timezone_get()) ?>).</span>
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
<?php panelBitir();
