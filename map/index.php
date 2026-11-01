<?php
/**
 * NFC Event Tag Scoring - Front Controller & Router
 */

define('APP_INIT', true);

// اصلاح و تنظیم خودکار دسترسی پوشه‌ها (755) و فایل‌ها (644) به صورت سیستمی در اولین اجرا
if (!file_exists(__DIR__ . '/data/.perms_fixed')) {
    if (!is_dir(__DIR__ . '/data')) {
        @mkdir(__DIR__ . '/data', 0777, true);
    }
    @chmod(__DIR__, 0755);
    @chmod(__DIR__ . '/data', 0777);
    @chmod(__DIR__ . '/inc', 0755);
    @chmod(__DIR__ . '/views', 0755);
    @chmod(__DIR__ . '/assets', 0755);
    @chmod(__DIR__ . '/assets/fonts', 0755);
    if (!is_dir(__DIR__ . '/assets/uploads')) {
        @mkdir(__DIR__ . '/assets/uploads', 0755, true);
    }
    @chmod(__DIR__ . '/assets/uploads', 0755);

    $foldersToFix = [__DIR__, __DIR__ . '/inc', __DIR__ . '/views', __DIR__ . '/assets', __DIR__ . '/assets/fonts', __DIR__ . '/assets/uploads'];
    foreach ($foldersToFix as $fDir) {
        $filesInDir = @glob($fDir . '/*.*');
        if (is_array($filesInDir)) {
            foreach ($filesInDir as $f) {
                @chmod($f, 0644);
            }
        }
    }
    if (file_exists(__DIR__ . '/data/database.sqlite')) {
        @chmod(__DIR__ . '/data/database.sqlite', 0666);
    }
    @file_put_contents(__DIR__ . '/data/.perms_fixed', '1');
}

// بارگذاری خودکار فایل تنظیمات (در صورت نبودن، از روی نمونه ساخته می‌شود)
$configFile = __DIR__ . '/inc/config.php';
if (!file_exists($configFile)) {
    $sampleFile = __DIR__ . '/inc/config.sample.php';
    if (file_exists($sampleFile)) {
        @copy($sampleFile, $configFile);
    }
}

if (file_exists($configFile)) {
    require_once $configFile;
} elseif (file_exists(__DIR__ . '/inc/config.sample.php')) {
    require_once __DIR__ . '/inc/config.sample.php';
}

// تنظیمات گزارش خطا و مدیریت خطاهای پیش‌بینی‌نشده
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// جلوگیری از نمایش صفحه خطای خام HTTP 500 آپاچی
set_exception_handler(function($e) {
    error_log('Application Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطا در پردازش اطلاعات</title>
        <style>
            body { font-family: Tahoma, Vazirmatn, sans-serif; background: #f8fafc; color: #0f172a; padding: 2rem; direction: rtl; text-align: right; }
            .box { max-width: 650px; margin: 3rem auto; background: #fff; padding: 2rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
            h2 { color: #dc2626; margin-top: 0; font-size: 1.3rem; }
            p { line-height: 1.7; margin-bottom: 1rem; }
            .details { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 8px; font-family: monospace; direction: ltr; text-align: left; margin: 1rem 0; word-break: break-all; }
            .btn { display: inline-block; background: #2563eb; color: #fff; padding: 0.6rem 1.25rem; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 1rem; }
            .btn:hover { background: #1d4ed8; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>⚠️ خطایی در پردازش اطلاعات رخ داد</h2>
            <p>درخواست شما با خطا مواجه شد. لطفاً به صفحه قبل بازگردید یا دسترسی پوشه داده‌ها (data) را بررسی کنید.</p>
            <div class="details"><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?></div>
            <a href="javascript:history.back()" class="btn">← بازگشت به صفحه قبلی</a>
        </div>
    </body>
    </html>
    <?php
    exit;
});

// بارگذاری ماژول‌های اصلی
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/jalali.php';
require_once __DIR__ . '/inc/csrf.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/scan.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/export.php';
require_once __DIR__ . '/inc/quotes.php';

// ارسال هدرهای امنیتی برای تمام پاسخ‌ها
send_security_headers();

// راه‌اندازی نشست امن
init_session();

// دریافت اتصال پایگاه‌داده
$pdo = get_db();

// استخراج مسیر درخواستی (Path) متناسب با BASE_PATH
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = defined('BASE_PATH') ? trim(BASE_PATH, '/') : 'map';

// حذف BASE_PATH از ابتدای مسیر درخواستی
$uri = trim($requestUri, '/');
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = trim(substr($uri, strlen($basePath)), '/');
}

// مسیر خالی: /map/ بدون کد
if ($uri === '') {
    show_404();
}

// ==============================================================================
// مسیرهای پنل مدیریت (/admin/...)
// ==============================================================================
if (strpos($uri, 'admin') === 0) {
    $adminPath = trim(substr($uri, 5), '/'); // پس از 'admin'

    // ۱. صفحه ورود (/admin/login)
    if ($adminPath === 'login') {
        if (is_admin_logged_in()) {
            header('Location: ' . path_url('/admin/events'));
            exit;
        }

        $loginError = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $loginRes = admin_login($pdo, $username, $password);
            if ($loginRes['success']) {
                header('Location: ' . path_url('/admin/events'));
                exit;
            } else {
                $loginError = $loginRes['message'];
            }
        }

        render_view('admin_login', [
            'page_title' => 'ورود به پنل مدیریت',
            'login_error' => $loginError,
            'username' => isset($_POST['username']) ? $_POST['username'] : '',
        ]);
        exit;
    }

    // ۲. خروج (/admin/logout)
    if ($adminPath === 'logout') {
        admin_logout();
        header('Location: ' . path_url('/admin/login'));
        exit;
    }

    // از اینجا به بعد تمام بخش‌های ادمین نیازمند لاگین هستند
    require_admin();

    // ریدایرکت /admin به /admin/events
    if ($adminPath === '') {
        header('Location: ' . path_url('/admin/events'));
        exit;
    }

    // ۳. تغییر رمز عبور (/admin/password)
    if ($adminPath === 'password') {
        $pwdError = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

            if ($new !== $confirm) {
                $pwdError = 'رمز عبور جدید با تکرار آن یکسان نیست.';
            } else {
                $res = admin_change_password($pdo, $_SESSION['admin_id'], $current, $new);
                if ($res['success']) {
                    flash_set('success', $res['message'], 'success');
                    header('Location: ' . path_url('/admin/events'));
                    exit;
                } else {
                    $pwdError = $res['message'];
                }
            }
        }

        render_view('admin_password', [
            'page_title' => 'تغییر رمز عبور ادمین',
            'pwd_error' => $pwdError,
        ]);
        exit;
    }

    // ۴. لیست رویدادها (/admin/events)
    if ($adminPath === 'events') {
        $sql = '
            SELECT 
                e.*,
                (SELECT COUNT(*) FROM teams WHERE event_id = e.id) AS teams_count,
                (SELECT COUNT(*) FROM tags WHERE event_id = e.id) AS tags_count
            FROM events e
            ORDER BY e.id DESC
        ';
        $stmt = $pdo->query($sql);
        $events = $stmt->fetchAll();

        render_view('admin_events', [
            'page_title' => 'مدیریت رویدادها',
            'events' => $events,
        ]);
        exit;
    }

    // ۵. ایجاد رویداد جدید (/admin/events/create)
    if ($adminPath === 'events/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $positionsCount = isset($_POST['positions_count']) ? (int)$_POST['positions_count'] : 4;
        if ($positionsCount < 1) $positionsCount = 1;
        if ($positionsCount > 10) $positionsCount = 10;
        $showResult = !empty($_POST['show_result_to_scanner']) ? 1 : 0;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            flash_set('error', 'نام رویداد نمی‌تواند خالی باشد.', 'error');
            header('Location: ' . path_url('/admin/events'));
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO events (name, positions_count, show_result_to_scanner, is_active, created_at)
            VALUES (:name, :positions, :show_res, :active, :created)
        ');
        $stmt->execute([
            'name' => $name,
            'positions' => $positionsCount,
            'show_res' => $showResult,
            'active' => $isActive,
            'created' => gmdate('Y-m-d H:i:s'),
        ]);
        $newEventId = (int)$pdo->lastInsertId();

        flash_set('success', 'رویداد جدید با موفقیت ایجاد شد.', 'success');
        header('Location: ' . path_url('/admin/events/' . $newEventId));
        exit;
    }

    // ۶. عملیات روی یک رویداد خاص (/admin/events/{id}/...)
    if (preg_match('#^events/(\d+)(/.*)?$#', $adminPath, $matches)) {
        $eventId = (int)$matches[1];
        $subAction = isset($matches[2]) ? trim($matches[2], '/') : '';

        // دریافت اطلاعات رویداد
        $evStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $evStmt->execute(['id' => $eventId]);
        $event = $evStmt->fetch();
        if (!$event) {
            show_404();
        }

        // ۶.۱. کپی رویداد (/admin/events/{id}/duplicate)
        if ($subAction === 'duplicate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $newId = duplicate_event($pdo, $eventId);
            if ($newId) {
                flash_set('success', 'رویداد کپی شد! توجه: تگ‌های رویداد جدید دارای کدهای کاملاً جدید هستند و باید مجدداً روی تگ‌های فیزیکی NFC نوشته شوند.', 'success');
                header('Location: ' . path_url('/admin/events/' . $newId));
                exit;
            } else {
                flash_set('error', 'خطا در کپی رویداد.', 'error');
                header('Location: ' . path_url('/admin/events'));
                exit;
            }
        }

        // ۶.۲. حذف رویداد (/admin/events/{id}/delete)
        if ($subAction === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $delStmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $delStmt->execute(['id' => $eventId]);
            flash_set('success', 'رویداد و کلیه اطلاعات مربوطه حذف شدند.', 'success');
            header('Location: ' . path_url('/admin/events'));
            exit;
        }

        // ۶.۲.۱. پاکسازی تمام ثبت‌ها و صفر کردن جدول امتیازات (جهت شروع مسابقه واقعی پس از تست)
        if ($subAction === 'reset-claims' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $resetStmt = $pdo->prepare('DELETE FROM claims WHERE tag_id IN (SELECT id FROM tags WHERE event_id = :eid)');
            $resetStmt->execute(['eid' => $eventId]);
            flash_set('success', 'تمامی ثبت‌های آزمایشی این رویداد با موفقیت پاکسازی شدند و جدول امتیازات صفر گردید.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=leaderboard'));
            exit;
        }

        // ۶.۳. به‌روزرسانی تنظیمات رویداد
        if ($subAction === 'settings/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $newPositions = isset($_POST['positions_count']) ? (int)$_POST['positions_count'] : 4;
            if ($newPositions < 1) $newPositions = 1;
            if ($newPositions > 10) $newPositions = 10;
            $showResult = !empty($_POST['show_result_to_scanner']) ? 1 : 0;
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            // اعتبارسنجی: کاهش به عددی کمتر از بیشترین rank ثبت‌شده مجاز نیست
            $maxRankStmt = $pdo->prepare('
                SELECT COALESCE(MAX(cl.rank), 0) AS max_rank 
                FROM claims cl 
                INNER JOIN tags tg ON tg.id = cl.tag_id 
                WHERE tg.event_id = :event_id
            ');
            $maxRankStmt->execute(['event_id' => $eventId]);
            $maxRank = (int)$maxRankStmt->fetch()['max_rank'];

            if ($newPositions < $maxRank) {
                flash_set('error', 'امکان کاهش تعداد جایگاه‌ها به کمتر از ' . fa_num($maxRank) . ' وجود ندارد، زیرا قبلاً ثبتی در این رتبه انجام شده است.', 'error');
                header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=settings'));
                exit;
            }

            // دریافت اطلاعات فعلی رویداد و مسیر لوگو
            $evStmt = $pdo->prepare('SELECT logo_path FROM events WHERE id = :id');
            $evStmt->execute(['id' => $eventId]);
            $currentEv = $evStmt->fetch();
            $currentLogo = $currentEv ? $currentEv['logo_path'] : null;

            $newLogoPath = $currentLogo;
            $logoTintMode = isset($_POST['logo_tint_mode']) ? (int)$_POST['logo_tint_mode'] : 1;

            // حذف لوگوی قبلی در صورت درخواست
            if (!empty($_POST['delete_logo'])) {
                if ($currentLogo && file_exists(__DIR__ . '/' . $currentLogo)) {
                    @unlink(__DIR__ . '/' . $currentLogo);
                }
                $newLogoPath = null;
            }

            // پردازش آپلود لوگوی جدید
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['logo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

                if (in_array($ext, $allowedExts, true)) {
                    if ($file['size'] <= 3 * 1024 * 1024) { // حداکثر ۳ مگابایت
                        $uploadDir = __DIR__ . '/assets/uploads';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        @chmod($uploadDir, 0755);

                        $randomToken = bin2hex(random_bytes(8));
                        $filename = 'logo_' . $eventId . '_' . $randomToken . '.' . $ext;
                        $destination = $uploadDir . '/' . $filename;

                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            @chmod($destination, 0644);
                            if ($currentLogo && file_exists(__DIR__ . '/' . $currentLogo)) {
                                @unlink(__DIR__ . '/' . $currentLogo);
                            }
                            $newLogoPath = 'assets/uploads/' . $filename;
                        } else {
                            flash_set('warning', 'خطا در ذخیره فایل لوگو. لطفاً مجوز نوشتن پوشه assets/uploads را بررسی کنید.', 'warning');
                        }
                    } else {
                        flash_set('warning', 'حجم فایل لوگو نباید بیش از ۳ مگابایت باشد.', 'warning');
                    }
                } else {
                    flash_set('warning', 'فرمت فایل لوگو نامعتبر است. فقط PNG، SVG، JPG و WEBP مجاز هستند.', 'warning');
                }
            }

            $upStmt = $pdo->prepare('
                UPDATE events 
                SET name = :name, positions_count = :positions, show_result_to_scanner = :show_res, is_active = :active, logo_path = :logo_path, logo_tint_mode = :tint_mode
                WHERE id = :id
            ');
            $upStmt->execute([
                'name' => $name,
                'positions' => $newPositions,
                'show_res' => $showResult,
                'active' => $isActive,
                'logo_path' => $newLogoPath,
                'tint_mode' => $logoTintMode,
                'id' => $eventId,
            ]);

            flash_set('success', 'تنظیمات رویداد و لوگوی سازمان با موفقیت ذخیره شد.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=settings'));
            exit;
        }

        // ۶.۴. افزودن رنگ جدید
        if ($subAction === 'colors/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $cName = isset($_POST['name']) ? trim($_POST['name']) : '';
            $cHex = isset($_POST['hex']) ? trim($_POST['hex']) : '#ef4444';
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

            if ($cName !== '') {
                $insCol = $pdo->prepare('INSERT INTO colors (event_id, name, hex, sort_order) VALUES (:eid, :name, :hex, :order)');
                $insCol->execute([
                    'eid' => $eventId,
                    'name' => $cName,
                    'hex' => $cHex,
                    'order' => $sortOrder,
                ]);
                $newColorId = (int)$pdo->lastInsertId();

                // افزودن پیش‌فرض ردیف‌های امتیاز صفر
                $insPt = $pdo->prepare('INSERT INTO color_points (color_id, rank, points) VALUES (:cid, :rank, 0)');
                for ($r = 1; $r <= (int)$event['positions_count']; $r++) {
                    $insPt->execute(['cid' => $newColorId, 'rank' => $r]);
                }

                flash_set('success', 'رنگ جدید اضافه شد.', 'success');
            }
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=colors'));
            exit;
        }

        // ۶.۵. حذف رنگ
        if (preg_match('#^colors/(\d+)/delete$#', $subAction, $cMatch)) {
            $delColorId = (int)$cMatch[1];
            // اعتبارسنجی CSRF
            $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
            if (!csrf_verify($token)) {
                die('خطای اعتبارسنجی CSRF');
            }

            // رنگ حذف شود (کلید خارجی تگ‌ها ON DELETE SET NULL است)
            $delStmt = $pdo->prepare('DELETE FROM colors WHERE id = :id AND event_id = :eid');
            $delStmt->execute(['id' => $delColorId, 'eid' => $eventId]);

            flash_set('warning', 'رنگ حذف شد و تگ‌های آن بدون رنگ شدند.', 'warning');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=colors'));
            exit;
        }

        // ۶.۶. ذخیره کل جدول ماتریس رنگ‌ها و امتیازات
        if ($subAction === 'colors/update-points' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $colorsData = isset($_POST['colors']) ? $_POST['colors'] : [];
            $pointsData = isset($_POST['points']) ? $_POST['points'] : [];

            $upCol = $pdo->prepare('UPDATE colors SET name = :name, hex = :hex, sort_order = :order WHERE id = :id AND event_id = :eid');
            foreach ($colorsData as $cid => $cItem) {
                $upCol->execute([
                    'name' => trim($cItem['name']),
                    'hex' => trim($cItem['hex']),
                    'order' => (int)$cItem['sort_order'],
                    'id' => (int)$cid,
                    'eid' => $eventId,
                ]);
            }

            // به‌روزرسانی جدول color_points متناسب با درایور
            $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

            if ($driver === 'sqlite') {
                $insOrUpPt = $pdo->prepare('INSERT OR REPLACE INTO color_points (color_id, rank, points) VALUES (:cid, :rank, :pts)');
            } else {
                $insOrUpPt = $pdo->prepare('
                    INSERT INTO color_points (color_id, rank, points) 
                    VALUES (:cid, :rank, :pts)
                    ON DUPLICATE KEY UPDATE points = VALUES(points)
                ');
            }

            foreach ($pointsData as $cid => $ranks) {
                foreach ($ranks as $rankNum => $ptsVal) {
                    $insOrUpPt->execute(['cid' => (int)$cid, 'rank' => (int)$rankNum, 'pts' => (int)$ptsVal]);
                }
            }

            flash_set('success', 'جدول رنگ‌ها و امتیازات با موفقیت به‌روزرسانی شد.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=colors'));
            exit;
        }

        // ۶.۷. افزودن گروهی تیم‌ها
        if ($subAction === 'teams/create-bulk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $text = isset($_POST['teams_text']) ? $_POST['teams_text'] : '';
            $lines = explode("\n", str_replace("\r", '', $text));
            $added = 0;

            $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
            if ($driver === 'sqlite') {
                $insTm = $pdo->prepare('INSERT OR IGNORE INTO teams (event_id, name) VALUES (:eid, :name)');
            } else {
                $insTm = $pdo->prepare('INSERT IGNORE INTO teams (event_id, name) VALUES (:eid, :name)');
            }

            foreach ($lines as $line) {
                $tName = trim($line);
                if ($tName !== '') {
                    $insTm->execute(['eid' => $eventId, 'name' => $tName]);
                    if ($insTm->rowCount() > 0) $added++;
                }
            }

            flash_set('success', fa_num($added) . ' تیم با موفقیت اضافه شدند.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=teams'));
            exit;
        }

        // ۶.۸. افزودن تک تیم
        if ($subAction === 'teams/create-single' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $tName = isset($_POST['name']) ? trim($_POST['name']) : '';
            if ($tName !== '') {
                try {
                    $insTm = $pdo->prepare('INSERT INTO teams (event_id, name) VALUES (:eid, :name)');
                    $insTm->execute(['eid' => $eventId, 'name' => $tName]);
                    flash_set('success', 'تیم با موفقیت اضافه شد.', 'success');
                } catch (PDOException $e) {
                    flash_set('error', 'تیمی با این نام در این رویداد قبلاً ثبت شده است.', 'error');
                }
            }
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=teams'));
            exit;
        }

        // ۶.۹. ویرایش نام تیم
        if (preg_match('#^teams/(\d+)/update$#', $subAction, $tMatch) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $teamId = (int)$tMatch[1];
            $tName = isset($_POST['name']) ? trim($_POST['name']) : '';
            if ($tName !== '') {
                $upTm = $pdo->prepare('UPDATE teams SET name = :name WHERE id = :id AND event_id = :eid');
                $upTm->execute(['name' => $tName, 'id' => $teamId, 'eid' => $eventId]);
                flash_set('success', 'نام تیم ویرایش شد.', 'success');
            }
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=teams'));
            exit;
        }

        // ۶.۱۰. حذف تیم
        if (preg_match('#^teams/(\d+)/delete$#', $subAction, $tMatch) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $teamId = (int)$tMatch[1];
            delete_team_and_reorder($pdo, $teamId, $eventId);
            flash_set('success', 'تیم با موفقیت حذف شد و رتبه‌های متأثر بازشماری شدند.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=teams'));
            exit;
        }

        // ۶.۱۱. درخواست AJAX جزئیات ثبت‌های یک تیم
        if (preg_match('#^teams/(\d+)/claims-json$#', $subAction, $tMatch)) {
            $teamId = (int)$tMatch[1];
            $claims = get_team_claims_detail($pdo, $teamId);
            $formatted = [];
            foreach ($claims as $c) {
                $formatted[] = [
                    'tag_name' => $c['tag_name'],
                    'color_name' => $c['color_name'],
                    'color_hex' => $c['color_hex'],
                    'claim_rank_fa' => rank_to_words_fa($c['claim_rank']),
                    'points_fa' => fa_num($c['points']),
                    'claim_time_fa' => format_jalali($c['claim_time'], true, true),
                ];
            }
            json_response(['success' => true, 'claims' => $formatted]);
        }

        // ۶.۱۲. ساخت چند تگ گروهی
        if ($subAction === 'tags/create-bulk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $count = isset($_POST['count']) ? (int)$_POST['count'] : 5;
            if ($count < 1) $count = 1;
            if ($count > 50) $count = 50;
            $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
            $colorId = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;

            // شمارش تگ‌های فعلی برای نام‌گذاری هوشمند
            $curCntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM tags WHERE event_id = :eid');
            $curCntStmt->execute(['eid' => $eventId]);
            $startNum = (int)$curCntStmt->fetch()['cnt'] + 1;

            $nowUtc = gmdate('Y-m-d H:i:s');
            $insTag = $pdo->prepare('
                INSERT INTO tags (event_id, name, color_id, code, created_at)
                VALUES (:eid, :name, :cid, :code, :created)
            ');

            for ($i = 0; $i < $count; $i++) {
                $num = $startNum + $i;
                $tName = ($prefix !== '') ? ($prefix . ' ' . fa_num($num)) : ('تگ ' . fa_num($num));
                $code = generate_unique_tag_code($pdo);
                $insTag->execute([
                    'eid' => $eventId,
                    'name' => $tName,
                    'cid' => $colorId,
                    'code' => $code,
                    'created' => $nowUtc,
                ]);
            }

            flash_set('success', fa_num($count) . ' تگ با موفقیت ساخته شد.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=tags'));
            exit;
        }

        // ۶.۱۳. افزودن تگ تکی
        if ($subAction === 'tags/create-single' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $tName = isset($_POST['name']) ? trim($_POST['name']) : '';
            $colorId = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;

            if ($tName !== '') {
                $code = generate_unique_tag_code($pdo);
                $insTag = $pdo->prepare('
                    INSERT INTO tags (event_id, name, color_id, code, created_at)
                    VALUES (:eid, :name, :cid, :code, :created)
                ');
                $insTag->execute([
                    'eid' => $eventId,
                    'name' => $tName,
                    'cid' => $colorId,
                    'code' => $code,
                    'created' => gmdate('Y-m-d H:i:s'),
                ]);
                flash_set('success', 'تگ با موفقیت اضافه شد.', 'success');
            }
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=tags'));
            exit;
        }

        // ۶.۱۴. ویرایش نام و رنگ تگ
        if (preg_match('#^tags/(\d+)/update$#', $subAction, $tgMatch) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $tagId = (int)$tgMatch[1];
            $tName = isset($_POST['name']) ? trim($_POST['name']) : '';
            $colorId = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;

            if ($tName !== '') {
                $upTag = $pdo->prepare('UPDATE tags SET name = :name, color_id = :cid WHERE id = :id AND event_id = :eid');
                $upTag->execute([
                    'name' => $tName,
                    'cid' => $colorId,
                    'id' => $tagId,
                    'eid' => $eventId,
                ]);
                flash_set('success', 'تگ با موفقیت به‌روزرسانی شد.', 'success');
            }
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=tags'));
            exit;
        }

        // ۶.۱۵. حذف تگ
        if (preg_match('#^tags/(\d+)/delete$#', $subAction, $tgMatch) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $tagId = (int)$tgMatch[1];
            $delTag = $pdo->prepare('DELETE FROM tags WHERE id = :id AND event_id = :eid');
            $delTag->execute(['id' => $tagId, 'eid' => $eventId]);
            flash_set('success', 'تگ با موفقیت حذف شد و پیوند آن باطل گردید.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=tags'));
            exit;
        }

        // ۶.۱۶. حذف ثبت تگ و بازشماری رتبه‌ها
        if (preg_match('#^claims/(\d+)/delete$#', $subAction, $cMatch) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf();
            $claimId = (int)$cMatch[1];
            delete_claim_and_reorder($pdo, $claimId);
            flash_set('success', 'ثبت با موفقیت حذف شد و رتبه‌های بعدی بازشماری گردیدند.', 'success');
            header('Location: ' . path_url('/admin/events/' . $eventId . '?tab=leaderboard'));
            exit;
        }

        // ۶.۱۷. خروجی اکسل لیدربورد
        if ($subAction === 'export/leaderboard') {
            export_leaderboard_csv($pdo, $eventId);
            exit;
        }

        // ۶.۱۸. خروجی اکسل ریز ثبت‌ها
        if ($subAction === 'export/claims') {
            export_claims_csv($pdo, $eventId);
            exit;
        }

        // ۶.۱۸.۱. خروجی اکسل پیوندهای تگ‌ها جهت رایت گروهی
        if ($subAction === 'export/tags') {
            export_tags_csv($pdo, $eventId);
            exit;
        }

        // ۶.۱۹. نمایش صفحه تب‌های مدیریت رویداد (پیش‌فرض)
        // واکشی رنگ‌ها
        $colStmt = $pdo->prepare('SELECT * FROM colors WHERE event_id = :eid ORDER BY sort_order ASC, id ASC');
        $colStmt->execute(['eid' => $eventId]);
        $colors = $colStmt->fetchAll();

        // واکشی ماتریس امتیازات
        $ptsStmt = $pdo->prepare('
            SELECT cp.color_id, cp.rank, cp.points 
            FROM color_points cp
            INNER JOIN colors c ON c.id = cp.color_id
            WHERE c.event_id = :eid
        ');
        $ptsStmt->execute(['eid' => $eventId]);
        $pointsMatrix = [];
        foreach ($ptsStmt->fetchAll() as $pt) {
            $pointsMatrix[$pt['color_id']][$pt['rank']] = (int)$pt['points'];
        }

        // واکشی تیم‌ها
        $tmStmt = $pdo->prepare('
            SELECT tm.*, (SELECT COUNT(*) FROM claims WHERE team_id = tm.id) as claims_count
            FROM teams tm 
            WHERE tm.event_id = :eid 
            ORDER BY tm.name ASC
        ');
        $tmStmt->execute(['eid' => $eventId]);
        $teams = $tmStmt->fetchAll();

        // واکشی تگ‌ها
        $tgStmt = $pdo->prepare('
            SELECT 
                tg.*,
                c.name as color_name,
                c.hex as color_hex,
                (SELECT COUNT(*) FROM claims WHERE tag_id = tg.id) as claims_count
            FROM tags tg
            LEFT JOIN colors c ON c.id = tg.color_id
            WHERE tg.event_id = :eid
            ORDER BY tg.id ASC
        ');
        $tgStmt->execute(['eid' => $eventId]);
        $tags = $tgStmt->fetchAll();

        // واکشی لیدربورد
        $leaderboard = get_event_leaderboard($pdo, $eventId);

        // واکشی ثبت‌ها به تفکیک تگ
        $claimsByTag = get_event_claims_by_tags($pdo, $eventId);

        render_view('admin_event', [
            'page_title' => 'مدیریت ' . e($event['name']),
            'event' => $event,
            'colors' => $colors,
            'points_matrix' => $pointsMatrix,
            'teams' => $teams,
            'tags' => $tags,
            'leaderboard' => $leaderboard,
            'claims_by_tag' => $claimsByTag,
        ]);
        exit;
    }

    // هر مسیر دیگر در ادمین -> ۴۰۴
    show_404();
}

// ==============================================================================
// مسیر اسکن تگ NFC (/map/<code>)
// کد باید دقیقا ۲۲ کاراکتر از [A-Za-z0-9_-] باشد
// ==============================================================================
if (preg_match('/^[A-Za-z0-9_-]{22}$/', $uri)) {
    handle_scan_request($pdo, $uri);
    exit;
}

// هر مسیر ناشناخته یا غیرمعتبر دیگر -> ۴۰۴ همسان
show_404();
