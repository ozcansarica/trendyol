<?php
// ============================================================
//  Sistem çekirdeği — DB gerektirmeyen testler.
//  Çalıştır: php php-app/tests/sistem_test.php
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../sistem.php';
require_once __DIR__ . '/test_yardimci.php';

test('.env: var olan anahtar güncellenir, yorumlar korunur', function () {
    $once = "# Veritabanı\nDB_HOST=eski\nDB_NAME=trendyol\n";
    esit("# Veritabanı\nDB_HOST=yeni\nDB_NAME=trendyol\n", envIcerigiGuncelle($once, ['DB_HOST' => 'yeni']));
});
test('.env: olmayan anahtar sona eklenir', function () {
    esit("A=1\nAPP_KEY=abc\n", envIcerigiGuncelle("A=1\n", ['APP_KEY' => 'abc']));
});
test('.env: boş içerikten oluşturulur', function () {
    esit("DB_HOST=localhost\nDB_PASS=\n", envIcerigiGuncelle('', ['DB_HOST' => 'localhost', 'DB_PASS' => '']));
});
test('.env: satır sonu enjeksiyonu engellenir', function () {
    esit("DB_PASS=xAPP_KEY=kotu\n", envIcerigiGuncelle('', ['DB_PASS' => "x\nAPP_KEY=kotu"]));
});
test('.env: config.php okuyucusuyla uyumlu (= içeren şifre)', function () {
    $satir = trim(envIcerigiGuncelle('', ['DB_PASS' => 'a=b=c']));
    [$k, $v] = explode('=', $satir, 2);
    esit(['DB_PASS', 'a=b=c'], [$k, $v]);
});

test('şifre: kısa şifre reddedilir', function () {
    esit('Şifre en az 8 karakter olmalı.', sifreDogrula('1234567', '1234567'));
});
test('şifre: eşleşmeyen tekrar reddedilir', function () {
    esit('Şifreler eşleşmiyor.', sifreDogrula('12345678', '12345679'));
});
test('şifre: geçerli', function () {
    esit(null, sifreDogrula('güçlüŞifre1', 'güçlüŞifre1'));
});

test('maskele: son 4 karakter görünür', function () {
    esit('••••cdef', maskele('abcdef'));
    esit('', maskele(''));
    esit('', maskele(null));
});

test('ayar: DB yoksa varsayılana düşer', function () {
    $GLOBALS['__ayarlar'] = []; // DB'den hiçbir şey gelmemiş gibi
    esit('Trendyol Analiz', ayar('site_adi'));
    esit('1', ayar('kayit_acik'));
    esit('varsayilan', ayar('bilinmeyen_anahtar', 'varsayilan'));
});
test('ayar: gizli değer şifreli saklanır, çözülerek okunur', function () {
    $GLOBALS['__ayarlar'] = ['fb_app_secret' => tokenSifrele('gizli123', 'k')];
    esit('', ayar('fb_app_secret'), 'yanlış anahtarla çözülemez → boş');
});
test('ayar: panel değeri .env ve varsayılandan önce gelir', function () {
    $GLOBALS['__ayarlar'] = ['site_adi' => 'Mağazam'];
    esit('Mağazam', ayar('site_adi'));
    esit('panel', ayarKaynagi('site_adi'));
    $GLOBALS['__ayarlar'] = ['site_adi' => ''];
    esit('Trendyol Analiz', ayar('site_adi'));
    esit('varsayılan', ayarKaynagi('site_adi'));
});
test('ayar: tanımlı her ayarın 4 alanı var', function () {
    foreach (SISTEM_AYARLARI as $k => $t) {
        esit(4, count($t), $k);
        esit(true, in_array($t[3], ['metin', 'gizli', 'evethayir', 'saat_dilimi'], true), $k);
    }
});

testSonucu();
