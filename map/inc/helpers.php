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
        $exists = $stmt->fetch();
        $stmt->closeCursor();
        if (!$exists) {
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
    if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST']);
        $base = (is_ssl() ? 'https' : 'http') . '://' . $host;
    }
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
    if (is_array($message)) {
        // پیام ساختاریافته (مانند نتیجه اسکن: type/message/motivation_quote) همان‌طور ذخیره می‌شود
        if (!isset($message['type'])) {
            $message['type'] = $type;
        }
        $_SESSION['flash'][$key] = $message;
        return;
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
    // بستن صریح کرسر: در SQLite کرسر باز، تراکنش خواندن را باز نگه می‌دارد و نوشتن بعدی را با خطای قفل مواجه می‌کند
    $stmt->closeCursor();
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
 * نمایش صفحه پیام کاربرپسند (به جای متن خام die) و توقف اجرا
 */
function show_message_page($statusCode, $title, $text, $icon = 'ℹ️', $link = null, $linkText = null) {
    send_security_headers();
    http_response_code($statusCode);
    render_view('message', [
        'page_title' => $title,
        'message_title' => $title,
        'message_text' => $text,
        'message_icon' => $icon,
        'message_link' => $link,
        'message_link_text' => $linkText,
    ]);
    exit;
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
    if (!preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
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

/**
 * اعتبارسنجی و یکسان‌سازی کد رنگ هگز (#rrggbb)؛ در صورت نامعتبر بودن مقدار پیش‌فرض برمی‌گردد
 */
function normalize_hex($hex, $default = '#94a3b8') {
    $hex = strtolower(trim((string)$hex));
    if (preg_match('/^#?([0-9a-f]{3})$/', $hex, $m)) {
        $h = $m[1];
        return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (preg_match('/^#?([0-9a-f]{6})$/', $hex, $m)) {
        return '#' . $m[1];
    }
    return $default;
}

/**
 * طول رشته یونیکد (با پشتیبانی از نبود mbstring)
 */
function str_len_u($str) {
    return function_exists('mb_strlen') ? mb_strlen((string)$str, 'UTF-8') : strlen((string)$str);
}

/**
 * کوتاه‌سازی رشته یونیکد به حداکثر طول مجاز ستون پایگاه‌داده
 */
function str_limit_u($str, $max) {
    $str = trim((string)$str);
    if (function_exists('mb_substr')) {
        return mb_substr($str, 0, $max, 'UTF-8');
    }
    return substr($str, 0, $max);
}

/**
 * آیا درایور پایگاه‌داده MySQL/MariaDB است؟
 */
function db_is_mysql($pdo) {
    return strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false;
}

/**
 * گرفتن قفل نوشتن روی ردیف تگ داخل تراکنش جاری.
 *
 * MySQL: با SELECT ... FOR UPDATE ردیف قفل می‌شود.
 * SQLite: یک UPDATE بی‌اثر به عنوان اولین دستور تراکنش، قفل نوشتن پایگاه‌داده را فوراً می‌گیرد
 * تا تراکنش‌های همزمان به جای خطای SQLITE_BUSY (ارتقای قفل خواندن به نوشتن) منتظر بمانند (busy_timeout).
 */
function lock_tag_row($pdo, $tagId) {
    if (db_is_mysql($pdo)) {
        $stmt = $pdo->prepare('SELECT id, color_id FROM tags WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => (int)$tagId]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }
    $pdo->prepare('UPDATE tags SET id = id WHERE id = :id')->execute(['id' => (int)$tagId]);
    $stmt = $pdo->prepare('SELECT id, color_id FROM tags WHERE id = :id');
    $stmt->execute(['id' => (int)$tagId]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row;
}

/**
 * بازشماری رتبه‌های ثبت‌های یک تگ به ترتیب زمان ثبت (۱، ۲، ۳، ...)
 *
 * برای جلوگیری از نقض قید یکتای (tag_id, rank) در حین به‌روزرسانی، ابتدا رتبه‌ها
 * به مقادیر منفی موقت منتقل و سپس مقدار نهایی نوشته می‌شود.
 */
function renumber_tag_claims($pdo, $tagId) {
    $remStmt = $pdo->prepare('SELECT id FROM claims WHERE tag_id = :tag_id ORDER BY created_at ASC, id ASC');
    $remStmt->execute(['tag_id' => (int)$tagId]);
    $ids = $remStmt->fetchAll(PDO::FETCH_COLUMN);

    $upStmt = $pdo->prepare('UPDATE claims SET `rank` = :rank WHERE id = :id');
    $k = 1;
    foreach ($ids as $id) {
        $upStmt->execute(['rank' => -$k, 'id' => $id]);
        $k++;
    }
    $k = 1;
    foreach ($ids as $id) {
        $upStmt->execute(['rank' => $k, 'id' => $id]);
        $k++;
    }
}

/**
 * ساخت ردیف‌های امتیاز صفر برای جایگاه‌هایی که هنوز در جدول امتیاز رنگ‌های رویداد وجود ندارند
 */
function seed_color_points($pdo, $eventId, $positionsCount) {
    $ins = db_is_mysql($pdo)
        ? $pdo->prepare('INSERT IGNORE INTO color_points (color_id, `rank`, points) VALUES (:cid, :rank, 0)')
        : $pdo->prepare('INSERT OR IGNORE INTO color_points (color_id, `rank`, points) VALUES (:cid, :rank, 0)');
    $colStmt = $pdo->prepare('SELECT id FROM colors WHERE event_id = :eid');
    $colStmt->execute(['eid' => (int)$eventId]);
    foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        for ($r = 1; $r <= (int)$positionsCount; $r++) {
            $ins->execute(['cid' => (int)$cid, 'rank' => $r]);
        }
    }
}

/**
 * بررسی تعلق رنگ به رویداد؛ در غیر این صورت null (بدون رنگ) برمی‌گردد
 */
function valid_event_color_id($pdo, $eventId, $colorId) {
    if (empty($colorId)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM colors WHERE id = :id AND event_id = :eid LIMIT 1');
    $stmt->execute(['id' => (int)$colorId, 'eid' => (int)$eventId]);
    return $stmt->fetch() ? (int)$colorId : null;
}

/**
 * توکن تصادفی برای لینک عمومی لیدربورد
 */
function generate_board_token() {
    return base64url_encode(random_bytes(16));
}

/**
 * شناسه ماندگار دستگاه (گوشی) برای قابلیت «قفل گوشی روی تیم»
 *
 * در کوکی HttpOnly با عمر ۱۸۰ روز نگهداری می‌شود. اگر $createIfMissing فعال باشد
 * و کوکی وجود نداشته باشد، شناسه جدید ساخته و کوکی تنظیم می‌شود.
 *
 * @return string|null
 */
function get_device_id($createIfMissing = false) {
    $name = 'nfc_device';
    if (isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) && preg_match('/^[A-Za-z0-9_-]{22}$/', $_COOKIE[$name])) {
        return $_COOKIE[$name];
    }
    if (!$createIfMissing) {
        return null;
    }
    $id = base64url_encode(random_bytes(16));
    $path = defined('BASE_PATH') && BASE_PATH !== '' ? BASE_PATH : '/';
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $id, [
            'expires' => time() + 180 * 86400,
            'path' => $path,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie($name, $id, time() + 180 * 86400, $path . '; samesite=Lax', '', is_ssl(), true);
    }
    $_COOKIE[$name] = $id;
    return $id;
}

/**
 * هش شناسه دستگاه برای ذخیره در پایگاه‌داده (شناسه خام ذخیره نمی‌شود)
 */
function device_hash($deviceId) {
    return hash('sha256', 'nfc-device|' . (string)$deviceId);
}

/**
 * تیمی که این دستگاه در رویداد به آن قفل شده است (یا null)
 *
 * @return array|null ['team_id' => int, 'team_name' => string]
 */
function get_device_lock($pdo, $eventId, $deviceId) {
    if (empty($deviceId)) {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT dl.team_id, tm.name AS team_name
        FROM device_locks dl
        INNER JOIN teams tm ON tm.id = dl.team_id
        WHERE dl.event_id = :eid AND dl.device_hash = :h
        LIMIT 1
    ');
    $stmt->execute(['eid' => (int)$eventId, 'h' => device_hash($deviceId)]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row ?: null;
}

/**
 * تیمی که این گوشی (نشست) آخرین بار در رویداد برای آن ثبت کرده است
 */
function get_session_team($eventId) {
    return isset($_SESSION['my_team'][(int)$eventId]) ? (int)$_SESSION['my_team'][(int)$eventId] : 0;
}

function set_session_team($eventId, $teamId) {
    $_SESSION['my_team'][(int)$eventId] = (int)$teamId;
}

/**
 * آدرس فایل‌های استاتیک همراه با نسخه (زمان تغییر فایل) برای جلوگیری از نمایش نسخه قدیمی از کش مرورگر
 */
function asset_url($path) {
    $path = '/' . ltrim($path, '/');
    $abs = dirname(__DIR__) . $path;
    $v = is_file($abs) ? filemtime($abs) : 0;
    return path_url($path) . ($v ? '?v=' . $v : '');
}
