<?php
// ============================================================
//  Kimlik doğrulama yardımcıları
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

function authUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function authMagaza(): ?array {
    return $_SESSION['magaza'] ?? null;
}

function requireLogin(): void {
    if (!authUser()) {
        header('Location: login.php');
        exit;
    }
    // Kullanıcı admin tarafından pasif edildiyse/silindiyse oturumu kapat;
    // rol/ad değişiklikleri de bir sonraki istekte yansısın.
    if (class_exists('DB')) {
        try {
            $u = DB::row("SELECT id, email, ad_soyad, rol, aktif FROM kullanicilar WHERE id=?", [authUser()['id']]);
            if (!$u || !(int)$u['aktif']) {
                $_SESSION = [];
                session_destroy();
                header('Location: login.php?durum=pasif');
                exit;
            }
            $_SESSION['user'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'ad' => $u['ad_soyad'], 'rol' => $u['rol']];
            // Seçili mağaza silinmiş/pasifleşmiş ya da panelden güncellenmiş olabilir
            if (!empty($_SESSION['magaza']['id'])) {
                $m = DB::row("SELECT * FROM magazalar WHERE id=? AND kullanici_id=? AND aktif=1",
                             [$_SESSION['magaza']['id'], $u['id']]);
                if ($m) $_SESSION['magaza'] = $m; else unset($_SESSION['magaza']);
            }
        } catch (PDOException $e) { /* DB geçici olarak yoksa oturumdakiyle devam */ }
    }
}

function requireAdmin(): void {
    requireLogin();
    if ((authUser()['rol'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

function isAdmin(): bool {
    return (authUser()['rol'] ?? '') === 'admin';
}

function setMagaza(array $magaza): void {
    $_SESSION['magaza'] = $magaza;
}

function logout(): void {
    session_destroy();
    header('Location: login.php');
    exit;
}
