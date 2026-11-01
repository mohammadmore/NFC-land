<?php
/**
 * NFC Event Tag Scoring - Global Helper Functions
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * پاک‌سازی رشته برای جلوگیری از XSS
 */
function e($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * تبدیل ارقام انگلیسی به فارسی برای نمایش در رابط کاربری
 */
function fa_num($input) {
    if ($input === null) {
        return '';
    }
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace($en, $fa, (string)$input);
}

/**
 * کدگذاری Base64 سازگار با URL بدون کاراکترهای پدینگ (=)
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * ساخت کد تصادفی ۲۲ کاراکتری غیرقابل‌حدس برای تگ NFC
 */
function generate_tag_code() {
    return base64url_encode(random_bytes(16));
}

/**
 * ساخت کد تگ یکتا در دیتابیس با تضمین عدم تکرار
 */
function generate_unique_tag_code($pdo) {
    $maxTries = 10;
    for ($i = 0; $i < $maxTries; $i++) {
        $code = generate_tag_code();
        $stmt = $pdo->prepare('SELECT id FROM tags WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    throw new RuntimeException('خطا در تولید کد تصادفی یکتا برای تگ.');
}

/**
 * تشخیص پروتکل امن HTTPS
 */
function is_ssl() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/**
 * ساخت آدرس کامل با نام دامنه و BASE_PATH
 */
function url($path = '') {
    $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
    $sub = trim(defined('BASE_PATH') ? BASE_PATH : '', '/');
    $subPart = $sub !== '' ? '/' . $sub : '';
    $cleanPath = ltrim($path, '/');
    return $base . $subPart . ($cleanPath !== '' ? '/' . $cleanPath : '');
}

/**
 * ساخت مسیر نسبی داخلی برای پیوندها
 */
function path_url($subpath = '') {
    $basePath = rtrim(defined('BASE_PATH') ? BASE_PATH : '/map', '/');
    $cleanSub = ltrim($subpath, '/');
    return $basePath . ($cleanSub !== '' ? '/' . $cleanSub : '');
}

/**
 * دریافت آی‌پی کلاینت
 */
function get_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    return preg_replace('/[^0-9a-fA-F:.]/', '', $ip);
}

/**
 * تبدیل جایگاه عددی به کلمات ترتیبی فارسی
 */
function rank_to_words_fa($rank) {
    $words = [
        1 => 'اولین',
        2 => 'دومین',
        3 => 'سومین',
        4 => 'چهارمین',
        5 => 'پنجمین',
        6 => 'ششمین',
        7 => 'هفتمین',
        8 => 'هشتمین',
        9 => 'نهمین',
        10 => 'دهمین',
    ];
    $rank = (int)$rank;
    return isset($words[$rank]) ? $words[$rank] : (fa_num($rank) . '-مین');
}

/**
 * مدیریت پیام‌های موقت نشست (Flash messages)
 */
function flash_set($key, $message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type, // 'success', 'error', 'warning', 'info'
    ];
}

function flash_get($key) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'][$key])) {
        $val = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    return null;
}

function flash_has($key) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['flash'][$key]);
}

/**
 * ارسال هدرهای امنیتی و جلوگیری از کش
 */
function send_security_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
}

/**
 * پاسخ JSON استاندارد
 */
function json_response($data, $statusCode = 200) {
    send_security_headers();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * رندر قالب با Layout
 */
function render_view($viewName, $data = [], $layout = 'layout') {
    extract($data);
    $viewFile = __DIR__ . '/../views/' . $viewName . '.php';
    if (!file_exists($viewFile)) {
        die('قالب پیدا نشد: ' . e($viewName));
    }

    ob_start();
    include $viewFile;
    $content = ob_get_clean();

    if ($layout) {
        $layoutFile = __DIR__ . '/../views/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            include $layoutFile;
            return;
        }
    }

    echo $content;
}

/**
 * پاکسازی دوره‌ای رکوردهای تاریخ‌گذشته rate_limits
 */
function clean_old_rate_limits($pdo) {
    // اجرای تصادفی با شانس ۵٪
    if (mt_rand(1, 100) <= 5) {
        try {
            // حذف رکوردهای قدیمی‌تر از ۲۴ ساعت
            $cutoff = gmdate('Y-m-d H:i:s', time() - 86400);
            $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE created_at < :cutoff');
            $stmt->execute(['cutoff' => $cutoff]);
        } catch (Exception $e) {
            // نادیده گرفتن خطا در پاکسازی پیش‌زمینه
        }
    }
}

/**
 * بررسی محدودیت نرخ (Rate Limiting)
 */
function check_rate_limit($pdo, $kind, $key, $maxAttempts, $windowSeconds) {
    clean_old_rate_limits($pdo);
    $keyHash = hash('sha256', (string)$key);
    $since = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt 
        FROM rate_limits 
        WHERE kind = :kind AND key_hash = :hash AND created_at >= :since
    ');
    $stmt->execute([
        'kind' => $kind,
        'hash' => $keyHash,
        'since' => $since,
    ]);
    $row = $stmt->fetch();
    return ((int)$row['cnt'] < (int)$maxAttempts);
}

/**
 * ثبت یک تلاش در جدول rate_limits
 */
function record_rate_limit($pdo, $kind, $key) {
    $keyHash = hash('sha256', (string)$key);
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare('
        INSERT INTO rate_limits (kind, key_hash, created_at) 
        VALUES (:kind, :hash, :created_at)
    ');
    $stmt->execute([
        'kind' => $kind,
        'hash' => $keyHash,
        'created_at' => $now,
    ]);
}

/**
 * نمایش صفحه ۴۰۴ همسان و امن
 */
function show_404() {
    send_security_headers();
    http_response_code(404);
    render_view('404', [
        'page_title' => 'صفحه پیدا نشد - ۴۰۴',
    ]);
    exit;
}

/**
 * تبدیل کد رنگ هگزادسیمال به گرادیانت، هاله نوری و پالت گلسمورفیسم اختصاصی
 */
function get_tag_color_palette($hex) {
    $hex = trim((string)$hex);
    if (!preg_match('/^#?[0-9a-fA-F]{3,6}$/', $hex)) {
        $hex = '#eab308'; // پیش‌فرض طلایی جذاب
    }
    if (strpos($hex, '#') !== 0) {
        $hex = '#' . $hex;
    }
    if (strlen($hex) === 4) {
        $hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
    }

    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));

    // محاسبه رنگ روشن‌تر برای های‌لایت گرادیانت
    $rLight = min(255, (int)round($r + (255 - $r) * 0.42));
    $gLight = min(255, (int)round($g + (255 - $g) * 0.42));
    $bLight = min(255, (int)round($b + (255 - $b) * 0.42));
    $lightHex = sprintf("#%02x%02x%02x", $rLight, $gLight, $bLight);

    // محاسبه رنگ تیره‌تر برای سایه و عمق گرادیانت
    $rDark = max(0, (int)round($r * 0.62));
    $gDark = max(0, (int)round($g * 0.62));
    $bDark = max(0, (int)round($b * 0.62));
    $darkHex = sprintf("#%02x%02x%02x", $rDark, $gDark, $bDark);

    return [
        'hex' => $hex,
        'r' => $r,
        'g' => $g,
        'b' => $b,
        'light' => $lightHex,
        'dark' => $darkHex,
        'glow' => "rgba({$r}, {$g}, {$b}, 0.55)",
        'glow_soft' => "rgba({$r}, {$g}, {$b}, 0.28)",
        'subtle' => "rgba({$r}, {$g}, {$b}, 0.12)",
        'border' => "rgba({$r}, {$g}, {$b}, 0.45)",
        'gradient' => "linear-gradient(135deg, {$lightHex} 0%, {$hex} 50%, {$darkHex} 100%)",
        'ambient' => "radial-gradient(ellipse 90% 60% at 50% -10%, rgba({$r}, {$g}, {$b}, 0.38) 0%, rgba({$r}, {$g}, {$b}, 0.1) 45%, transparent 75%)",
    ];
}

