<?php
/**
 * NFC Event Tag Scoring - Admin Authentication & Session Management
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * راه‌اندازی ایمن نشست PHP
 */
function init_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookiePath = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/';
    $secure = is_ssl();

    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }

    // تنظیمات امنیتی کوکی نشست
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    session_start();

    // بررسی انقضای نشست پس از ۸ ساعت بی‌فعالیتی (28800 ثانیه)
    if (!empty($_SESSION['admin_logged_in'])) {
        $timeout = 28800; // 8 hours
        $now = time();
        if (isset($_SESSION['admin_last_activity']) && ($now - $_SESSION['admin_last_activity']) > $timeout) {
            unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_last_activity']);
            flash_set('auth_error', 'نشست شما به دلیل بی‌فعالیتی منقضی شد. لطفاً دوباره وارد شوید.', 'warning');
        } else {
            $_SESSION['admin_last_activity'] = $now;
        }
    }
}

/**
 * بررسی لاگین بودن ادمین
 */
function is_admin_logged_in() {
    return !empty($_SESSION['admin_logged_in']);
}

/**
 * محافظت از مسیرهای ادمین
 */
function require_admin() {
    if (!is_admin_logged_in()) {
        $loginUrl = path_url('/admin/login');
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * احراز هویت و ورود ادمین با کنترل Rate Limit
 *
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array [success => bool, message => string]
 */
function admin_login($pdo, $username, $password) {
    $ip = get_client_ip();
    $cleanUsername = strtolower(trim($username));
    $rateKey = $ip . '|' . $cleanUsername;

    // حداکثر ۵ تلاش در ۱۵ دقیقه (۹۰۰ ثانیه)
    if (!check_rate_limit($pdo, 'admin_login', $rateKey, 5, 900)) {
        return [
            'success' => false,
            'message' => 'تعداد دفعات تلاش ناموفق بیش از حد مجاز است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.',
        ];
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => trim($username)]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        // احراز هویت موفق: بازسازی شناسه نشست
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_last_activity'] = time();

        return [
            'success' => true,
            'message' => 'ورود با موفقیت انجام شد.',
        ];
    }

    // ثبت تلاش ناموفق
    record_rate_limit($pdo, 'admin_login', $rateKey);

    return [
        'success' => false,
        'message' => 'نام کاربری یا رمز عبور اشتباه است.',
    ];
}

/**
 * خروج ادمین
 */
function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * تغییر رمز عبور ادمین
 */
function admin_change_password($pdo, $adminId, $currentPassword, $newPassword) {
    $len = function_exists('mb_strlen') ? mb_strlen($newPassword, 'UTF-8') : strlen($newPassword);
    if ($len < 8) {
        return [
            'success' => false,
            'message' => 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.',
        ];
    }

    $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $stmtFallback = $pdo->query('SELECT id, password_hash FROM admins ORDER BY id ASC LIMIT 1');
        $admin = $stmtFallback->fetch();
        if ($admin) {
            $adminId = (int)$admin['id'];
        }
    }

    if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
        return [
            'success' => false,
            'message' => 'رمز عبور فعلی نادرست است.',
        ];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upStmt = $pdo->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
    $upStmt->execute(['hash' => $newHash, 'id' => $adminId]);

    return [
        'success' => true,
        'message' => 'رمز عبور با موفقیت به‌روزرسانی شد.',
    ];
}
