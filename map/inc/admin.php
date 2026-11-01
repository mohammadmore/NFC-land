<?php
/**
 * NFC Event Tag Scoring - Admin Controller & Operations
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * دریافت لیدربورد رویداد با ترتیب دقیق مشخص‌شده
 *
 * مرتب‌سازی:
 * ۱. مجموع امتیاز نزولی
 * ۲. تعداد تگ بیشتر نزولی
 * ۳. زودتر بودن زمان آخرین ثبت صعودی
 */
function get_event_leaderboard($pdo, $eventId) {
    // استخراج تمامی تیم‌های رویداد به همراه ثبتهایش
    $sql = '
        SELECT 
            tm.id AS team_id,
            tm.name AS team_name,
            COUNT(cl.id) AS tag_count,
            COALESCE(SUM(COALESCE(cp.points, 0)), 0) AS total_points,
            MAX(cl.created_at) AS last_claim_time
        FROM teams tm
        LEFT JOIN claims cl ON cl.team_id = tm.id
        LEFT JOIN tags tg ON tg.id = cl.tag_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.`rank` = cl.`rank`
        WHERE tm.event_id = :event_id
        GROUP BY tm.id, tm.name
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['event_id' => $eventId]);
    $teams = $stmt->fetchAll();

    // مرتب‌سازی در PHP برای سازگاری بدون ابهام با قوانین خاص مرتب‌سازی
    usort($teams, function($a, $b) {
        // ۱. مجموع امتیاز
        if ((int)$a['total_points'] !== (int)$b['total_points']) {
            return (int)$b['total_points'] - (int)$a['total_points'];
        }
        // ۲. تعداد تگ بیشتر
        if ((int)$a['tag_count'] !== (int)$b['tag_count']) {
            return (int)$b['tag_count'] - (int)$a['tag_count'];
        }
        // ۳. زودتر بودن زمان آخرین ثبت (تیمی که زودتر به این امتیاز رسیده بالاتر است)
        if ($a['last_claim_time'] === null && $b['last_claim_time'] !== null) return 1;
        if ($a['last_claim_time'] !== null && $b['last_claim_time'] === null) return -1;
        if ($a['last_claim_time'] !== $b['last_claim_time']) {
            return strcmp((string)$a['last_claim_time'], (string)$b['last_claim_time']);
        }
        // در نهایت بر اساس شناسه تیم
        return (int)$a['team_id'] - (int)$b['team_id'];
    });

    return $teams;
}

/**
 * دریافت ریز ثبت‌های یک تیم در رویداد
 */
function get_team_claims_detail($pdo, $teamId, $eventId) {
    $sql = '
        SELECT 
            cl.id AS claim_id,
            cl.`rank` AS claim_rank,
            cl.created_at AS claim_time,
            tg.name AS tag_name,
            c.name AS color_name,
            c.hex AS color_hex,
            COALESCE(cp.points, 0) AS points
        FROM claims cl
        INNER JOIN tags tg ON tg.id = cl.tag_id
        LEFT JOIN colors c ON c.id = tg.color_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.`rank` = cl.`rank`
        WHERE cl.team_id = :team_id AND tg.event_id = :event_id
        ORDER BY cl.created_at ASC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['team_id' => (int)$teamId, 'event_id' => (int)$eventId]);
    return $stmt->fetchAll();
}

/**
 * دریافت ثبت‌های رویداد به تفکیک تگ
 */
function get_event_claims_by_tags($pdo, $eventId) {
    $tagsStmt = $pdo->prepare('
        SELECT tg.id, tg.name, c.name AS color_name, c.hex AS color_hex
        FROM tags tg
        LEFT JOIN colors c ON c.id = tg.color_id
        WHERE tg.event_id = :event_id
        ORDER BY tg.id ASC
    ');
    $tagsStmt->execute(['event_id' => $eventId]);
    $tags = $tagsStmt->fetchAll();

    $claimsStmt = $pdo->prepare('
        SELECT 
            cl.id AS claim_id,
            cl.tag_id,
            cl.team_id,
            cl.`rank`,
            cl.created_at,
            tm.name AS team_name,
            COALESCE(cp.points, 0) AS points
        FROM claims cl
        INNER JOIN teams tm ON tm.id = cl.team_id
        INNER JOIN tags tg ON tg.id = cl.tag_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.`rank` = cl.`rank`
        WHERE tg.event_id = :event_id
        ORDER BY cl.tag_id ASC, cl.`rank` ASC
    ');
    $claimsStmt->execute(['event_id' => $eventId]);
    $allClaims = $claimsStmt->fetchAll();

    $claimsByTag = [];
    foreach ($allClaims as $c) {
        $claimsByTag[$c['tag_id']][] = $c;
    }

    $result = [];
    foreach ($tags as $t) {
        $result[] = [
            'tag' => $t,
            'claims' => isset($claimsByTag[$t['id']]) ? $claimsByTag[$t['id']] : [],
        ];
    }
    return $result;
}

/**
 * حذف یک ثبت تگ (فقط اگر متعلق به رویداد داده‌شده باشد) و بازشماری اتمیک رتبه‌های باقی‌مانده آن تگ
 */
function delete_claim_and_reorder($pdo, $claimId, $eventId) {
    try {
        $pdo->beginTransaction();

        // یافتن ثبت و اطمینان از تعلق آن به همین رویداد
        $stmt = $pdo->prepare('
            SELECT cl.id, cl.tag_id
            FROM claims cl
            INNER JOIN tags tg ON tg.id = cl.tag_id
            WHERE cl.id = :id AND tg.event_id = :event_id
            LIMIT 1
        ');
        $stmt->execute(['id' => (int)$claimId, 'event_id' => (int)$eventId]);
        $claim = $stmt->fetch();
        $stmt->closeCursor();
        if (!$claim) {
            $pdo->rollBack();
            return false;
        }

        $tagId = (int)$claim['tag_id'];
        lock_tag_row($pdo, $tagId);

        $delStmt = $pdo->prepare('DELETE FROM claims WHERE id = :id');
        $delStmt->execute(['id' => (int)$claimId]);

        renumber_tag_claims($pdo, $tagId);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error deleting claim: ' . $e->getMessage());
        return false;
    }
}

/**
 * حذف یک تیم و بازشماری تگ‌های متأثر
 */
function delete_team_and_reorder($pdo, $teamId, $eventId) {
    try {
        $pdo->beginTransaction();

        $chk = $pdo->prepare('SELECT id FROM teams WHERE id = :id AND event_id = :event_id LIMIT 1');
        $chk->execute(['id' => (int)$teamId, 'event_id' => (int)$eventId]);
        $teamRow = $chk->fetch();
        $chk->closeCursor();
        if (!$teamRow) {
            $pdo->rollBack();
            return false;
        }

        // تگ‌های متأثر از ثبت‌های این تیم
        $affStmt = $pdo->prepare('SELECT DISTINCT tag_id FROM claims WHERE team_id = :team_id');
        $affStmt->execute(['team_id' => (int)$teamId]);
        $affectedTags = $affStmt->fetchAll(PDO::FETCH_COLUMN);

        // حذف صریح ثبت‌ها و سپس تیم (مستقل از فعال بودن Cascade)
        $pdo->prepare('DELETE FROM claims WHERE team_id = :team_id')->execute(['team_id' => (int)$teamId]);
        $delStmt = $pdo->prepare('DELETE FROM teams WHERE id = :id AND event_id = :event_id');
        $delStmt->execute(['id' => (int)$teamId, 'event_id' => (int)$eventId]);

        foreach ($affectedTags as $tagId) {
            renumber_tag_claims($pdo, $tagId);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error deleting team: ' . $e->getMessage());
        return false;
    }
}

/**
 * کپی کامل رویداد با کدهای تگ جدید
 */
function duplicate_event($pdo, $sourceEventId) {
    try {
        $pdo->beginTransaction();

        // رویداد مبدا
        $evStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $evStmt->execute(['id' => $sourceEventId]);
        $srcEvent = $evStmt->fetch();
        $evStmt->closeCursor();
        if (!$srcEvent) {
            $pdo->rollBack();
            return false;
        }

        // ساخت رویداد جدید
        $newName = 'کپی از ' . $srcEvent['name'];
        $nowUtc = gmdate('Y-m-d H:i:s');
        $insEv = $pdo->prepare('
            INSERT INTO events (name, positions_count, show_result_to_scanner, is_active, logo_tint_mode, public_board, board_token, lock_device_team, created_at)
            VALUES (:name, :positions, :show_res, :active, :tint, 0, NULL, :lock_device, :created)
        ');
        $insEv->execute([
            'name' => str_limit_u($newName, 150),
            'positions' => $srcEvent['positions_count'],
            'show_res' => $srcEvent['show_result_to_scanner'],
            'active' => $srcEvent['is_active'],
            'tint' => isset($srcEvent['logo_tint_mode']) ? (int)$srcEvent['logo_tint_mode'] : 1,
            'lock_device' => isset($srcEvent['lock_device_team']) ? (int)$srcEvent['lock_device_team'] : 0,
            'created' => $nowUtc,
        ]);
        $newEventId = (int)$pdo->lastInsertId();

        // کپی فایل لوگو با نام جدید (تا حذف لوگوی یک رویداد روی دیگری اثر نگذارد)
        $copiedLogoAbs = null;
        if (!empty($srcEvent['logo_path'])) {
            $appRoot = dirname(__DIR__);
            $srcAbs = $appRoot . '/' . $srcEvent['logo_path'];
            if (is_file($srcAbs)) {
                $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
                $newRel = 'assets/uploads/logo_' . $newEventId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (@copy($srcAbs, $appRoot . '/' . $newRel)) {
                    $copiedLogoAbs = $appRoot . '/' . $newRel;
                    @chmod($copiedLogoAbs, 0644);
                    $pdo->prepare('UPDATE events SET logo_path = :p WHERE id = :id')->execute(['p' => $newRel, 'id' => $newEventId]);
                }
            }
        }

        // کپی رنگ‌ها و جدول امتیازات
        $colorMap = []; // old_color_id => new_color_id
        $colStmt = $pdo->prepare('SELECT * FROM colors WHERE event_id = :event_id ORDER BY sort_order ASC, id ASC');
        $colStmt->execute(['event_id' => $sourceEventId]);
        $colors = $colStmt->fetchAll();

        $insCol = $pdo->prepare('INSERT INTO colors (event_id, name, hex, sort_order) VALUES (:eid, :name, :hex, :order)');
        $ptsStmt = $pdo->prepare('SELECT `rank`, points FROM color_points WHERE color_id = :color_id');
        $insPts = $pdo->prepare('INSERT INTO color_points (color_id, `rank`, points) VALUES (:cid, :rank, :pts)');

        foreach ($colors as $col) {
            $insCol->execute([
                'eid' => $newEventId,
                'name' => $col['name'],
                'hex' => $col['hex'],
                'order' => $col['sort_order'],
            ]);
            $newColorId = (int)$pdo->lastInsertId();
            $colorMap[$col['id']] = $newColorId;

            // کپی امتیازات جایگاه‌های این رنگ
            $ptsStmt->execute(['color_id' => $col['id']]);
            $ptsList = $ptsStmt->fetchAll();
            foreach ($ptsList as $pt) {
                $insPts->execute([
                    'cid' => $newColorId,
                    'rank' => $pt['rank'],
                    'pts' => $pt['points'],
                ]);
            }
        }

        // کپی تیم‌ها
        $teamsStmt = $pdo->prepare('SELECT name FROM teams WHERE event_id = :event_id ORDER BY id ASC');
        $teamsStmt->execute(['event_id' => $sourceEventId]);
        $teams = $teamsStmt->fetchAll();
        $insTeam = $pdo->prepare('INSERT INTO teams (event_id, name) VALUES (:eid, :name)');
        foreach ($teams as $tm) {
            $insTeam->execute([
                'eid' => $newEventId,
                'name' => $tm['name'],
            ]);
        }

        // کپی تگ‌ها با کدهای تصادفی جدید
        $tagsStmt = $pdo->prepare('SELECT name, color_id FROM tags WHERE event_id = :event_id ORDER BY id ASC');
        $tagsStmt->execute(['event_id' => $sourceEventId]);
        $tags = $tagsStmt->fetchAll();

        $insTag = $pdo->prepare('
            INSERT INTO tags (event_id, name, color_id, code, created_at) 
            VALUES (:eid, :name, :cid, :code, :created)
        ');
        foreach ($tags as $tg) {
            $newColorId = (!empty($tg['color_id']) && isset($colorMap[$tg['color_id']])) ? $colorMap[$tg['color_id']] : null;
            $newCode = generate_unique_tag_code($pdo);
            $insTag->execute([
                'eid' => $newEventId,
                'name' => $tg['name'],
                'cid' => $newColorId,
                'code' => $newCode,
                'created' => $nowUtc,
            ]);
        }

        $pdo->commit();
        return $newEventId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!empty($copiedLogoAbs) && is_file($copiedLogoAbs)) {
            @unlink($copiedLogoAbs);
        }
        error_log('Error duplicating event: ' . $e->getMessage());
        return false;
    }
}

/**
 * پردازش و ذخیره امن فایل لوگوی آپلودشده
 *
 * فقط تصاویر رستری (PNG/JPG/WEBP) با بررسی محتوای واقعی فایل پذیرفته می‌شوند.
 * SVG به دلیل امکان اجرای اسکریپت (XSS ذخیره‌شده) مجاز نیست.
 *
 * @return array ['ok' => bool, 'path' => string|null, 'error' => string|null]
 */
function store_uploaded_logo($file, $eventId) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => null, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'خطا در بارگذاری فایل لوگو (کد ' . (int)$file['error'] . ').'];
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        return ['ok' => false, 'path' => null, 'error' => 'حجم فایل لوگو نباید بیش از ۳ مگابایت باشد.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'path' => null, 'error' => 'فایل ارسالی معتبر نیست.'];
    }

    $allowed = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $allowed[IMAGETYPE_WEBP] = 'webp';
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info[2]]) || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
        return ['ok' => false, 'path' => null, 'error' => 'فرمت فایل لوگو نامعتبر است. فقط تصاویر PNG، JPG و WEBP مجاز هستند.'];
    }
    $ext = $allowed[$info[2]];

    $uploadDir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $filename = 'logo_' . (int)$eventId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        return ['ok' => false, 'path' => null, 'error' => 'خطا در ذخیره فایل لوگو. لطفاً مجوز نوشتن پوشه assets/uploads را بررسی کنید.'];
    }
    @chmod($uploadDir . '/' . $filename, 0644);
    return ['ok' => true, 'path' => 'assets/uploads/' . $filename, 'error' => null];
}

/**
 * حذف امن فایل لوگو (فقط فایل‌های داخل assets/uploads)
 */
function delete_logo_file($relPath) {
    if (empty($relPath) || strpos($relPath, 'assets/uploads/') !== 0 || strpos($relPath, '..') !== false) {
        return;
    }
    $abs = dirname(__DIR__) . '/' . $relPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * لیدربورد عمومی (بدون شناسه‌های داخلی) برای نمایش در صفحه عمومی
 */
function get_public_leaderboard($pdo, $eventId) {
    $rows = [];
    $rank = 1;
    foreach (get_event_leaderboard($pdo, $eventId) as $row) {
        $rows[] = [
            'rank' => $rank++,
            'team' => $row['team_name'],
            'points' => (int)$row['total_points'],
            'tags' => (int)$row['tag_count'],
        ];
    }
    return $rows;
}
