<?php
/**
 * NFC Event Tag Scoring - Field work (کار میدانی): پنل نصب، تأیید و جمع‌آوری تگ‌ها
 *
 * وقتی کادر برگزاری (مدیر یا نصاب) که در همین مرورگر لاگین است تگ را لمس کند،
 * به جای فرم ثبت امتیاز، «پنل تگ» باز می‌شود. این لمس هرگز امتیاز ثبت نمی‌کند.
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * حالت‌های کار میدانی
 */
function field_modes() {
    return [
        'install' => ['label' => 'نصب', 'icon' => '📍'],
        'verify' => ['label' => 'تأیید', 'icon' => '✔️'],
        'collect' => ['label' => 'جمع‌آوری', 'icon' => '📦'],
    ];
}

function field_mode() {
    $m = isset($_SESSION['field_mode']) ? $_SESSION['field_mode'] : 'install';
    return array_key_exists($m, field_modes()) ? $m : 'install';
}

/**
 * دریافت تگ همراه با رویداد، رنگ و نقطه
 */
function field_get_tag($pdo, $where, $value) {
    $col = $where === 'code' ? 'tg.code' : 'tg.id';
    $stmt = $pdo->prepare("
        SELECT tg.*, e.name AS event_name, e.venue_id, e.positions_count, e.is_active AS event_active,
               c.name AS color_name, c.hex AS color_hex,
               v.name AS venue_name,
               pu.username AS placed_by_name, pu.display_name AS placed_by_display,
               vu.username AS verified_by_name, vu.display_name AS verified_by_display,
               cu.username AS collected_by_name, cu.display_name AS collected_by_display
        FROM tags tg
        INNER JOIN events e ON e.id = tg.event_id
        LEFT JOIN colors c ON c.id = tg.color_id
        LEFT JOIN venues v ON v.id = e.venue_id
        LEFT JOIN admins pu ON pu.id = tg.placed_by
        LEFT JOIN admins vu ON vu.id = tg.verified_by
        LEFT JOIN admins cu ON cu.id = tg.collected_by
        WHERE {$col} = :val LIMIT 1
    ");
    $stmt->execute(['val' => $value]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row ?: null;
}

/**
 * نمایش پنل تگ برای کادر برگزاری
 *
 * @param string $returnTo 'scan' (باز شده با لمس تگ) یا 'id' (باز شده از فهرست)
 */
function render_tag_panel($pdo, $tag, $me, $returnTo) {
    $isAdmin = ($me['role'] === 'admin');
    $spot = !empty($tag['spot_id']) ? get_spot_full($pdo, $tag['spot_id']) : null;
    $structure = !empty($tag['venue_id']) ? get_venue_structure($pdo, $tag['venue_id'], $tag['event_id']) : null;

    $claims = [];
    $lastClaim = null;
    if ($isAdmin) {
        $cs = $pdo->prepare('
            SELECT cl.`rank` AS claim_rank, cl.created_at, tm.name AS team_name
            FROM claims cl INNER JOIN teams tm ON tm.id = cl.team_id
            WHERE cl.tag_id = :t ORDER BY cl.`rank` ASC
        ');
        $cs->execute(['t' => (int)$tag['id']]);
        $claims = $cs->fetchAll();
        foreach ($claims as $c) {
            if ($lastClaim === null || $c['created_at'] > $lastClaim) {
                $lastClaim = $c['created_at'];
            }
        }
    }

    $lg = $pdo->prepare('
        SELECT l.action, l.note, l.created_at, a.username, a.display_name
        FROM tag_log l LEFT JOIN admins a ON a.id = l.user_id
        WHERE l.tag_id = :t ORDER BY l.id DESC LIMIT 15
    ');
    $lg->execute(['t' => (int)$tag['id']]);

    render_view('field_tag', [
        'page_title' => 'پنل تگ - ' . $tag['name'],
        'tag' => $tag,
        'spot' => $spot,
        'structure' => $structure,
        'me' => $me,
        'is_admin' => $isAdmin,
        'claims' => $claims,
        'last_claim' => $lastClaim,
        'log' => $lg->fetchAll(),
        'mode' => field_mode(),
        'return_to' => $returnTo,
        'panel_url' => $returnTo === 'scan' ? path_url('/' . $tag['code']) : path_url('/admin/field/tag/' . (int)$tag['id']),
    ]);
    exit;
}

/**
 * مسیرهای میدانی (مدیر و نصاب): /admin/field/... و /admin/photo/...
 *
 * @return bool
 */
function handle_field_routes($pdo, $adminPath, $me) {
    // نمایش عکس محرمانه نقطه‌ها
    if (preg_match('#^photo/(\d+)/(near|wide)(/thumb)?$#', $adminPath, $pm)) {
        serve_spot_photo($pdo, (int)$pm[1], $pm[2], !empty($pm[3]));
    }

    if ($adminPath !== 'field' && strpos($adminPath, 'field/') !== 0) {
        return false;
    }
    $isAdmin = ($me['role'] === 'admin');

    // صفحه اصلی کار میدانی
    if ($adminPath === 'field') {
        $events = $pdo->query("
            SELECT e.id, e.name, e.is_active, e.venue_id, v.name AS venue_name,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id) AS total,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id AND t.status = 'new') AS st_new,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id AND t.status = 'placed') AS st_placed,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id AND t.status = 'verified') AS st_verified,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id AND t.status = 'collected') AS st_collected,
                (SELECT COUNT(*) FROM tags t WHERE t.event_id = e.id AND t.status = 'missing') AS st_missing
            FROM events e LEFT JOIN venues v ON v.id = e.venue_id
            ORDER BY e.is_active DESC, e.id DESC
        ")->fetchAll();
        render_view('field_home', [
            'page_title' => 'کار میدانی',
            'me' => $me,
            'events' => $events,
            'mode' => field_mode(),
        ]);
        exit;
    }

    // انتخاب حالت کار (نصب / تأیید / جمع‌آوری)
    if ($adminPath === 'field/mode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $m = isset($_POST['mode']) ? $_POST['mode'] : '';
        if (array_key_exists($m, field_modes())) {
            $_SESSION['field_mode'] = $m;
        }
        $ret = isset($_POST['return']) && preg_match('#^/[A-Za-z0-9_/\-]*$#', $_POST['return']) ? $_POST['return'] : path_url('/admin/field');
        header('Location: ' . $ret);
        exit;
    }

    // ساخت سریع طبقه یا بخش حین نصب (پاسخ JSON تا عکس‌های انتخاب‌شده از دست نروند)
    if (preg_match('#^field/venue/(\d+)/(floors|zones)$#', $adminPath, $vm) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            json_response(['success' => false, 'message' => 'صفحه منقضی شده است؛ آن را دوباره باز کنید.'], 403);
        }
        $venueId = (int)$vm[1];
        $vChk = $pdo->prepare('SELECT id FROM venues WHERE id = :id');
        $vChk->execute(['id' => $venueId]);
        if (!$vChk->fetch()) {
            json_response(['success' => false, 'message' => 'محل برگزاری پیدا نشد.'], 404);
        }
        $vChk->closeCursor();
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $newId = $vm[2] === 'floors'
            ? venue_create_floor($pdo, $venueId, $name)
            : venue_create_zone($pdo, $venueId, isset($_POST['floor_id']) ? (int)$_POST['floor_id'] : 0, $name);
        // پیام flash لازم نیست (پاسخ JSON است)
        flash_get('success');
        $err = flash_get('error');
        if (!$newId) {
            json_response(['success' => false, 'message' => $err ? $err['message'] : 'خطا در ثبت.'], 422);
        }
        json_response(['success' => true, 'id' => $newId, 'name' => str_limit_u($name, 100)]);
    }

    // پنل تگ از طریق فهرست (بدون لمس تگ)
    if (preg_match('#^field/tag/(\d+)$#', $adminPath, $tm)) {
        $tag = field_get_tag($pdo, 'id', (int)$tm[1]);
        if (!$tag) {
            show_404();
        }
        render_tag_panel($pdo, $tag, $me, 'id');
    }

    // عملیات روی تگ
    if (preg_match('#^field/tag/(\d+)/(place|photos|spot-edit|verify|collect|missing|unassign)$#', $adminPath, $am) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $tag = field_get_tag($pdo, 'id', (int)$am[1]);
        if (!$tag) {
            show_404();
        }
        $action = $am[2];
        $back = (isset($_POST['return']) && $_POST['return'] === 'scan') ? path_url('/' . $tag['code']) : path_url('/admin/field/tag/' . (int)$tag['id']);
        $now = gmdate('Y-m-d H:i:s');
        $uid = (int)$me['id'];
        $tagId = (int)$tag['id'];
        $eventId = (int)$tag['event_id'];

        if (in_array($action, ['missing', 'unassign'], true) && !$isAdmin) {
            flash_set('error', 'این عملیات فقط برای مدیران مجاز است.', 'error');
            header('Location: ' . $back);
            exit;
        }

        if ($action === 'place') {
            field_action_place($pdo, $tag, $me, $now);
        } elseif ($action === 'photos' || $action === 'spot-edit') {
            $spot = !empty($tag['spot_id']) ? get_spot_full($pdo, $tag['spot_id']) : null;
            if (!$spot) {
                flash_set('error', 'این تگ هنوز در نقطه‌ای نصب نشده است.', 'error');
            } elseif ($action === 'photos') {
                $n = field_store_photos($pdo, $spot, true);
                if ($n > 0) {
                    tag_log_add($pdo, $tagId, $eventId, $uid, 'photos');
                    flash_set('success', fa_num($n) . ' عکس ذخیره شد.', 'success');
                } elseif (!flash_has('error')) {
                    flash_set('warning', 'عکسی انتخاب نشده بود.', 'warning');
                }
            } else {
                $name = isset($_POST['spot_name']) ? str_limit_u($_POST['spot_name'], 150) : '';
                if ($name === '') {
                    flash_set('error', 'نام نقطه نمی‌تواند خالی باشد.', 'error');
                } else {
                    $pdo->prepare('UPDATE spots SET name = :n, description = :d WHERE id = :id')->execute([
                        'n' => $name,
                        'd' => isset($_POST['spot_desc']) ? str_limit_u($_POST['spot_desc'], 500) : null,
                        'id' => (int)$spot['id'],
                    ]);
                    tag_log_add($pdo, $tagId, $eventId, $uid, 'spot_edit');
                    flash_set('success', 'اطلاعات نقطه ذخیره شد.', 'success');
                }
            }
        } elseif ($action === 'verify') {
            if (empty($tag['spot_id'])) {
                flash_set('error', 'ابتدا محل نصب این تگ را ثبت کنید.', 'error');
            } else {
                $pdo->prepare("UPDATE tags SET status = 'verified', verified_at = :n, verified_by = :u WHERE id = :id")->execute(['n' => $now, 'u' => $uid, 'id' => $tagId]);
                tag_log_add($pdo, $tagId, $eventId, $uid, 'verified');
                flash_set('success', '✔️ تگ «' . $tag['name'] . '» تأیید شد.', 'success');
            }
        } elseif ($action === 'collect') {
            if ($tag['status'] === 'collected') {
                flash_set('info', 'این تگ قبلاً جمع‌آوری شده است.', 'info');
            } else {
                $pdo->prepare("UPDATE tags SET status = 'collected', collected_at = :n, collected_by = :u WHERE id = :id")->execute(['n' => $now, 'u' => $uid, 'id' => $tagId]);
                tag_log_add($pdo, $tagId, $eventId, $uid, 'collected');
                flash_set('success', '📦 تگ «' . $tag['name'] . '» جمع‌آوری شد.', 'success');
            }
        } elseif ($action === 'missing') {
            $pdo->prepare("UPDATE tags SET status = 'missing' WHERE id = :id")->execute(['id' => $tagId]);
            tag_log_add($pdo, $tagId, $eventId, $uid, 'missing', isset($_POST['note']) ? $_POST['note'] : null);
            flash_set('warning', 'تگ «' . $tag['name'] . '» گم‌شده ثبت شد.', 'warning');
        } elseif ($action === 'unassign') {
            $pdo->prepare("UPDATE tags SET spot_id = NULL, status = 'new', placed_at = NULL, placed_by = NULL, verified_at = NULL, verified_by = NULL WHERE id = :id")->execute(['id' => $tagId]);
            tag_log_add($pdo, $tagId, $eventId, $uid, 'unassigned');
            flash_set('success', 'محل تگ حذف شد و تگ به حالت «آماده نصب» برگشت. (نقطه و عکس‌هایش برای استفاده بعدی باقی ماندند.)', 'success');
        }

        header('Location: ' . $back);
        exit;
    }

    show_404();
    return true;
}

/**
 * ذخیره عکس‌های ارسالی (نزدیک و باز) برای یک نقطه
 *
 * @return int تعداد عکس‌های ذخیره‌شده
 */
function field_store_photos($pdo, $spot, $replace) {
    $saved = 0;
    foreach (['near', 'wide'] as $kind) {
        $f = isset($_FILES['photo_' . $kind]) ? $_FILES['photo_' . $kind] : null;
        $t = isset($_FILES['photo_' . $kind . '_thumb']) ? $_FILES['photo_' . $kind . '_thumb'] : null;
        if (!$f) {
            continue;
        }
        $res = store_spot_photo($f, $t, (int)$spot['id'], $kind);
        if ($res['ok']) {
            $col = 'photo_' . $kind;
            if ($replace && !empty($spot[$col])) {
                delete_spot_photo_files($spot[$col]);
            }
            $pdo->prepare("UPDATE spots SET {$col} = :f WHERE id = :id")->execute(['f' => $res['file'], 'id' => (int)$spot['id']]);
            $saved++;
        } elseif (!empty($res['error'])) {
            flash_set('error', $res['error'], 'error');
        }
    }
    return $saved;
}

/**
 * ثبت نصب تگ در یک نقطه موجود یا نقطه جدید
 */
function field_action_place($pdo, $tag, $me, $now) {
    $venueId = (int)$tag['venue_id'];
    $tagId = (int)$tag['id'];
    $eventId = (int)$tag['event_id'];
    if ($venueId <= 0) {
        flash_set('error', 'برای این رویداد هنوز محل برگزاری تعیین نشده است. مدیر باید آن را در تنظیمات رویداد انتخاب کند.', 'error');
        return;
    }

    $spotChoice = isset($_POST['spot_id']) ? $_POST['spot_id'] : '';
    if ($spotChoice === 'new') {
        $zoneId = isset($_POST['zone_id']) ? (int)$_POST['zone_id'] : 0;
        if (!zone_in_venue($pdo, $zoneId, $venueId)) {
            flash_set('error', 'طبقه و بخش محل نصب را انتخاب کنید.', 'error');
            return;
        }
        $name = isset($_POST['spot_name']) ? str_limit_u($_POST['spot_name'], 150) : '';
        if ($name === '') {
            $zc = $pdo->prepare('SELECT z.name, (SELECT COUNT(*) FROM spots s WHERE s.zone_id = z.id) AS c FROM zones z WHERE z.id = :z');
            $zc->execute(['z' => $zoneId]);
            $zr = $zc->fetch();
            $zc->closeCursor();
            $name = $zr['name'] . ' - نقطه ' . fa_num((int)$zr['c'] + 1);
        }
        $pdo->prepare('INSERT INTO spots (zone_id, name, description, created_by, created_at) VALUES (:z, :n, :d, :u, :c)')->execute([
            'z' => $zoneId,
            'n' => $name,
            'd' => isset($_POST['spot_desc']) && trim($_POST['spot_desc']) !== '' ? str_limit_u($_POST['spot_desc'], 500) : null,
            'u' => (int)$me['id'],
            'c' => $now,
        ]);
        $spotId = (int)$pdo->lastInsertId();
    } else {
        $spotId = (int)$spotChoice;
        $sp = $spotId > 0 ? get_spot_full($pdo, $spotId) : null;
        if (!$sp || (int)$sp['venue_id'] !== $venueId) {
            flash_set('error', 'یک نقطه موجود را انتخاب کنید یا «نقطه جدید» را بزنید.', 'error');
            return;
        }
        // هر نقطه در یک رویداد فقط یک تگ فعال (نصب‌شده/تأییدشده) دارد
        $occ = $pdo->prepare("SELECT name FROM tags WHERE spot_id = :s AND event_id = :e AND id <> :t AND status IN ('placed', 'verified') LIMIT 1");
        $occ->execute(['s' => $spotId, 'e' => $eventId, 't' => $tagId]);
        $other = $occ->fetchColumn();
        $occ->closeCursor();
        if ($other !== false) {
            flash_set('error', 'در این نقطه قبلاً تگ «' . $other . '» نصب شده است. ابتدا آن تگ را جمع‌آوری کنید یا نقطه دیگری انتخاب کنید.', 'error');
            return;
        }
    }

    $spot = get_spot_full($pdo, $spotId);
    $photos = field_store_photos($pdo, $spot, true);

    $moved = !empty($tag['spot_id']) && (int)$tag['spot_id'] !== $spotId;
    $pdo->prepare("UPDATE tags SET spot_id = :s, status = 'placed', placed_at = :n, placed_by = :u, verified_at = NULL, verified_by = NULL, collected_at = NULL, collected_by = NULL WHERE id = :id")->execute([
        's' => $spotId,
        'n' => $now,
        'u' => (int)$me['id'],
        'id' => $tagId,
    ]);
    tag_log_add($pdo, $tagId, $eventId, (int)$me['id'], $moved ? 'moved' : 'placed', $spot['floor_name'] . ' › ' . $spot['zone_name'] . ' › ' . $spot['name']);

    $spot = get_spot_full($pdo, $spotId);
    $msg = '📍 تگ «' . $tag['name'] . '» در «' . $spot['floor_name'] . ' › ' . $spot['zone_name'] . ' › ' . $spot['name'] . '» نصب شد.';
    if (empty($spot['photo_near']) || empty($spot['photo_wide'])) {
        $msg .= ' پیشنهاد: هر دو عکس (نزدیک و باز) را برای این نقطه ثبت کنید.';
    }
    flash_set('success', $msg, 'success');
}
