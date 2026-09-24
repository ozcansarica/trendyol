<?php
// ============================================================
//  Sosyal Paylaşım — DB gerektirmeyen mantık testleri.
//  Çalıştır: php php-app/tests/sosyal_test.php
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../SosyalMedya.php';

$basarisiz = 0; $toplam = 0;
function test(string $ad, callable $fn): void {
    global $basarisiz, $toplam;
    $toplam++;
    try { $fn(); echo "✔ $ad\n"; }
    catch (Throwable $e) { $basarisiz++; echo "✘ $ad\n   " . $e->getMessage() . "\n"; }
}
function esit($beklenen, $gercek, string $not = ''): void {
    if ($beklenen !== $gercek) {
        throw new Exception(($not ? "$not: " : '') . 'beklenen ' . var_export($beklenen, true) . ', gelen ' . var_export($gercek, true));
    }
}

/** Sahte HTTP: çağrıları kaydeder, sıradaki yanıtı döner. */
function sahteHttp(array $yanitlar, array &$cagrilar): callable {
    return function (string $metot, string $url, array $params) use (&$yanitlar, &$cagrilar) {
        $cagrilar[] = compact('metot', 'url', 'params');
        $y = array_shift($yanitlar);
        return [$y[0], json_encode($y[1])];
    };
}

// ---- Doğrulama ----
test('boş paylaşım reddedilir', function () {
    $h = paylasimDogrula(['mesaj' => '  ', 'hesap_idler' => [1]]);
    esit(1, count($h));
});
test('hesap seçilmezse hata', function () {
    esit(['En az bir hesap seçilmelidir.'], paylasimDogrula(['mesaj' => 'Merhaba', 'hesap_idler' => []]));
});
test('http olmayan link reddedilir', function () {
    $h = paylasimDogrula(['mesaj' => 'x', 'link' => 'javascript:alert(1)', 'hesap_idler' => [1]]);
    esit(1, count($h));
});
test('geçerli paylaşım', function () {
    esit([], paylasimDogrula(['mesaj' => 'x', 'link' => 'https://a.com/b', 'gorsel_url' => 'https://c.com/d.jpg', 'hesap_idler' => [1]]));
});

// ---- Planlanan zaman ----
test('boş zaman → hemen', function () {
    esit('2026-09-24 10:00:00', planlananZamanCoz('', '2026-09-24 10:00:00'));
});
test('ileri zaman korunur', function () {
    esit('2026-09-25 15:30:00', planlananZamanCoz('2026-09-25T15:30', '2026-09-24 10:00:00'));
});
test('geçmiş zaman → hemen', function () {
    esit('2026-09-24 10:00:00', planlananZamanCoz('2026-09-20T15:30', '2026-09-24 10:00:00'));
});
test('geçersiz zaman → null', function () {
    esit(null, planlananZamanCoz('yarın', '2026-09-24 10:00:00'));
});

// ---- Şablon ----
test('ürün şablonu', function () {
    $u = ['title' => 'Kupa', 'sale_price' => '1249.9', 'brand' => 'X', 'barcode' => '869', 'product_url' => 'https://t.co/1'];
    esit("Kupa — 1.249,90 ₺ (X/869) https://t.co/1", paylasimSablonuUygula('{urun_adi} — {fiyat} ({marka}/{barkod}) {link}', $u));
});

// ---- Facebook isteği ----
test('metin + link → /feed', function () {
    esit(['feed', ['message' => 'Merhaba', 'link' => 'https://a.com']], facebookIstegiOlustur(['mesaj' => 'Merhaba', 'link' => 'https://a.com']));
});
test('görsel → /photos, link açıklamaya eklenir', function () {
    esit(['photos', ['url' => 'https://c/d.jpg', 'caption' => "Merhaba\n\nhttps://a.com"]],
        facebookIstegiOlustur(['mesaj' => 'Merhaba', 'link' => 'https://a.com', 'gorsel_url' => 'https://c/d.jpg']));
});
test('link mesajda zaten varsa tekrar eklenmez', function () {
    esit(['photos', ['url' => 'https://c/d.jpg', 'caption' => 'Bak: https://a.com']],
        facebookIstegiOlustur(['mesaj' => 'Bak: https://a.com', 'link' => 'https://a.com', 'gorsel_url' => 'https://c/d.jpg']));
});

// ---- Yeniden deneme ----
test('geçici hata: artan gecikmeyle tekrar', function () {
    esit(['durum' => 'bekliyor', 'gecikme_dk' => 5],  yenidenDenemeKarari(1, false));
    esit(['durum' => 'bekliyor', 'gecikme_dk' => 10], yenidenDenemeKarari(2, false));
    esit(['durum' => 'hata', 'gecikme_dk' => 0],      yenidenDenemeKarari(SOSYAL_MAX_DENEME, false));
});
test('kalıcı hata: tekrar yok', function () {
    esit(['durum' => 'hata', 'gecikme_dk' => 0], yenidenDenemeKarari(1, true));
});
test('FB hata sınıflandırma', function () {
    esit(true,  (new FacebookHata('x', 190))->tokenHatasi());
    esit(true,  (new FacebookHata('x', 200))->kalici());
    esit(false, (new FacebookHata('x', 2))->kalici(), 'geçici servis hatası');
    esit(false, (new FacebookHata('ağ'))->kalici(), 'bağlantı hatası');
});

// ---- Token şifreleme ----
test('şifrele / çöz', function () {
    $s = tokenSifrele('EAAB-gizli', 'anahtar1');
    esit(true, str_starts_with($s, 'v1:'));
    esit(false, str_contains($s, 'EAAB'));
    esit('EAAB-gizli', tokenCoz($s, 'anahtar1'));
});
test('yanlış anahtarla çözülemez', function () {
    $s = tokenSifrele('EAAB-gizli', 'anahtar1');
    try { tokenCoz($s, 'anahtar2'); } catch (RuntimeException $e) { return; }
    throw new Exception('istisna bekleniyordu');
});

// ---- Graph istemcisi ----
test('giriş URL\'si izinleri ve state\'i taşır', function () {
    $fb = new FacebookGraph('123', 'sır', 'v23.0', fn() => [200, '{}']);
    $u = $fb->girisUrl('https://site/facebook_callback.php', 'abc');
    esit(true, str_starts_with($u, 'https://www.facebook.com/v23.0/dialog/oauth?'));
    parse_str(parse_url($u, PHP_URL_QUERY), $q);
    esit('abc', $q['state']);
    esit('pages_show_list,pages_manage_posts,pages_read_engagement', $q['scope']);
});
test('sayfalar: paylaşım yetkisi olmayan sayfa elenir', function () {
    $c = [];
    $fb = new FacebookGraph('123', 'sır', 'v23.0', sahteHttp([[200, ['data' => [
        ['id' => '1', 'name' => 'A', 'access_token' => 'tA', 'tasks' => ['CREATE_CONTENT', 'MANAGE'], 'picture' => ['data' => ['url' => 'p']]],
        ['id' => '2', 'name' => 'B', 'access_token' => 'tB', 'tasks' => ['ANALYZE']],
    ]]]], $c));
    $s = $fb->sayfalar('kullanici');
    esit(1, count($s));
    esit(['id' => '1', 'ad' => 'A', 'access_token' => 'tA', 'resim_url' => 'p'], $s[0]);
    esit(hash_hmac('sha256', 'kullanici', 'sır'), $c[0]['params']['appsecret_proof']);
});
test('sayfada paylaş: post_id döner', function () {
    $c = [];
    $fb = new FacebookGraph('123', 'sır', 'v23.0', sahteHttp([[200, ['id' => '9', 'post_id' => '1_9']]], $c));
    esit('1_9', $fb->sayfadaPaylas('1', 'tA', ['mesaj' => 'x', 'gorsel_url' => 'https://c/d.jpg']));
    esit('POST', $c[0]['metot']);
    esit('https://graph.facebook.com/v23.0/1/photos', $c[0]['url']);
});
test('Graph hatası FacebookHata olarak fırlar', function () {
    $c = [];
    $fb = new FacebookGraph('123', 'sır', 'v23.0', sahteHttp([[400, ['error' => ['message' => 'Session expired', 'code' => 190]]]], $c));
    try { $fb->sayfadaPaylas('1', 'tA', ['mesaj' => 'x']); }
    catch (FacebookHata $e) { esit(190, $e->fbKod); esit(true, $e->tokenHatasi()); return; }
    throw new Exception('istisna bekleniyordu');
});

echo "\n$toplam test, $basarisiz başarısız\n";
exit($basarisiz ? 1 : 0);
