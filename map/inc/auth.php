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

    // ذخیره نشست‌ها در پوشه محافظت‌شده data/sessions با عمر ۱۲ ساعت.
    // در بسیاری از هاست‌ها نشست‌های پیش‌فرض PHP پس از ~۲۴ دقیقه بی‌فعالیتی پاک می‌شوند
    // و ادمین یا تیم‌ها وسط مسابقه با «صفحه منقضی شده» مواجه می‌شوند.
    $sessDir = dirname(__DIR__) . '/data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0700, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
        @ini_set('session.gc_probability', '1');
        @ini_set('session.gc_divisor', '200');
    }
    @ini_set('session.gc_maxlifetime', '43200');
    @ini_set('session.use_strict_mode', '1');

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
 * نقش‌های کاربری سامانه
 * admin: دسترسی کامل | placer: نصاب (فقط نصب، تأیید و جمع‌آوری تگ‌ها؛ بدون دسترسی به امتیازها و تنظیمات)
 */
function staff_roles() {
    return [
        'admin' => 'مدیر',
        'placer' => 'نصاب',
    ];
}

/**
 * کاربر لاگین‌شده فعلی (با بررسی زنده فعال بودن حساب و نقش از پایگاه‌داده در هر درخواست)
 *
 * اگر حساب در این فاصله غیرفعال یا حذف شده باشد، کاربر فوراً از سامانه خارج می‌شود.
 *
 * @return array|null
 */
function current_user($pdo) {
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    if (!is_admin_logged_in()) {
        $cached = null;
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, username, display_name, role, is_active FROM admins WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['admin_id']]);
    $user = $stmt->fetch();
    $stmt->closeCursor();

    if (!$user || (int)$user['is_active'] !== 1) {
        admin_logout();
        init_session();
        flash_set('auth_error', 'حساب کاربری شما غیرفعال شده است.', 'error');
        header('Location: ' . path_url('/admin/login'));
        exit;
    }
    if (!array_key_exists($user['role'], staff_roles())) {
        $user['role'] = 'placer';
    }
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['admin_username'] = $user['display_name'] ?: $user['username'];
    $cached = $user;
    return $user;
}

/**
 * آیا کاربر فعلی نقش «مدیر» دارد؟ (بر اساس نقشی که current_user در همین درخواست تازه کرده است)
 */
function is_admin_role() {
    return is_admin_logged_in() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

/**
 * صفحه شروع هر نقش پس از ورود
 */
function staff_home_url() {
    return is_admin_role() ? path_url('/admin/events') : path_url('/admin/field');
}

/**
 * محافظت از مسیرهای مشترک کادر برگزاری (مدیر و نصاب)
 */
function require_staff($pdo, $allowPendingPasswordChange = false) {
    if (!is_admin_logged_in()) {
        header('Location: ' . path_url('/admin/login'));
        exit;
    }
    $user = current_user($pdo);
    if (!$allowPendingPasswordChange && admin_must_change_password()) {
        flash_set('warning', 'برای امنیت سامانه، ابتدا رمز عبور خود را تغییر دهید.', 'warning');
        header('Location: ' . path_url('/admin/password'));
        exit;
    }
    return $user;
}

/**
 * محافظت از مسیرهای ادمین
 */
function require_admin($allowPendingPasswordChange = false) {
    if (!is_admin_logged_in()) {
        $loginUrl = path_url('/admin/login');
        header('Location: ' . $loginUrl);
        exit;
    }
    if (!$allowPendingPasswordChange && admin_must_change_password()) {
        flash_set('warning', 'برای امنیت سامانه، ابتدا رمز عبور پیش‌فرض را تغییر دهید.', 'warning');
        header('Location: ' . path_url('/admin/password'));
        exit;
    }
    // نصاب‌ها به بخش‌های مدیریتی (امتیازها، تنظیمات، کاربران) دسترسی ندارند
    if (!is_admin_role()) {
        flash_set('warning', 'این بخش فقط برای مدیران در دسترس است.', 'warning');
        header('Location: ' . path_url('/admin/field'));
        exit;
    }
}

/**
 * آیا ادمین باید پیش از ادامه کار رمز عبور خود را تغییر دهد؟
 */
function admin_must_change_password() {
    return !empty($_SESSION['admin_must_change_password']);
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

    $stmt = $pdo->prepare('SELECT id, username, display_name, role, is_active, password_hash, must_change_password FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => trim($username)]);
    $admin = $stmt->fetch();
    $stmt->closeCursor();

    if ($admin && password_verify($password, $admin['password_hash']) && (int)$admin['is_active'] !== 1) {
        return [
            'success' => false,
            'message' => 'حساب کاربری شما غیرفعال است. با مدیر رویداد تماس بگیرید.',
        ];
    }

    if ($admin && password_verify($password, $admin['password_hash'])) {
        // احراز هویت موفق: بازسازی شناسه نشست
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['display_name'] ?: $admin['username'];
        $_SESSION['admin_role'] = array_key_exists($admin['role'], staff_roles()) ? $admin['role'] : 'placer';
        $_SESSION['admin_last_activity'] = time();
        // اجبار به تغییر رمز پیش‌فرض یا رمزی که مدیر سامانه تعیین کرده باشد
        $_SESSION['admin_must_change_password'] = ((int)$admin['must_change_password'] === 1 || $password === 'admin1234');

        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $re = $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
            $re->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int)$admin['id']]);
        }

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
    $stmt->closeCursor();

    if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
        return [
            'success' => false,
            'message' => 'رمز عبور فعلی نادرست است.',
        ];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (hash_equals((string)$currentPassword, (string)$newPassword) || $newPassword === 'admin1234') {
        return [
            'success' => false,
            'message' => 'رمز عبور جدید باید با رمز فعلی و رمز پیش‌فرض متفاوت باشد.',
        ];
    }

    $upStmt = $pdo->prepare('UPDATE admins SET password_hash = :hash, must_change_password = 0 WHERE id = :id');
    $upStmt->execute(['hash' => $newHash, 'id' => (int)$adminId]);
    $_SESSION['admin_must_change_password'] = false;
    session_regenerate_id(true);

    return [
        'success' => true,
        'message' => 'رمز عبور با موفقیت به‌روزرسانی شد.',
    ];
}
