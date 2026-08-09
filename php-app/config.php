<?php
// ============================================================
//  Ayarlar .env dosyasından okunur; yoksa fallback değerler
//  kullanılır. Production'da .env dosyası oluşturun ve
//  bu dosyayı git'e eklemeyin (.gitignore).
//
//  .env örnek: php-app/.env.example dosyasına bakın.
// ============================================================

(function () {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
})();

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Veritabanı ──────────────────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    env('DB_PORT',    '3306'));
define('DB_NAME',    env('DB_NAME',    'trendyol_analiz'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', 'utf8mb4');

// ── Trendyol API (varsayılan boş — DB'deki mağaza kayıtlarından gelir) ──
define('TY_SELLER_ID',  env('TY_SELLER_ID',  ''));
define('TY_API_KEY',    env('TY_API_KEY',    ''));
define('TY_API_SECRET', env('TY_API_SECRET', ''));
define('TY_BASE_URL',   'https://apigw.trendyol.com');
define('TY_USER_AGENT', TY_SELLER_ID . ' - SelfIntegration');
