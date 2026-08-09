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
