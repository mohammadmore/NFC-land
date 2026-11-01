<?php
/**
 * NFC Event Tag Scoring - CSV Export (Leaderboard & Claims)
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * جلوگیری از تزریق فرمول در اکسل (CSV Injection Protection)
 */
function sanitize_csv_cell($value) {
    $str = (string)$value;
    if ($str === '') {
        return '';
    }
    $firstChar = $str[0];
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * ایجاد و دانلود فایل CSV با هدر UTF-8 BOM
 *
 * @param string $filename
 * @param array $headers
 * @param array $rows
 */
function output_csv($filename, $headers, $rows) {
    send_security_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');

    $out = fopen('php://output', 'w');
    // نوشتن BOM یوتی‌اف-۸ برای سازگاری کامل با اکسل و حروف فارسی
    fwrite($out, "\xEF\xBB\xBF");

    // نوشتن سطرهای هدر
    fputcsv($out, array_map('sanitize_csv_cell', $headers));

    // نوشتن داده‌ها
    foreach ($rows as $row) {
        fputcsv($out, array_map('sanitize_csv_cell', $row));
    }

    fclose($out);
    exit;
}

/**
 * خروجی CSV لیدربورد رویداد
 *
 * @param PDO $pdo
 * @param int $eventId
 */
function export_leaderboard_csv($pdo, $eventId) {
    require_admin();

    $stmt = $pdo->prepare('SELECT id, name FROM events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        show_404();
    }

    // دریافت داده‌های لیدربورد
    $leaderboard = get_event_leaderboard($pdo, $eventId);

    $headers = ['رتبه', 'تیم', 'مجموع امتیاز', 'تعداد تگ'];
    $rows = [];

    $rank = 1;
    foreach ($leaderboard as $row) {
        $rows[] = [
            $rank,
            $row['team_name'],
            $row['total_points'],
            $row['tag_count'],
        ];
        $rank++;
    }

    list($jdate) = get_jalali_date_time_parts(gmdate('Y-m-d H:i:s'));
    $cleanDate = str_replace('/', '-', $jdate);
    $cleanEventName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $event['name']);
    $filename = "لیدربورد_{$cleanEventName}_{$cleanDate}.csv";

    output_csv($filename, $headers, $rows);
}

/**
 * خروجی CSV ریز ثبت‌های تگ‌ها
 *
 * @param PDO $pdo
 * @param int $eventId
 */
function export_claims_csv($pdo, $eventId) {
    require_admin();

    $stmt = $pdo->prepare('SELECT id, name FROM events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        show_404();
    }

    // استخراج تمام ثبت‌های رویداد با اطلاعات تیم، تگ، رنگ و امتیاز محاسبه‌شده
    $query = "
        SELECT 
            tm.name AS team_name,
            tg.name AS tag_name,
            COALESCE(c.name, 'بدون رنگ') AS color_name,
            cl.`rank` AS claim_rank,
            COALESCE(cp.points, 0) AS points,
            cl.created_at AS claim_time
        FROM claims cl
        INNER JOIN tags tg ON tg.id = cl.tag_id
        INNER JOIN teams tm ON tm.id = cl.team_id
        LEFT JOIN colors c ON c.id = tg.color_id
        LEFT JOIN color_points cp ON cp.color_id = tg.color_id AND cp.`rank` = cl.`rank`
        WHERE tg.event_id = :event_id
        ORDER BY cl.created_at DESC, cl.id DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['event_id' => $eventId]);
    $claims = $stmt->fetchAll();

    $headers = ['تیم', 'تگ', 'رنگ', 'جایگاه', 'امتیاز', 'تاریخ', 'ساعت'];
    $rows = [];

    foreach ($claims as $item) {
        list($jDate, $jTime) = get_jalali_date_time_parts($item['claim_time']);
        $rows[] = [
            $item['team_name'],
            $item['tag_name'],
            $item['color_name'],
            $item['claim_rank'],
            $item['points'],
            $jDate,
            $jTime,
        ];
    }

    list($jdate) = get_jalali_date_time_parts(gmdate('Y-m-d H:i:s'));
    $cleanDate = str_replace('/', '-', $jdate);
    $cleanEventName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $event['name']);
    $filename = "ریز_ثبت‌ها_{$cleanEventName}_{$cleanDate}.csv";

    output_csv($filename, $headers, $rows);
}

/**
 * خروجی CSV پیوندهای تمام تگ‌های رویداد جهت رایت گروهی روی NFC
 *
 * @param PDO $pdo
 * @param int $eventId
 */
function export_tags_csv($pdo, $eventId) {
    require_admin();

    $stmt = $pdo->prepare('SELECT id, name FROM events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        show_404();
    }

    $tagsStmt = $pdo->prepare("
        SELECT tg.name as tag_name, tg.code, COALESCE(c.name, 'بدون رنگ') as color_name
        FROM tags tg
        LEFT JOIN colors c ON c.id = tg.color_id
        WHERE tg.event_id = :event_id
        ORDER BY tg.id ASC
    ");
    $tagsStmt->execute(['event_id' => $eventId]);
    $tags = $tagsStmt->fetchAll();

    $headers = ['ردیف', 'نام تگ', 'رنگ تگ', 'کد تگ', 'لینک کامل اسکن'];
    $rows = [];
    $idx = 1;
    foreach ($tags as $tg) {
        $fullLink = url('/' . $tg['code']);
        $rows[] = [
            $idx++,
            $tg['tag_name'],
            $tg['color_name'],
            $tg['code'],
            $fullLink,
        ];
    }

    list($jdate) = get_jalali_date_time_parts(gmdate('Y-m-d H:i:s'));
    $cleanDate = str_replace('/', '-', $jdate);
    $cleanEventName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $event['name']);
    $filename = "لینک‌های_تگ‌ها_{$cleanEventName}_{$cleanDate}.csv";

    output_csv($filename, $headers, $rows);
}
