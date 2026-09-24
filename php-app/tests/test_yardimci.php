<?php
// Testler için küçük yardımcılar (PHPUnit bağımlılığı olmadan).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

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

function testSonucu(): never {
    global $basarisiz, $toplam;
    echo "\n$toplam test, $basarisiz başarısız\n";
    exit($basarisiz ? 1 : 0);
}
