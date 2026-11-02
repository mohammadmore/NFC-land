<?php
/**
 * NFC Event Tag Scoring - Venues (محل برگزاری)، طبقات، بخش‌ها، نقطه‌ها و عکس‌های محرمانه
 *
 * ساختار: محل برگزاری ← طبقه ← بخش ← نقطه. نقطه‌ها و عکس‌هایشان متعلق به محل هستند
 * (نه رویداد) تا در رویدادهای بعدی همان هتل دوباره استفاده شوند. تگ هر رویداد هنگام
 * نصب به یک نقطه وصل می‌شود.
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * وضعیت‌های چرخه عمر فیزیکی تگ
 */
function tag_statuses() {
    return [
        'new' => ['label' => 'آماده نصب', 'class' => 'badge-secondary'],
        'placed' => ['label' => 'نصب‌شده', 'class' => 'badge-info'],
        'verified' => ['label' => 'تأییدشده', 'class' => 'badge-success'],
        'collected' => ['label' => 'جمع‌آوری‌شده', 'class' => 'badge-secondary'],
        'missing' => ['label' => 'گم‌شده', 'class' => 'badge-danger'],
    ];
}

function tag_status_label($status) {
    $all = tag_statuses();
    return isset($all[$status]) ? $all[$status]['label'] : $status;
}

function tag_status_class($status) {
    $all = tag_statuses();
    return isset($all[$status]) ? $all[$status]['class'] : 'badge-secondary';
}

/**
 * ثبت یک رویداد در تاریخچه فعالیت تگ
 */
function tag_log_add($pdo, $tagId, $eventId, $userId, $action, $note = null) {
    $pdo->prepare('INSERT INTO tag_log (tag_id, event_id, user_id, action, note, created_at) VALUES (:t, :e, :u, :a, :n, :c)')->execute([
        't' => (int)$tagId,
        'e' => (int)$eventId,
        'u' => $userId ? (int)$userId : null,
        'a' => $action,
        'n' => $note !== null ? str_limit_u($note, 255) : null,
        'c' => gmdate('Y-m-d H:i:s'),
    ]);
}

function tag_log_action_label($action) {
    $labels = [
        'placed' => 'نصب در نقطه',
        'moved' => 'تغییر محل',
        'verified' => 'تأیید',
        'collected' => 'جمع‌آوری',
        'missing' => 'ثبت گم‌شده',
        'unassigned' => 'حذف محل',
        'photos' => 'به‌روزرسانی عکس',
        'spot_edit' => 'ویرایش نقطه',
        'replaced_code' => 'تعویض کد (تگ نو)',
        'replaced_reserve' => 'جایگزینی با تگ رزرو',
        'reset' => 'بازگشت به آماده نصب',
    ];
    return isset($labels[$action]) ? $labels[$action] : $action;
}

/**
 * ساختار کامل یک محل: طبقات، بخش‌ها و نقطه‌ها (به همراه وضعیت اشغال در یک رویداد)
 *
 * @return array ['floors' => [...], 'zones' => [...], 'spots' => [...]]
 */
function get_venue_structure($pdo, $venueId, $eventId = 0) {
    $fl = $pdo->prepare('SELECT id, name, sort_order FROM floors WHERE venue_id = :v ORDER BY sort_order ASC, id ASC');
    $fl->execute(['v' => (int)$venueId]);
    $floors = $fl->fetchAll();

    $zn = $pdo->prepare('
        SELECT z.id, z.floor_id, z.name, z.sort_order
        FROM zones z INNER JOIN floors f ON f.id = z.floor_id
        WHERE f.venue_id = :v
        ORDER BY f.sort_order ASC, f.id ASC, z.sort_order ASC, z.id ASC
    ');
    $zn->execute(['v' => (int)$venueId]);
    $zones = $zn->fetchAll();

    $sp = $pdo->prepare('
        SELECT s.id, s.zone_id, s.name, s.description, s.photo_near, s.photo_wide, s.created_at,
               (SELECT tg.id FROM tags tg WHERE tg.spot_id = s.id AND tg.event_id = :e AND tg.status IN (\'placed\', \'verified\') LIMIT 1) AS occupied_tag_id,
               (SELECT tg.name FROM tags tg WHERE tg.spot_id = s.id AND tg.event_id = :e2 AND tg.status IN (\'placed\', \'verified\') LIMIT 1) AS occupied_tag_name
        FROM spots s
        INNER JOIN zones z ON z.id = s.zone_id
        INNER JOIN floors f ON f.id = z.floor_id
        WHERE f.venue_id = :v
        ORDER BY s.name ASC, s.id ASC
    ');
    $sp->execute(['v' => (int)$venueId, 'e' => (int)$eventId, 'e2' => (int)$eventId]);
    $spots = $sp->fetchAll();

    return ['floors' => $floors, 'zones' => $zones, 'spots' => $spots];
}

/**
 * اطلاعات کامل نقطه همراه با طبقه، بخش و محل
 */
function get_spot_full($pdo, $spotId) {
    $stmt = $pdo->prepare('
        SELECT s.*, z.name AS zone_name, z.id AS zone_id, f.name AS floor_name, f.id AS floor_id, f.venue_id
        FROM spots s
        INNER JOIN zones z ON z.id = s.zone_id
        INNER JOIN floors f ON f.id = z.floor_id
        WHERE s.id = :id LIMIT 1
    ');
    $stmt->execute(['id' => (int)$spotId]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row ?: null;
}

/**
 * آیا بخش داده‌شده متعلق به محل داده‌شده است؟
 */
function zone_in_venue($pdo, $zoneId, $venueId) {
    $stmt = $pdo->prepare('SELECT z.id FROM zones z INNER JOIN floors f ON f.id = z.floor_id WHERE z.id = :z AND f.venue_id = :v LIMIT 1');
    $stmt->execute(['z' => (int)$zoneId, 'v' => (int)$venueId]);
    $ok = (bool)$stmt->fetch();
    $stmt->closeCursor();
    return $ok;
}

/* ==========================================================================
   عکس‌های محرمانه نقطه‌ها
   در پوشه data/photos ذخیره می‌شوند (دسترسی مستقیم وب مسدود است) و فقط از
   طریق مسیر /admin/photo برای کادر لاگین‌شده نمایش داده می‌شوند، چون عملاً
   جواب مسابقه هستند.
   ========================================================================== */

function photos_dir() {
    $dir = dirname(__DIR__) . '/data/photos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/**
 * ساخت تصویر کوچک با GD (در صورت نبود GD، null)
 */
function make_thumbnail_gd($srcPath, $destPath, $maxSide = 400) {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $info = @getimagesize($srcPath);
    if (!$info) {
        return false;
    }
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($srcPath); break;
        case IMAGETYPE_PNG: $src = @imagecreatefrompng($srcPath); break;
        default:
            $src = (defined('IMAGETYPE_WEBP') && $info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) ? @imagecreatefromwebp($srcPath) : false;
    }
    if (!$src) {
        return false;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($dst, $destPath, 78);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

/**
 * ذخیره عکس آپلودشده یک نقطه (اصلی + تصویر کوچک)
 *
 * مرورگر معمولاً عکس را پیش از ارسال فشرده می‌کند و تصویر کوچک را هم می‌فرستد
 * (photo_x_thumb). در غیر این صورت سرور با GD تصویر کوچک می‌سازد.
 *
 * @return array ['ok' => bool, 'file' => string|null, 'error' => string|null]
 */
function store_spot_photo($file, $thumbFile, $spotId, $kind) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'file' => null, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'حجم عکس بیش از حد مجاز سرور است.'
            : 'خطا در بارگذاری عکس (کد ' . (int)$file['error'] . ').';
        return ['ok' => false, 'file' => null, 'error' => $msg];
    }
    if ($file['size'] > 12 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'file' => null, 'error' => 'فایل عکس معتبر نیست یا بیش از ۱۲ مگابایت است.'];
    }
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (defined('IMAGETYPE_WEBP')) {
        $allowed[IMAGETYPE_WEBP] = 'webp';
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info[2]])) {
        return ['ok' => false, 'file' => null, 'error' => 'فرمت عکس پشتیبانی نمی‌شود (فقط JPG، PNG و WEBP).'];
    }
    $ext = $allowed[$info[2]];
    $base = 'spot' . (int)$spotId . '_' . $kind . '_' . bin2hex(random_bytes(8));
    $dir = photos_dir();
    $dest = $dir . '/' . $base . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'file' => null, 'error' => 'خطا در ذخیره عکس. مجوز نوشتن پوشه data را بررسی کنید.'];
    }
    @chmod($dest, 0600);

    // تصویر کوچک: ارسالی مرورگر، یا ساخت با GD، یا در بدترین حالت همان عکس اصلی
    $thumbDest = $dir . '/' . $base . '_t.jpg';
    $thumbOk = false;
    if ($thumbFile && isset($thumbFile['error']) && $thumbFile['error'] === UPLOAD_ERR_OK && $thumbFile['size'] <= 1024 * 1024 && is_uploaded_file($thumbFile['tmp_name'])) {
        $tInfo = @getimagesize($thumbFile['tmp_name']);
        if ($tInfo && $tInfo[2] === IMAGETYPE_JPEG) {
            $thumbOk = move_uploaded_file($thumbFile['tmp_name'], $thumbDest);
        }
    }
    if (!$thumbOk) {
        $thumbOk = make_thumbnail_gd($dest, $thumbDest);
    }
    if ($thumbOk) {
        @chmod($thumbDest, 0600);
    }
    return ['ok' => true, 'file' => $base . '.' . $ext, 'error' => null];
}

function delete_spot_photo_files($fileName) {
    if (empty($fileName) || !preg_match('/^spot\d+_(near|wide)_[0-9a-f]{16}\.(jpg|png|webp)$/', $fileName)) {
        return;
    }
    $dir = photos_dir();
    $base = preg_replace('/\.(jpg|png|webp)$/', '', $fileName);
    @unlink($dir . '/' . $fileName);
    @unlink($dir . '/' . $base . '_t.jpg');
}

/**
 * آدرس نمایش عکس یک نقطه (فقط برای کادر لاگین‌شده قابل دسترسی است)
 */
function spot_photo_url($spot, $kind, $thumb = false) {
    $col = $kind === 'wide' ? 'photo_wide' : 'photo_near';
    if (empty($spot[$col])) {
        return null;
    }
    $v = substr(md5($spot[$col]), 0, 8);
    return path_url('/admin/photo/' . (int)$spot['id'] . '/' . ($kind === 'wide' ? 'wide' : 'near') . ($thumb ? '/thumb' : '')) . '?v=' . $v;
}

/**
 * ارسال فایل عکس نقطه به مرورگر (پس از احراز هویت کادر)
 */
function serve_spot_photo($pdo, $spotId, $kind, $thumb) {
    $stmt = $pdo->prepare('SELECT photo_near, photo_wide FROM spots WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$spotId]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    $fileName = $row ? ($kind === 'wide' ? $row['photo_wide'] : $row['photo_near']) : null;
    if (empty($fileName) || !preg_match('/^spot\d+_(near|wide)_[0-9a-f]{16}\.(jpg|png|webp)$/', $fileName)) {
        show_404();
    }
    $dir = photos_dir();
    $path = $dir . '/' . $fileName;
    if ($thumb) {
        $tPath = $dir . '/' . preg_replace('/\.(jpg|png|webp)$/', '', $fileName) . '_t.jpg';
        if (is_file($tPath)) {
            $path = $tPath;
        }
    }
    if (!is_file($path)) {
        show_404();
    }
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header_remove('Pragma');
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/* ==========================================================================
   مسیرهای مدیریت محل‌ها (فقط مدیر): /admin/venues/...
   ========================================================================== */

function handle_venue_routes($pdo, $adminPath, $me) {
    if ($adminPath !== 'venues' && strpos($adminPath, 'venues/') !== 0) {
        return false;
    }

    if ($adminPath === 'venues') {
        $venues = $pdo->query('
            SELECT v.*,
                (SELECT COUNT(*) FROM floors f WHERE f.venue_id = v.id) AS floors_count,
                (SELECT COUNT(*) FROM spots s INNER JOIN zones z ON z.id = s.zone_id INNER JOIN floors f ON f.id = z.floor_id WHERE f.venue_id = v.id) AS spots_count,
                (SELECT COUNT(*) FROM events e WHERE e.venue_id = v.id) AS events_count
            FROM venues v ORDER BY v.id DESC
        ')->fetchAll();
        render_view('admin_venues', ['page_title' => 'محل‌های برگزاری', 'venues' => $venues]);
        exit;
    }

    if ($adminPath === 'venues/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $name = isset($_POST['name']) ? str_limit_u($_POST['name'], 150) : '';
        if ($name === '') {
            flash_set('error', 'نام محل برگزاری نمی‌تواند خالی باشد.', 'error');
            header('Location: ' . path_url('/admin/venues'));
            exit;
        }
        $pdo->prepare('INSERT INTO venues (name, created_at) VALUES (:n, :c)')->execute(['n' => $name, 'c' => gmdate('Y-m-d H:i:s')]);
        $newId = (int)$pdo->lastInsertId();
        // اتصال مستقیم به رویداد (اگر از تنظیمات رویداد ساخته شده باشد)
        $forEvent = isset($_POST['for_event']) ? (int)$_POST['for_event'] : 0;
        if ($forEvent > 0) {
            $pdo->prepare('UPDATE events SET venue_id = :v WHERE id = :e AND venue_id IS NULL')->execute(['v' => $newId, 'e' => $forEvent]);
        }
        flash_set('success', 'محل برگزاری «' . $name . '» ساخته شد. اکنون طبقات و بخش‌های آن را تعریف کنید (نصاب‌ها هم می‌توانند حین نصب، طبقه و بخش جدید اضافه کنند).', 'success');
        header('Location: ' . path_url('/admin/venues/' . $newId));
        exit;
    }

    if (!preg_match('#^venues/(\d+)(?:/(.*))?$#', $adminPath, $m)) {
        show_404();
    }
    $venueId = (int)$m[1];
    $sub = isset($m[2]) ? $m[2] : '';
    $vStmt = $pdo->prepare('SELECT * FROM venues WHERE id = :id LIMIT 1');
    $vStmt->execute(['id' => $venueId]);
    $venue = $vStmt->fetch();
    $vStmt->closeCursor();
    if (!$venue) {
        show_404();
    }
    $back = path_url('/admin/venues/' . $venueId);

    if ($sub === '') {
        $structure = get_venue_structure($pdo, $venueId, 0);
        // تعداد استفاده هر نقطه در تگ‌های همه رویدادها
        $usage = [];
        foreach ($pdo->query('SELECT spot_id, COUNT(*) AS c FROM tags WHERE spot_id IS NOT NULL GROUP BY spot_id')->fetchAll() as $u) {
            $usage[(int)$u['spot_id']] = (int)$u['c'];
        }
        $evStmt = $pdo->prepare('SELECT id, name FROM events WHERE venue_id = :v ORDER BY id DESC');
        $evStmt->execute(['v' => $venueId]);
        render_view('admin_venue', [
            'page_title' => 'محل برگزاری - ' . $venue['name'],
            'venue' => $venue,
            'structure' => $structure,
            'usage' => $usage,
            'venue_events' => $evStmt->fetchAll(),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        show_404();
    }
    require_csrf();

    if ($sub === 'update') {
        $name = isset($_POST['name']) ? str_limit_u($_POST['name'], 150) : '';
        if ($name !== '') {
            $pdo->prepare('UPDATE venues SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $venueId]);
            flash_set('success', 'نام محل ذخیره شد.', 'success');
        }
    } elseif ($sub === 'delete') {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE venue_id = :v');
        $cnt->execute(['v' => $venueId]);
        if ((int)$cnt->fetchColumn() > 0) {
            flash_set('error', 'این محل به یک یا چند رویداد متصل است و قابل حذف نیست.', 'error');
        } else {
            $ph = $pdo->prepare('SELECT s.photo_near, s.photo_wide FROM spots s INNER JOIN zones z ON z.id = s.zone_id INNER JOIN floors f ON f.id = z.floor_id WHERE f.venue_id = :v');
            $ph->execute(['v' => $venueId]);
            foreach ($ph->fetchAll() as $p) {
                delete_spot_photo_files($p['photo_near']);
                delete_spot_photo_files($p['photo_wide']);
            }
            $pdo->prepare('UPDATE tags SET spot_id = NULL WHERE spot_id IN (SELECT s.id FROM spots s INNER JOIN zones z ON z.id = s.zone_id INNER JOIN floors f ON f.id = z.floor_id WHERE f.venue_id = :v)')->execute(['v' => $venueId]);
            $pdo->prepare('DELETE FROM venues WHERE id = :id')->execute(['id' => $venueId]);
            flash_set('success', 'محل برگزاری و همه طبقات، بخش‌ها، نقطه‌ها و عکس‌های آن حذف شد.', 'success');
            header('Location: ' . path_url('/admin/venues'));
            exit;
        }
    } elseif ($sub === 'floors/create') {
        venue_create_floor($pdo, $venueId, isset($_POST['name']) ? $_POST['name'] : '');
    } elseif ($sub === 'zones/create') {
        venue_create_zone($pdo, $venueId, isset($_POST['floor_id']) ? (int)$_POST['floor_id'] : 0, isset($_POST['name']) ? $_POST['name'] : '');
    } elseif (preg_match('#^floors/(\d+)/(update|delete)$#', $sub, $fm)) {
        $own = $pdo->prepare('SELECT id FROM floors WHERE id = :id AND venue_id = :v');
        $own->execute(['id' => (int)$fm[1], 'v' => $venueId]);
        if ($own->fetch()) {
            $own->closeCursor();
            if ($fm[2] === 'update') {
                $name = isset($_POST['name']) ? str_limit_u($_POST['name'], 100) : '';
                if ($name !== '') {
                    $pdo->prepare('UPDATE floors SET name = :n, sort_order = :o WHERE id = :id')->execute(['n' => $name, 'o' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0, 'id' => (int)$fm[1]]);
                    flash_set('success', 'طبقه ذخیره شد.', 'success');
                }
            } else {
                if (venue_scope_in_use($pdo, 'floor', (int)$fm[1])) {
                    flash_set('error', 'نقطه‌های این طبقه به تگ‌های رویدادها متصل هستند و طبقه قابل حذف نیست.', 'error');
                } else {
                    venue_delete_scope_photos($pdo, 'floor', (int)$fm[1]);
                    $pdo->prepare('DELETE FROM floors WHERE id = :id')->execute(['id' => (int)$fm[1]]);
                    flash_set('success', 'طبقه حذف شد.', 'success');
                }
            }
        }
    } elseif (preg_match('#^zones/(\d+)/(update|delete)$#', $sub, $zm)) {
        if (zone_in_venue($pdo, (int)$zm[1], $venueId)) {
            if ($zm[2] === 'update') {
                $name = isset($_POST['name']) ? str_limit_u($_POST['name'], 100) : '';
                if ($name !== '') {
                    $pdo->prepare('UPDATE zones SET name = :n, sort_order = :o WHERE id = :id')->execute(['n' => $name, 'o' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0, 'id' => (int)$zm[1]]);
                    flash_set('success', 'بخش ذخیره شد.', 'success');
                }
            } else {
                if (venue_scope_in_use($pdo, 'zone', (int)$zm[1])) {
                    flash_set('error', 'نقطه‌های این بخش به تگ‌های رویدادها متصل هستند و بخش قابل حذف نیست.', 'error');
                } else {
                    venue_delete_scope_photos($pdo, 'zone', (int)$zm[1]);
                    $pdo->prepare('DELETE FROM zones WHERE id = :id')->execute(['id' => (int)$zm[1]]);
                    flash_set('success', 'بخش حذف شد.', 'success');
                }
            }
        }
    } elseif (preg_match('#^spots/(\d+)/(update|delete)$#', $sub, $sm)) {
        $spot = get_spot_full($pdo, (int)$sm[1]);
        if ($spot && (int)$spot['venue_id'] === $venueId) {
            if ($sm[2] === 'update') {
                $name = isset($_POST['name']) ? str_limit_u($_POST['name'], 150) : '';
                $zoneId = isset($_POST['zone_id']) ? (int)$_POST['zone_id'] : (int)$spot['zone_id'];
                if (!zone_in_venue($pdo, $zoneId, $venueId)) {
                    $zoneId = (int)$spot['zone_id'];
                }
                if ($name !== '') {
                    $pdo->prepare('UPDATE spots SET name = :n, description = :d, zone_id = :z WHERE id = :id')->execute([
                        'n' => $name,
                        'd' => isset($_POST['description']) ? str_limit_u($_POST['description'], 500) : null,
                        'z' => $zoneId,
                        'id' => (int)$sm[1],
                    ]);
                    flash_set('success', 'نقطه ذخیره شد.', 'success');
                }
            } else {
                if (venue_scope_in_use($pdo, 'spot', (int)$sm[1])) {
                    flash_set('error', 'این نقطه به تگ یک رویداد متصل است و قابل حذف نیست.', 'error');
                } else {
                    delete_spot_photo_files($spot['photo_near']);
                    delete_spot_photo_files($spot['photo_wide']);
                    $pdo->prepare('DELETE FROM spots WHERE id = :id')->execute(['id' => (int)$sm[1]]);
                    flash_set('success', 'نقطه و عکس‌های آن حذف شد.', 'success');
                }
            }
        }
    } else {
        show_404();
    }

    header('Location: ' . $back);
    exit;
}

/**
 * ساخت طبقه جدید (مشترک بین پنل مدیر و پنل نصب)
 *
 * @return int|false شناسه طبقه جدید
 */
function venue_create_floor($pdo, $venueId, $name) {
    $name = str_limit_u($name, 100);
    if ($name === '') {
        flash_set('error', 'نام طبقه نمی‌تواند خالی باشد.', 'error');
        return false;
    }
    $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM floors WHERE venue_id = :v');
    $mx->execute(['v' => (int)$venueId]);
    $order = (int)$mx->fetchColumn() + 1;
    $mx->closeCursor();
    $pdo->prepare('INSERT INTO floors (venue_id, name, sort_order) VALUES (:v, :n, :o)')->execute(['v' => (int)$venueId, 'n' => $name, 'o' => $order]);
    flash_set('success', 'طبقه «' . $name . '» اضافه شد.', 'success');
    return (int)$pdo->lastInsertId();
}

/**
 * ساخت بخش جدید در یک طبقه از محل (مشترک بین پنل مدیر و پنل نصب)
 *
 * @return int|false
 */
function venue_create_zone($pdo, $venueId, $floorId, $name) {
    $name = str_limit_u($name, 100);
    $own = $pdo->prepare('SELECT id FROM floors WHERE id = :id AND venue_id = :v');
    $own->execute(['id' => (int)$floorId, 'v' => (int)$venueId]);
    $exists = (bool)$own->fetch();
    $own->closeCursor();
    if ($name === '' || !$exists) {
        flash_set('error', 'نام بخش و طبقه آن را مشخص کنید.', 'error');
        return false;
    }
    $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM zones WHERE floor_id = :f');
    $mx->execute(['f' => (int)$floorId]);
    $order = (int)$mx->fetchColumn() + 1;
    $mx->closeCursor();
    $pdo->prepare('INSERT INTO zones (floor_id, name, sort_order) VALUES (:f, :n, :o)')->execute(['f' => (int)$floorId, 'n' => $name, 'o' => $order]);
    flash_set('success', 'بخش «' . $name . '» اضافه شد.', 'success');
    return (int)$pdo->lastInsertId();
}

/**
 * آیا نقطه‌ای از این طبقه/بخش/نقطه به تگی متصل است؟
 */
function venue_scope_in_use($pdo, $scope, $id) {
    if ($scope === 'spot') {
        $sql = 'SELECT COUNT(*) FROM tags WHERE spot_id = :id';
    } elseif ($scope === 'zone') {
        $sql = 'SELECT COUNT(*) FROM tags tg INNER JOIN spots s ON s.id = tg.spot_id WHERE s.zone_id = :id';
    } else {
        $sql = 'SELECT COUNT(*) FROM tags tg INNER JOIN spots s ON s.id = tg.spot_id INNER JOIN zones z ON z.id = s.zone_id WHERE z.floor_id = :id';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int)$id]);
    $n = (int)$stmt->fetchColumn();
    $stmt->closeCursor();
    return $n > 0;
}

function venue_delete_scope_photos($pdo, $scope, $id) {
    $sql = $scope === 'zone'
        ? 'SELECT photo_near, photo_wide FROM spots WHERE zone_id = :id'
        : 'SELECT s.photo_near, s.photo_wide FROM spots s INNER JOIN zones z ON z.id = s.zone_id WHERE z.floor_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int)$id]);
    foreach ($stmt->fetchAll() as $p) {
        delete_spot_photo_files($p['photo_near']);
        delete_spot_photo_files($p['photo_wide']);
    }
}
