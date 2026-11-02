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

    // جایگزینی تگ خراب با تگ رزرو: فهرست تگ‌های نصب‌شده همین رویداد (برای تگ آماده نصب و بدون ثبت)
    $replaceTargets = [];
    if ($tag['status'] === 'new' && empty($tag['spot_id'])) {
        $rc = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE tag_id = :t');
        $rc->execute(['t' => (int)$tag['id']]);
        if ((int)$rc->fetchColumn() === 0) {
            $rt = $pdo->prepare("
                SELECT tg.id, tg.name, tg.status, s.name AS spot_name, z.name AS zone_name, f.name AS floor_name
                FROM tags tg
                INNER JOIN spots s ON s.id = tg.spot_id
                INNER JOIN zones z ON z.id = s.zone_id
                INNER JOIN floors f ON f.id = z.floor_id
                WHERE tg.event_id = :e AND tg.id <> :t AND tg.status IN ('placed', 'verified', 'missing')
                ORDER BY f.sort_order, f.id, z.sort_order, z.id, tg.id
            ");
            $rt->execute(['e' => (int)$tag['event_id'], 't' => (int)$tag['id']]);
            $replaceTargets = $rt->fetchAll();
        }
        $rc->closeCursor();
    }
    $justReplaced = isset($_SESSION['field_replaced_tag']) && (int)$_SESSION['field_replaced_tag'] === (int)$tag['id'];
    unset($_SESSION['field_replaced_tag']);

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
        'replace_targets' => $replaceTargets,
        'just_replaced' => $justReplaced,
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

    // نقشه بخشی و فهرست کار یک رویداد
    if (preg_match('#^field/event/(\d+)$#', $adminPath, $em)) {
        field_render_event_map($pdo, (int)$em[1], $me);
    }

    // پایان جمع‌آوری: ثبت تگ‌های باقی‌مانده به‌عنوان گم‌شده (فقط مدیر)
    if (preg_match('#^field/event/(\d+)/close-collection$#', $adminPath, $em) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (!$isAdmin) {
            flash_set('error', 'این عملیات فقط برای مدیران مجاز است.', 'error');
        } else {
            $eid = (int)$em[1];
            $left = $pdo->prepare("SELECT id FROM tags WHERE event_id = :e AND status IN ('placed', 'verified')");
            $left->execute(['e' => $eid]);
            $ids = $left->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $lid) {
                $pdo->prepare("UPDATE tags SET status = 'missing' WHERE id = :id")->execute(['id' => (int)$lid]);
                tag_log_add($pdo, (int)$lid, $eid, (int)$me['id'], 'missing', 'پایان جمع‌آوری');
            }
            flash_set(count($ids) ? 'warning' : 'success', count($ids) ? fa_num(count($ids)) . ' تگ جمع‌آوری‌نشده به‌عنوان «گم‌شده» ثبت شد.' : 'همه تگ‌ها جمع‌آوری شده‌اند. 🎉', count($ids) ? 'warning' : 'success');
        }
        header('Location: ' . path_url('/admin/field/event/' . (int)$em[1] . '?filter=missing'));
        exit;
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
    if (preg_match('#^field/tag/(\d+)/(place|photos|spot-edit|verify|collect|missing|unassign|replace-code|adopt)$#', $adminPath, $am) && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        } elseif ($action === 'replace-code') {
            // تگ خراب: کد جدید برای همین تگ (ثبت‌ها و محل حفظ می‌شوند). کد قبلی فوراً باطل می‌شود.
            $newCode = generate_unique_tag_code($pdo);
            $pdo->prepare('UPDATE tags SET code = :c WHERE id = :id')->execute(['c' => $newCode, 'id' => $tagId]);
            tag_log_add($pdo, $tagId, $eventId, $uid, 'replaced_code');
            $_SESSION['field_replaced_tag'] = $tagId;
            flash_set('success', 'کد جدید ساخته شد و تگ قبلی باطل شد. لینک جدید را روی یک تگ نو رایت کنید و در همین نقطه نصب کنید.', 'success');
            header('Location: ' . path_url('/admin/field/tag/' . $tagId));
            exit;
        } elseif ($action === 'adopt') {
            // تگ رزرو (رایت‌شده و استفاده‌نشده) جایگزین تگ خراب می‌شود: کد تگ رزرو به تگ خراب منتقل می‌شود
            // تا ثبت‌ها، امتیازها و محل تگ خراب حفظ شوند؛ ردیف تگ رزرو حذف می‌شود.
            $target = field_get_tag($pdo, 'id', isset($_POST['target_tag_id']) ? (int)$_POST['target_tag_id'] : 0);
            $cc = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE tag_id = :t');
            $cc->execute(['t' => $tagId]);
            $reserveClaims = (int)$cc->fetchColumn();
            $cc->closeCursor();
            if (!$target || (int)$target['event_id'] !== $eventId || (int)$target['id'] === $tagId) {
                flash_set('error', 'تگ خراب را از فهرست انتخاب کنید.', 'error');
            } elseif ($tag['status'] !== 'new' || !empty($tag['spot_id']) || $reserveClaims > 0) {
                flash_set('error', 'فقط تگی که هنوز نصب نشده و ثبتی ندارد می‌تواند به‌عنوان تگ رزرو جایگزین شود.', 'error');
            } else {
                $pdo->beginTransaction();
                try {
                    $code = $tag['code'];
                    $pdo->prepare('DELETE FROM tags WHERE id = :id')->execute(['id' => $tagId]);
                    $pdo->prepare('UPDATE tags SET code = :c WHERE id = :id')->execute(['c' => $code, 'id' => (int)$target['id']]);
                    tag_log_add($pdo, (int)$target['id'], $eventId, $uid, 'replaced_reserve', 'تگ رزرو «' . $tag['name'] . '»');
                    $pdo->commit();
                } catch (Exception $ex) {
                    $pdo->rollBack();
                    throw $ex;
                }
                flash_set('success', '♻️ این تگ اکنون جایگزین «' . $target['name'] . '» است (ثبت‌ها و محل آن حفظ شد). آن را در همان نقطه نصب کنید و تگ خراب را بردارید.', 'success');
                header('Location: ' . path_url('/' . $code));
                exit;
            }
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

/**
 * تگ‌های مشکوک: تگ نصب‌شده‌ای که مدتی فعالیتی نداشته در حالی که تگ‌های دیگر همان بخش
 * پس از آن ثبت شده‌اند (احتمال جابه‌جایی، خرابی یا برداشته شدن در نظافت).
 *
 * @return array tag_id => true
 */
function field_stale_tags($tags, $claimsByTag, $positions, $minutes = 30) {
    $now = time();
    $lastByTag = [];
    $byZone = [];
    foreach ($tags as $t) {
        if (!in_array($t['status'], ['placed', 'verified'], true) || empty($t['zone_id'])) {
            continue;
        }
        $last = max((string)$t['placed_at'], (string)$t['verified_at'], isset($claimsByTag[$t['id']]) ? $claimsByTag[$t['id']]['last'] : '');
        $lastByTag[$t['id']] = $last;
        $byZone[$t['zone_id']][] = $t['id'];
    }
    $stale = [];
    foreach ($lastByTag as $tid => $last) {
        $cnt = isset($claimsByTag[$tid]) ? $claimsByTag[$tid]['count'] : 0;
        if ($cnt >= $positions || $last === '' || ($now - strtotime($last . ' UTC')) < $minutes * 60) {
            continue;
        }
        $peerClaimsAfter = 0;
        foreach ($tags as $t) {
            if ((int)$t['id'] === (int)$tid || empty($t['zone_id']) || !isset($byZone[$t['zone_id']]) || !in_array($tid, $byZone[$t['zone_id']], true)) {
                continue;
            }
            if (isset($claimsByTag[$t['id']]) && $claimsByTag[$t['id']]['last'] > $last) {
                $peerClaimsAfter += $claimsByTag[$t['id']]['count'];
            }
        }
        if ($peerClaimsAfter >= 2) {
            $stale[$tid] = true;
        }
    }
    return $stale;
}

/**
 * نقشه بخشی رویداد: تگ‌ها بر اساس طبقه و بخش، با وضعیت، عکس و (برای مدیر) ثبت‌ها
 */
function field_render_event_map($pdo, $eventId, $me) {
    $isAdmin = ($me['role'] === 'admin');
    $ev = $pdo->prepare('SELECT e.*, v.name AS venue_name FROM events e LEFT JOIN venues v ON v.id = e.venue_id WHERE e.id = :id');
    $ev->execute(['id' => $eventId]);
    $event = $ev->fetch();
    $ev->closeCursor();
    if (!$event) {
        show_404();
    }

    $ts = $pdo->prepare('
        SELECT tg.id, tg.name, tg.status, tg.spot_id, tg.placed_at, tg.verified_at, tg.collected_at,
               c.hex AS color_hex, c.name AS color_name,
               s.name AS spot_name, s.description AS spot_desc, s.photo_near, s.photo_wide,
               z.id AS zone_id, z.name AS zone_name, z.sort_order AS zone_order,
               f.id AS floor_id, f.name AS floor_name, f.sort_order AS floor_order,
               pu.username AS placed_by_name, pu.display_name AS placed_by_display
        FROM tags tg
        LEFT JOIN colors c ON c.id = tg.color_id
        LEFT JOIN spots s ON s.id = tg.spot_id
        LEFT JOIN zones z ON z.id = s.zone_id
        LEFT JOIN floors f ON f.id = z.floor_id
        LEFT JOIN admins pu ON pu.id = tg.placed_by
        WHERE tg.event_id = :e
        ORDER BY f.sort_order ASC, f.id ASC, z.sort_order ASC, z.id ASC, tg.id ASC
    ');
    $ts->execute(['e' => $eventId]);
    $tags = $ts->fetchAll();

    $claimsByTag = [];
    $cs = $pdo->prepare('SELECT cl.tag_id, COUNT(*) AS cnt, MAX(cl.created_at) AS last_at FROM claims cl INNER JOIN tags tg ON tg.id = cl.tag_id WHERE tg.event_id = :e GROUP BY cl.tag_id');
    $cs->execute(['e' => $eventId]);
    foreach ($cs->fetchAll() as $c) {
        $claimsByTag[$c['tag_id']] = ['count' => (int)$c['cnt'], 'last' => $c['last_at']];
    }
    $stale = ((int)$event['is_active'] === 1) ? field_stale_tags($tags, $claimsByTag, (int)$event['positions_count']) : [];

    $counts = array_fill_keys(array_keys(tag_statuses()), 0);
    foreach ($tags as $t) {
        if (isset($counts[$t['status']])) {
            $counts[$t['status']]++;
        }
    }

    // نقطه‌های خالی محل (از رویدادهای قبلی) برای فهرست کار نصب
    $emptySpots = [];
    if (!empty($event['venue_id'])) {
        $structure = get_venue_structure($pdo, $event['venue_id'], $eventId);
        $zoneNames = [];
        foreach ($structure['zones'] as $z) {
            $zoneNames[$z['id']] = $z;
        }
        $floorNames = [];
        foreach ($structure['floors'] as $f) {
            $floorNames[$f['id']] = $f['name'];
        }
        $usedInEvent = [];
        foreach ($tags as $t) {
            if (!empty($t['spot_id']) && $t['status'] !== 'new') {
                $usedInEvent[(int)$t['spot_id']] = true;
            }
        }
        foreach ($structure['spots'] as $s) {
            if (!isset($usedInEvent[(int)$s['id']]) && isset($zoneNames[$s['zone_id']])) {
                $z = $zoneNames[$s['zone_id']];
                $s['zone_name'] = $z['name'];
                $s['floor_name'] = isset($floorNames[$z['floor_id']]) ? $floorNames[$z['floor_id']] : '';
                $emptySpots[] = $s;
            }
        }
    }

    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    render_view('field_event', [
        'page_title' => 'نقشه و کار میدانی - ' . $event['name'],
        'event' => $event,
        'tags' => $tags,
        'claims_by_tag' => $claimsByTag,
        'stale' => $stale,
        'counts' => $counts,
        'empty_spots' => $emptySpots,
        'is_admin' => $isAdmin,
        'filter' => in_array($filter, ['all', 'todo-install', 'todo-verify', 'todo-collect', 'missing', 'stale'], true) ? $filter : 'all',
        'me' => $me,
    ]);
    exit;
}
