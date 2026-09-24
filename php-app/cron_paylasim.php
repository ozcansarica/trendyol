<?php
// ============================================================
//  Otomatik paylaşım cron'u — zamanı gelen paylaşımları yayınlar.
//  Yalnızca komut satırından çalışır. Sunucuda her dakika:
//
//    * * * * * php /var/www/trendyol/cron_paylasim.php >> /var/log/paylasim.log 2>&1
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/SosyalMedya.php';

// Önceki çalıştırma bitmeden yenisi başlamasın
$kilit = fopen(sys_get_temp_dir() . '/trendyol_paylasim_cron.lock', 'c');
if (!$kilit || !flock($kilit, LOCK_EX | LOCK_NB)) {
    echo '[' . simdi() . "] Önceki çalıştırma sürüyor, atlandı.\n";
    exit(0);
}

try {
    sosyalSemaKur();
    $ozet = paylasimKuyrugunuIsle(null, 50);
    if (array_sum($ozet) > 0) {
        printf("[%s] gönderildi=%d, ertelendi=%d, hata=%d\n",
            simdi(), $ozet['gonderildi'], $ozet['ertelendi'], $ozet['hata']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[' . simdi() . '] HATA: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($kilit, LOCK_UN);
}
