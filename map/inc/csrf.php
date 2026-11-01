<?php
/**
 * NFC Event Tag Scoring - CSRF Protection
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * دریافت یا ایجاد توکن امن CSRF برای نشست جاری
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * خروجی تگ اینپوت مخفی برای فرم‌های HTML
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * اعتبارسنجی توکن ارسالی در درخواست‌های POST
 */
function csrf_verify($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * بررسی اجباری توکن CSRF و توقف اجرای اسکریپت در صورت نامعتبر بودن
 */
function require_csrf() {
    if (!csrf_verify()) {
        // معمولاً به دلیل باز ماندن طولانی صفحه و منقضی شدن نشست رخ می‌دهد
        $back = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : path_url('/admin/events');
        show_message_page(
            403,
            'صفحه منقضی شده است',
            'این صفحه مدت زیادی باز مانده بود و اعتبار فرم آن به پایان رسیده است. لطفاً صفحه را دوباره باز کرده و عملیات را تکرار کنید.',
            '⏳',
            $back,
            'باز کردن دوباره صفحه'
        );
    }
}
