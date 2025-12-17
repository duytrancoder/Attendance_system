<?php

session_start();

function is_logged_in(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        // Xác định đường dẫn đến login.php dựa trên vị trí file hiện tại
        $script_path = $_SERVER['SCRIPT_NAME'];
        if (strpos($script_path, '/api/') !== false) {
            // Nếu đang ở trong thư mục api, quay về public/login.php
            header('Location: ../public/login.php');
        } else {
            // Nếu đang ở trong public, dùng đường dẫn tương đối
            header('Location: login.php');
        }
        exit;
    }
}

function login(string $username, string $password): bool
{
    // Chỉ có 1 tài khoản duy nhất
    if ($username === 'admin' && $password === '123456') {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

