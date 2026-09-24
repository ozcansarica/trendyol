<?php
// ============================================================
//  Facebook OAuth: "Facebook Sayfası Bağla" akışı
//   ?baslat=1  → state üret, Facebook giriş ekranına yönlendir
//   ?code=…    → Facebook dönüşü: token al, sayfaları kaydet
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

function geriDon(string $tip, string $mesaj): never {
    $_SESSION['sosyal_bildirim'] = [$tip, $mesaj];
    header('Location: sosyal.php');
    exit;
}

/** Facebook uygulamasındaki "Geçerli OAuth Yönlendirme URI'leri" ile birebir aynı olmalı. */
function facebookYonlendirmeUrl(): string {
    $taban = rtrim(env('APP_URL'), '/');
    if ($taban === '') {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
        $taban = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
               . rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    }
    return $taban . '/facebook_callback.php';
}

try {
    sosyalSemaKur();
    if (!FacebookGraph::yapilandirildi()) geriDon('hata', 'Facebook uygulaması yapılandırılmamış (.env: FB_APP_ID, FB_APP_SECRET).');
    if (!sosyalAnahtarVarMi())            geriDon('hata', '.env içinde APP_KEY tanımlı değil; token\'lar şifrelenemez.');
    $fb = FacebookGraph::ayarlardan();

    if (isset($_GET['baslat'])) {
        $_SESSION['fb_oauth_state'] = bin2hex(random_bytes(16));
        header('Location: ' . $fb->girisUrl(facebookYonlendirmeUrl(), $_SESSION['fb_oauth_state']));
        exit;
    }

    $beklenen = $_SESSION['fb_oauth_state'] ?? '';
    unset($_SESSION['fb_oauth_state']);
    if ($beklenen === '' || !hash_equals($beklenen, (string)($_GET['state'] ?? ''))) {
        geriDon('hata', 'Geçersiz ya da süresi dolmuş bağlantı isteği. Lütfen tekrar deneyin.');
    }
    if (isset($_GET['error'])) {
        geriDon('hata', 'Facebook bağlantısı iptal edildi: ' . ($_GET['error_description'] ?? $_GET['error']));
    }
    $kod = (string)($_GET['code'] ?? '');
    if ($kod === '') geriDon('hata', 'Facebook yetkilendirme kodu gelmedi.');

    $kullaniciToken = $fb->koduTokenaCevir($kod, facebookYonlendirmeUrl());
    $sayfalar = $fb->sayfalar($kullaniciToken);
    if (!$sayfalar) {
        geriDon('hata', 'Paylaşım yetkiniz olan bir Facebook sayfası bulunamadı. Bağlanırken sayfaları seçtiğinizden emin olun.');
    }
    $n = facebookSayfalariniKaydet($kullaniciId, $sayfalar);
    geriDon('basari', "✅ $n Facebook sayfası bağlandı: " . implode(', ', array_column($sayfalar, 'ad')));
} catch (Throwable $e) {
    error_log('facebook_callback: ' . $e->getMessage());
    geriDon('hata', 'Facebook bağlantısı başarısız: ' . $e->getMessage());
}
