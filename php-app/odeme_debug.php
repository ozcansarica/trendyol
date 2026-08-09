<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireAdmin(); // debug sayfası yalnızca adminlere açık
$magaza = authMagaza(); $magazaId = (int)($magaza['id'] ?? 0);
header('Content-Type: text/plain; charset=utf-8');

echo "=== Reklam baslangic_tarihi örnekleri ===\n";
$rows = DB::rows("SELECT reklam_adi, baslangic_tarihi, harcama FROM reklamlar WHERE magaza_id=? ORDER BY baslangic_tarihi DESC LIMIT 10", [$magazaId]);
foreach ($rows as $r) echo "  [{$r['baslangic_tarihi']}] harcama={$r['harcama']} | {$r['reklam_adi']}\n";

echo "\n=== Mayıs 2026 reklam (DD.MM.YYYY) ===\n";
$t1 = DB::scalar("SELECT COALESCE(SUM(harcama),0) FROM reklamlar WHERE magaza_id=? AND SUBSTRING(baslangic_tarihi,4,2)='05' AND SUBSTRING(baslangic_tarihi,7,4)='2026'", [$magazaId]);
echo "  Toplam: $t1\n";

echo "\n=== Mayıs 2026 reklam (YYYY-MM-DD) ===\n";
$t2 = DB::scalar("SELECT COALESCE(SUM(harcama),0) FROM reklamlar WHERE magaza_id=? AND SUBSTRING(baslangic_tarihi,6,2)='05' AND SUBSTRING(baslangic_tarihi,1,4)='2026'", [$magazaId]);
echo "  Toplam: $t2\n";
