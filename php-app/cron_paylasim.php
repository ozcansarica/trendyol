<?php
// ============================================================
//  Otomatik paylaşım zamanlayıcısı — zamanı gelen paylaşımları yayınlar.
//  Üç yoldan çalışabilir (hiçbiri zorunlu değil; varsayılan olarak
//  site ziyaretleri de kuyruğu işler — bkz. webCronTetikle()):
//
//   1) Sunucu cron'u:  * * * * * php /yol/cron_paylasim.php
//   2) Harici cron servisi (cron-job.org vb.) bu URL'yi dakikada bir çağırır:
//        https://site/cron_paylasim.php?anahtar=…   (anahtar admin panelinde)
//   3) Admin paneli → Sistem Ayarları → "Kuyruğu şimdi çalıştır"
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/SosyalMedya.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $beklenen = webCronAnahtari();
    if ($beklenen === '' || !hash_equals($beklenen, (string)($_GET['anahtar'] ?? ''))) {
        http_response_code(404);
        exit;
    }
    ignore_user_abort(true);
    set_time_limit(120);
}

// Önceki çalıştırma bitmeden yenisi başlamasın
$kilit = fopen(sys_get_temp_dir() . '/trendyol_paylasim_cron.lock', 'c');
if (!$kilit || !flock($kilit, LOCK_EX | LOCK_NB)) {
    echo '[' . simdi() . "] Önceki çalıştırma sürüyor, atlandı.\n";
    exit(0);
}

try {
    sosyalSemaKur();
    $ozet = kuyrukCalistir($cli ? 'cron' : 'web-cron', 50);
    if (array_sum($ozet) > 0 || !$cli) {
        printf("[%s] gönderildi=%d, ertelendi=%d, hata=%d\n",
            simdi(), $ozet['gonderildi'], $ozet['ertelendi'], $ozet['hata']);
    }
} catch (Throwable $e) {
    if ($cli) fwrite(STDERR, '[' . simdi() . '] HATA: ' . $e->getMessage() . "\n");
    else { http_response_code(500); echo "HATA\n"; error_log('cron_paylasim: ' . $e->getMessage()); }
    exit(1);
} finally {
    flock($kilit, LOCK_UN);
}
