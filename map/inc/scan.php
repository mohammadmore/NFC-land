<?php
/**
 * NFC Event Tag Scoring - Scan Page Logic & Claim Processing
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * پردازش درخواست اسکن تگ NFC
 *
 * @param PDO $pdo
 * @param string $code
 */
function handle_scan_request($pdo, $code) {
    $ip = get_client_ip();

    // بررسی محدودیت نرخ درخواست‌های با کد نامعتبر (حداکثر ۶۰ تلاش در ۱۰ دقیقه) پیش از هر پردازشی
    if (!check_rate_limit($pdo, 'invalid_scan', $ip, 60, 600)) {
        header('Retry-After: 600');
        show_message_page(429, 'درخواست‌های بیش از حد', 'تعداد درخواست‌های نامعتبر از این شبکه بیش از حد مجاز است. لطفاً چند دقیقه دیگر دوباره تگ را اسکن کنید.', '⏱️');
    }

    // اعتبارسنجی اولیه فرمت کد: دقیقا ۲۲ کاراکتر مجاز base64url
    if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $code)) {
        record_rate_limit($pdo, 'invalid_scan', $ip);
        show_404();
    }

    // بازیابی تگ و رویداد متناظر
    $stmt = $pdo->prepare('
        SELECT 
            t.id AS tag_id,
            t.name AS tag_name,
            t.code,
            t.color_id,
            c.name AS color_name,
            c.hex AS color_hex,
            e.id AS event_id,
            e.name AS event_name,
            e.positions_count,
            e.show_result_to_scanner,
            e.is_active,
            e.logo_path,
            e.logo_tint_mode,
            e.public_board,
            e.board_token,
            e.lock_device_team,
            e.brand_1,
            e.brand_2,
            e.brand_3
        FROM tags t
        INNER JOIN events e ON e.id = t.event_id
        LEFT JOIN colors c ON c.id = t.color_id
        WHERE t.code = :code
        LIMIT 1
    ');
    $stmt->execute(['code' => $code]);
    $tag = $stmt->fetch();
    $stmt->closeCursor();

    if (!$tag) {
        // کد در دیتابیس موجود نیست: ثبت به عنوان درخواست نامعتبر و نمایش ۴۰۴ یکسان
        record_rate_limit($pdo, 'invalid_scan', $ip);
        show_404();
    }

    // کادر برگزاری (مدیر/نصاب) لاگین‌شده: به جای فرم امتیاز، پنل تگ (نصب/تأیید/جمع‌آوری) باز می‌شود
    // و هیچ امتیازی ثبت نمی‌شود. مدیر می‌تواند با ?as=player صفحه را مثل شرکت‌کننده ببیند و تست کند.
    $GLOBALS['scan_as_player'] = false;
    if (is_admin_logged_in()) {
        $me = current_user($pdo);
        $asPlayer = ($me['role'] === 'admin' && isset($_GET['as']) && $_GET['as'] === 'player');
        if (!$asPlayer) {
            if (admin_must_change_password()) {
                flash_set('warning', 'برای امنیت سامانه، ابتدا رمز عبور خود را تغییر دهید.', 'warning');
                header('Location: ' . path_url('/admin/password'));
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                flash_set('warning', 'کادر برگزاری نمی‌تواند برای تیم‌ها امتیاز ثبت کند.', 'warning');
                header('Location: ' . path_url('/' . $code));
                exit;
            }
            render_tag_panel($pdo, field_get_tag($pdo, 'code', $code), $me, 'scan');
        }
        $GLOBALS['scan_as_player'] = true;
    }

    $colorHex = normalize_hex($tag['color_hex'], '#eab308');
    $boardUrl = ((int)$tag['public_board'] === 1 && !empty($tag['board_token'])) ? path_url('/board/' . $tag['board_token']) : null;
    $palette = get_tag_color_palette($colorHex);

    // ۱. بررسی فعال بودن رویداد
    if ((int)$tag['is_active'] === 0) {
        render_view('scan', [
            'page_title' => 'رویداد غیرفعال است',
            'tag' => $tag,
            'palette' => $palette,
            'is_inactive' => true,
            'status_message' => 'این رویداد در حال حاضر فعال نیست.',
            'status_type' => 'warning',
            'can_claim' => false,
            'board_url' => $boardUrl,
        ]);
        exit;
    }

    // پردازش ثبت تگ توسط تیم (درخواست POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handle_claim_submission($pdo, $tag);
        exit;
    }

    // پردازش درخواست نمایش صفحه اسکن (GET)
    // شمارش ثبت‌های فعلی این تگ
    $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM claims WHERE tag_id = :tag_id');
    $cntStmt->execute(['tag_id' => $tag['tag_id']]);
    $claimsCount = (int)$cntStmt->fetch()['cnt'];

    $positionsCount = (int)$tag['positions_count'];
    $isFull = ($claimsCount >= $positionsCount);

    // دریافت پیام نتیجه قبلی از Flash (در صورت وجود)
    $flashStatus = flash_get('scan_status');
    $justClaimed = !empty($flashStatus) && isset($flashStatus['type']) && $flashStatus['type'] === 'success';

    // دریافت لیست تیم‌های رویداد برای انتخاب در منو
    $teamsStmt = $pdo->prepare('
        SELECT id, name 
        FROM teams 
        WHERE event_id = :event_id 
        ORDER BY name ASC
    ');
    $teamsStmt->execute(['event_id' => $tag['event_id']]);
    $teams = $teamsStmt->fetchAll();

    // قفل گوشی روی تیم: اگر این گوشی قبلاً برای تیمی ثبت کرده، فقط همان تیم قابل انتخاب است
    $lockedTeam = null;
    if ((int)$tag['lock_device_team'] === 1) {
        $lockedTeam = get_device_lock($pdo, $tag['event_id'], get_device_id(true));
    }

    // تیم این گوشی (قفل‌شده یا آخرین تیمی که از این گوشی ثبت کرده) و ثبت قبلی آن روی همین تگ
    $myTeamId = $lockedTeam ? (int)$lockedTeam['team_id'] : get_session_team((int)$tag['event_id']);
    $myClaim = null;
    if ($myTeamId) {
        $mcStmt = $pdo->prepare('
            SELECT cl.`rank` AS claim_rank, tm.name AS team_name, COALESCE(cp.points, 0) AS points
            FROM claims cl
            INNER JOIN teams tm ON tm.id = cl.team_id
            LEFT JOIN color_points cp ON cp.color_id = :color_id AND cp.`rank` = cl.`rank`
            WHERE cl.tag_id = :tag_id AND cl.team_id = :team_id
            LIMIT 1
        ');
        $mcStmt->execute(['color_id' => (int)$tag['color_id'], 'tag_id' => $tag['tag_id'], 'team_id' => $myTeamId]);
        $myClaim = $mcStmt->fetch() ?: null;
        $mcStmt->closeCursor();
    }

    render_view('scan', [
        'page_title' => 'اسکن تگ - ' . e($tag['tag_name']),
        'tag' => $tag,
        'palette' => $palette,
        'teams' => $teams,
        'just_claimed' => $justClaimed,
        'my_claim' => $myClaim,
        'claims_count' => $claimsCount,
        'positions_count' => $positionsCount,
        'is_full' => $isFull,
        'can_claim' => (!$isFull && !$myClaim && !$justClaimed),
        'flash_status' => $flashStatus,
        'board_url' => $boardUrl,
        'locked_team' => $lockedTeam,
        'device_lock_enabled' => ((int)$tag['lock_device_team'] === 1),
    ]);
    exit;
}

/**
 * پردازش اتمیک ثبت تگ داخل تراکنش دیتابیس
 *
 * @param PDO $pdo
 * @param array $tag
 */
function handle_claim_submission($pdo, $tag) {
    $code = $tag['code'];

    // فرم منقضی‌شده (باز ماندن طولانی صفحه): بازگشت به صفحه تگ با پیام قابل فهم به جای صفحه خطا
    if (!csrf_verify()) {
        flash_set('scan_status', [
            'type' => 'warning',
            'message' => 'صفحه مدت زیادی باز مانده بود و ثبت انجام نشد. لطفاً دوباره تیم خود را انتخاب و ثبت کنید.',
        ]);
        header('Location: ' . scan_self_url($code));
        exit;
    }

    $tagId = (int)$tag['tag_id'];
    $eventId = (int)$tag['event_id'];
    $positionsCount = (int)$tag['positions_count'];

    // محدودیت نرخ ثبت: جلوگیری از ثبت انبوه خودکار از یک دستگاه/شبکه
    // (سقف شبکه بالاست چون معمولاً همه شرکت‌کنندگان پشت یک وای‌فای مشترک هستند)
    $ip = get_client_ip();
    $sessKey = session_id() !== '' ? session_id() : $ip;
    if (!check_rate_limit($pdo, 'claim_sess', $sessKey, 30, 600) || !check_rate_limit($pdo, 'claim_ip', $ip, 300, 600)) {
        flash_set('scan_status', [
            'type' => 'error',
            'message' => 'تعداد ثبت‌های شما در چند دقیقه اخیر بیش از حد مجاز است. لطفاً کمی صبر کنید.',
        ]);
        header('Location: ' . scan_self_url($code));
        exit;
    }
    record_rate_limit($pdo, 'claim_sess', $sessKey);
    record_rate_limit($pdo, 'claim_ip', $ip);

    $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    if ($teamId <= 0) {
        flash_set('scan_status', [
            'type' => 'error',
            'message' => 'لطفاً نام تیم خود را انتخاب کنید.',
        ]);
        header('Location: ' . scan_self_url($code));
        exit;
    }

    // اعتبارسنجی تعلق تیم به رویداد جاری
    $teamStmt = $pdo->prepare('SELECT id, name FROM teams WHERE id = :id AND event_id = :event_id LIMIT 1');
    $teamStmt->execute(['id' => $teamId, 'event_id' => $eventId]);
    $team = $teamStmt->fetch();
    $teamStmt->closeCursor();
    if (!$team) {
        flash_set('scan_status', [
            'type' => 'error',
            'message' => 'تیم انتخاب‌شده معتبر نیست یا به این رویداد تعلق ندارد.',
        ]);
        header('Location: ' . scan_self_url($code));
        exit;
    }

    // قفل گوشی روی تیم: شناسه دستگاه باید از قبل (هنگام باز شدن صفحه اسکن) در کوکی تنظیم شده باشد
    $deviceLockOn = ((int)$tag['lock_device_team'] === 1);
    $deviceId = null;
    if ($deviceLockOn) {
        $deviceId = get_device_id(false);
        if ($deviceId === null) {
            flash_set('scan_status', [
                'type' => 'error',
                'message' => 'برای ثبت، کوکی‌های مرورگر باید فعال باشد. لطفاً صفحه را دوباره باز کنید (از حالت ناشناس/Incognito استفاده نکنید).',
            ]);
            header('Location: ' . scan_self_url($code));
            exit;
        }
    }

    // شروع تراکنش اتمیک
    try {
        $pdo->beginTransaction();

        // ۱. قفل نوشتن روی تگ برای جلوگیری از Race Condition (MySQL: FOR UPDATE / SQLite: قفل نوشتن فوری)
        $lockedTag = lock_tag_row($pdo, $tagId);
        if (!$lockedTag) {
            $pdo->rollBack();
            show_404();
        }
        $colorId = $lockedTag['color_id'];

        // ۱.۱. قفل گوشی روی تیم: ثبت قفل (در اولین ثبت) و رد ثبت برای تیم دیگر
        if ($deviceLockOn) {
            $dHash = device_hash($deviceId);
            $insLock = db_is_mysql($pdo)
                ? $pdo->prepare('INSERT IGNORE INTO device_locks (event_id, device_hash, team_id, created_at) VALUES (:eid, :h, :tid, :c)')
                : $pdo->prepare('INSERT OR IGNORE INTO device_locks (event_id, device_hash, team_id, created_at) VALUES (:eid, :h, :tid, :c)');
            $insLock->execute(['eid' => $eventId, 'h' => $dHash, 'tid' => $teamId, 'c' => gmdate('Y-m-d H:i:s')]);

            $lock = get_device_lock($pdo, $eventId, $deviceId);
            if ($lock && (int)$lock['team_id'] !== $teamId) {
                $pdo->rollBack();
                flash_set('scan_status', [
                    'type' => 'error',
                    'message' => 'این گوشی قبلاً برای «' . $lock['team_name'] . '» ثبت کرده و فقط برای همان تیم می‌تواند ثبت کند. در صورت اشتباه به برگزارکننده مراجعه کنید.',
                ]);
                header('Location: ' . scan_self_url($code));
                exit;
            }
        }

        // ۲. شمارش ثبت‌های قبلی تگ
        $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM claims WHERE tag_id = :tag_id');
        $cntStmt->execute(['tag_id' => $tagId]);
        $cntRow = $cntStmt->fetch();
        $cntStmt->closeCursor();
        $currentClaims = (int)$cntRow['cnt'];

        // ۳. بررسی پر بودن ظرفیت ثبت‌های تگ
        if ($currentClaims >= $positionsCount) {
            $pdo->rollBack();
            flash_set('scan_status', [
                'type' => 'warning',
                'message' => 'این تگ قبلاً توسط ' . fa_num($currentClaims) . ' تیم دیگر اسکن شده و دیگر امتیازی ندارد.',
            ]);
            header('Location: ' . scan_self_url($code));
            exit;
        }

        // ۴. بررسی آیا این تیم قبلاً تگ را ثبت کرده است
        $dupStmt = $pdo->prepare('SELECT id FROM claims WHERE tag_id = :tag_id AND team_id = :team_id LIMIT 1');
        $dupStmt->execute(['tag_id' => $tagId, 'team_id' => $teamId]);
        $dupRow = $dupStmt->fetch();
        $dupStmt->closeCursor();
        if ($dupRow) {
            $pdo->rollBack();
            // تیم این گوشی مشخص شد؛ در اسکن‌های بعدی وضعیت «تیم شما ثبت کرده» نمایش داده می‌شود
            set_session_team($eventId, $teamId);
            flash_set('scan_status', [
                'type' => 'error',
                'message' => 'این تگ قبلاً توسط تیم شما ثبت شده است.',
            ]);
            header('Location: ' . scan_self_url($code));
            exit;
        }

        // ۵. درج رکورد ثبت با رتبه = تعداد فعلی + ۱
        $newRank = $currentClaims + 1;
        $nowUtc = gmdate('Y-m-d H:i:s');

        $insStmt = $pdo->prepare('
            INSERT INTO claims (tag_id, team_id, `rank`, created_at) 
            VALUES (:tag_id, :team_id, :rank, :created_at)
        ');
        $insStmt->execute([
            'tag_id' => $tagId,
            'team_id' => $teamId,
            'rank' => $newRank,
            'created_at' => $nowUtc,
        ]);

        $pdo->commit();

        // به خاطر سپردن تیم این گوشی برای نمایش وضعیت «تیم شما این تگ را ثبت کرده» در اسکن‌های بعدی
        set_session_team($eventId, $teamId);

        // ۶. محاسبه امتیاز کسب‌شده برای نمایش به اسکن‌کننده
        $earnedPoints = 0;
        if (!empty($colorId)) {
            $ptsStmt = $pdo->prepare('
                SELECT points 
                FROM color_points 
                WHERE color_id = :color_id AND `rank` = :rank 
                LIMIT 1
            ');
            $ptsStmt->execute([
                'color_id' => $colorId,
                'rank' => $newRank,
            ]);
            $ptsRow = $ptsStmt->fetch();
            if ($ptsRow) {
                $earnedPoints = (int)$ptsRow['points'];
            }
        }

        // تنظیم پیام موفقیت همراه با پیام انگیزشی تصادفی
        $succMotivation = function_exists('get_random_motivation_message') ? get_random_motivation_message() : '';

        if ((int)$tag['show_result_to_scanner'] === 1) {
            $rankWord = rank_to_words_fa($newRank);
            $msg = 'ثبت شد! شما ' . $rankWord . ' تیمی هستید که این تگ را پیدا کرده‌اید و ' . fa_num($earnedPoints) . ' امتیاز گرفتید.';
        } else {
            $msg = 'ثبت شد ✓ با موفقیت برای تیم شما ذخیره گردید.';
        }

        flash_set('scan_status', [
            'type' => 'success',
            'message' => $msg,
            'motivation_quote' => $succMotivation,
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // در صورت خطای یکتایی (Unique Constraint)، پیام دقیق داده شود
        $chkDup = $pdo->prepare('SELECT id FROM claims WHERE tag_id = :tag_id AND team_id = :team_id LIMIT 1');
        $chkDup->execute(['tag_id' => $tagId, 'team_id' => $teamId]);
        if ($chkDup->fetch()) {
            flash_set('scan_status', [
                'type' => 'error',
                'message' => 'این تگ قبلاً توسط تیم شما ثبت شده است.',
            ]);
        } else {
            // ممکن است ظرفیت پر شده باشد
            $chkCnt = $pdo->prepare('SELECT COUNT(*) as cnt FROM claims WHERE tag_id = :tag_id');
            $chkCnt->execute(['tag_id' => $tagId]);
            $cnt = (int)$chkCnt->fetch()['cnt'];
            if ($cnt >= $positionsCount) {
                flash_set('scan_status', [
                    'type' => 'warning',
                    'message' => 'این تگ قبلاً توسط ' . fa_num($cnt) . ' تیم دیگر اسکن شده و دیگر امتیازی ندارد.',
                ]);
            } else {
                flash_set('scan_status', [
                    'type' => 'error',
                    'message' => 'خطایی در ثبت رخ داد. لطفاً مجدداً امتحان کنید.',
                ]);
            }
        }
    }

    // الگوی Post/Redirect/Get برای جلوگیری از ثبت مجدد با رفرش
    header('Location: ' . scan_self_url($code));
    exit;
}

/**
 * آدرس همین صفحه اسکن (با حفظ حالت «مشاهده به‌عنوان شرکت‌کننده» برای مدیر)
 */
function scan_self_url($code) {
    return path_url('/' . $code) . (!empty($GLOBALS['scan_as_player']) ? '?as=player' : '');
}
