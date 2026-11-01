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
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.rank = cl.rank
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
function get_team_claims_detail($pdo, $teamId) {
    $sql = '
        SELECT 
            cl.id AS claim_id,
            cl.rank AS claim_rank,
            cl.created_at AS claim_time,
            tg.name AS tag_name,
            c.name AS color_name,
            c.hex AS color_hex,
            COALESCE(cp.points, 0) AS points
        FROM claims cl
        INNER JOIN tags tg ON tg.id = cl.tag_id
        LEFT JOIN colors c ON c.id = tg.color_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.rank = cl.rank
        WHERE cl.team_id = :team_id
        ORDER BY cl.created_at ASC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['team_id' => $teamId]);
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
            cl.rank,
            cl.created_at,
            tm.name AS team_name,
            COALESCE(cp.points, 0) AS points
        FROM claims cl
        INNER JOIN teams tm ON tm.id = cl.team_id
        INNER JOIN tags tg ON tg.id = cl.tag_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.rank = cl.rank
        WHERE tg.event_id = :event_id
        ORDER BY cl.tag_id ASC, cl.rank ASC
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
 * حذف یک ثبت تگ و بازشماری اتمیک رتبه‌های باقی‌مانده آن تگ
 */
function delete_claim_and_reorder($pdo, $claimId) {
    try {
        $pdo->beginTransaction();

        // یافتن شناسه تگ
        $stmt = $pdo->prepare('SELECT id, tag_id FROM claims WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $claimId]);
        $claim = $stmt->fetch();
        if (!$claim) {
            $pdo->rollBack();
            return false;
        }

        $tagId = (int)$claim['tag_id'];

        // قفل کردن ردیف تگ در MySQL
        $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
        if ($isMysql) {
            $lockStmt = $pdo->prepare('SELECT id FROM tags WHERE id = :id FOR UPDATE');
            $lockStmt->execute(['id' => $tagId]);
        }

        // حذف ثبت مورد نظر
        $delStmt = $pdo->prepare('DELETE FROM claims WHERE id = :id');
        $delStmt->execute(['id' => $claimId]);

        // بازشماری و به‌روزرسانی رتبه‌های باقیمانده به ترتیب زمان ثبت
        $remStmt = $pdo->prepare('SELECT id FROM claims WHERE tag_id = :tag_id ORDER BY created_at ASC, id ASC');
        $remStmt->execute(['tag_id' => $tagId]);
        $remaining = $remStmt->fetchAll();

        $k = 1;
        $upStmt = $pdo->prepare('UPDATE claims SET rank = :rank WHERE id = :id');
        foreach ($remaining as $r) {
            $upStmt->execute(['rank' => $k, 'id' => $r['id']]);
            $k++;
        }

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

        // تگ‌های متأثر از ثبت‌های این تیم را پیدا می‌کنیم
        $affStmt = $pdo->prepare('SELECT DISTINCT tag_id FROM claims WHERE team_id = :team_id');
        $affStmt->execute(['team_id' => $teamId]);
        $affectedTags = $affStmt->fetchAll(PDO::FETCH_COLUMN);

        // حذف تیم (Cascade باعث حذف خودکار claims این تیم می‌شود)
        $delStmt = $pdo->prepare('DELETE FROM teams WHERE id = :id AND event_id = :event_id');
        $delStmt->execute(['id' => $teamId, 'event_id' => $eventId]);

        // بازشماری رتبه تگ‌های متأثر
        $upStmt = $pdo->prepare('UPDATE claims SET rank = :rank WHERE id = :id');
        foreach ($affectedTags as $tagId) {
            $remStmt = $pdo->prepare('SELECT id FROM claims WHERE tag_id = :tag_id ORDER BY created_at ASC, id ASC');
            $remStmt->execute(['tag_id' => $tagId]);
            $remaining = $remStmt->fetchAll();

            $k = 1;
            foreach ($remaining as $r) {
                $upStmt->execute(['rank' => $k, 'id' => $r['id']]);
                $k++;
            }
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
        if (!$srcEvent) {
            $pdo->rollBack();
            return false;
        }

        // ساخت رویداد جدید
        $newName = 'کپی از ' . $srcEvent['name'];
        $nowUtc = gmdate('Y-m-d H:i:s');
        $insEv = $pdo->prepare('
            INSERT INTO events (name, positions_count, show_result_to_scanner, is_active, created_at)
            VALUES (:name, :positions, :show_res, :active, :created)
        ');
        $insEv->execute([
            'name' => $newName,
            'positions' => $srcEvent['positions_count'],
            'show_res' => $srcEvent['show_result_to_scanner'],
            'active' => $srcEvent['is_active'],
            'created' => $nowUtc,
        ]);
        $newEventId = (int)$pdo->lastInsertId();

        // کپی رنگ‌ها و جدول امتیازات
        $colorMap = []; // old_color_id => new_color_id
        $colStmt = $pdo->prepare('SELECT * FROM colors WHERE event_id = :event_id ORDER BY sort_order ASC, id ASC');
        $colStmt->execute(['event_id' => $sourceEventId]);
        $colors = $colStmt->fetchAll();

        $insCol = $pdo->prepare('INSERT INTO colors (event_id, name, hex, sort_order) VALUES (:eid, :name, :hex, :order)');
        $ptsStmt = $pdo->prepare('SELECT rank, points FROM color_points WHERE color_id = :color_id');
        $insPts = $pdo->prepare('INSERT INTO color_points (color_id, rank, points) VALUES (:cid, :rank, :pts)');

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
        error_log('Error duplicating event: ' . $e->getMessage());
        return false;
    }
}
