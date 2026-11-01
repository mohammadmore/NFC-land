<?php
/**
 * NFC Event Tag Scoring - Staff user management (admins & placers)
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * رمز عبور موقت خوانا (بدون حروف مشابه مثل O/0 و l/1)
 */
function generate_temp_password($length = 10) {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * تعداد مدیران فعال (برای جلوگیری از حذف یا غیرفعال‌سازی آخرین مدیر)
 */
function count_active_admins($pdo, $excludeId = 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND is_active = 1 AND id <> :id");
    $stmt->execute(['id' => (int)$excludeId]);
    $n = (int)$stmt->fetchColumn();
    $stmt->closeCursor();
    return $n;
}

/**
 * مسیرهای مدیریت کاربران: /admin/users/...
 *
 * @return bool true اگر مسیر توسط این ماژول پردازش شد
 */
function handle_user_routes($pdo, $adminPath, $me) {
    if ($adminPath !== 'users' && strpos($adminPath, 'users/') !== 0) {
        return false;
    }
    $back = path_url('/admin/users');
    $roles = staff_roles();

    // فهرست کاربران
    if ($adminPath === 'users') {
        $users = $pdo->query('SELECT id, username, display_name, role, is_active, must_change_password, created_at FROM admins ORDER BY role ASC, id ASC')->fetchAll();
        render_view('admin_users', [
            'page_title' => 'کاربران و نصاب‌ها',
            'users' => $users,
            'me' => $me,
            'roles' => $roles,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        show_404();
    }
    require_csrf();

    // ایجاد کاربر جدید با رمز موقت (تغییر رمز در اولین ورود اجباری است)
    if ($adminPath === 'users/create') {
        $username = isset($_POST['username']) ? strtolower(trim($_POST['username'])) : '';
        $displayName = isset($_POST['display_name']) ? str_limit_u($_POST['display_name'], 100) : '';
        $role = isset($_POST['role']) && isset($roles[$_POST['role']]) ? $_POST['role'] : 'placer';

        if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
            flash_set('error', 'نام کاربری باید ۳ تا ۵۰ کاراکتر و فقط شامل حروف انگلیسی کوچک، عدد، نقطه، خط تیره یا زیرخط باشد.', 'error');
            header('Location: ' . $back);
            exit;
        }
        $temp = generate_temp_password();
        try {
            $ins = $pdo->prepare('INSERT INTO admins (username, password_hash, must_change_password, role, display_name, is_active, created_at) VALUES (:u, :p, 1, :r, :d, 1, :c)');
            $ins->execute([
                'u' => $username,
                'p' => password_hash($temp, PASSWORD_DEFAULT),
                'r' => $role,
                'd' => $displayName !== '' ? $displayName : null,
                'c' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            flash_set('error', 'این نام کاربری قبلاً ثبت شده است.', 'error');
            header('Location: ' . $back);
            exit;
        }
        flash_set('success', 'کاربر «' . $username . '» (' . $roles[$role] . ') ساخته شد. رمز موقت: ' . $temp . ' — این رمز فقط همین یک بار نمایش داده می‌شود و کاربر باید در اولین ورود آن را تغییر دهد.', 'success');
        header('Location: ' . $back);
        exit;
    }

    if (!preg_match('#^users/(\d+)/(update|toggle|reset-password|delete)$#', $adminPath, $m)) {
        show_404();
    }
    $userId = (int)$m[1];
    $action = $m[2];

    $stmt = $pdo->prepare('SELECT id, username, role, is_active FROM admins WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $target = $stmt->fetch();
    $stmt->closeCursor();
    if (!$target) {
        flash_set('error', 'کاربر پیدا نشد.', 'error');
        header('Location: ' . $back);
        exit;
    }
    $isSelf = ((int)$me['id'] === $userId);
    $isLastAdmin = ($target['role'] === 'admin' && (int)$target['is_active'] === 1 && count_active_admins($pdo, $userId) === 0);

    if ($action === 'update') {
        $displayName = isset($_POST['display_name']) ? str_limit_u($_POST['display_name'], 100) : '';
        $role = isset($_POST['role']) && isset($roles[$_POST['role']]) ? $_POST['role'] : $target['role'];
        if ($role !== 'admin' && ($isSelf || $isLastAdmin)) {
            flash_set('error', $isSelf ? 'نمی‌توانید نقش مدیر را از حساب خودتان بگیرید.' : 'حداقل یک مدیر فعال باید باقی بماند.', 'error');
            header('Location: ' . $back);
            exit;
        }
        $pdo->prepare('UPDATE admins SET display_name = :d, role = :r WHERE id = :id')->execute([
            'd' => $displayName !== '' ? $displayName : null,
            'r' => $role,
            'id' => $userId,
        ]);
        flash_set('success', 'اطلاعات کاربر «' . $target['username'] . '» ذخیره شد.', 'success');
    } elseif ($action === 'toggle') {
        $newActive = (int)$target['is_active'] === 1 ? 0 : 1;
        if ($newActive === 0 && ($isSelf || $isLastAdmin)) {
            flash_set('error', $isSelf ? 'نمی‌توانید حساب خودتان را غیرفعال کنید.' : 'حداقل یک مدیر فعال باید باقی بماند.', 'error');
            header('Location: ' . $back);
            exit;
        }
        $pdo->prepare('UPDATE admins SET is_active = :a WHERE id = :id')->execute(['a' => $newActive, 'id' => $userId]);
        flash_set('success', 'حساب «' . $target['username'] . '» ' . ($newActive ? 'فعال' : 'غیرفعال') . ' شد.' . ($newActive ? '' : ' اگر این کاربر وارد سامانه باشد، در درخواست بعدی خارج می‌شود.'), 'success');
    } elseif ($action === 'reset-password') {
        $temp = generate_temp_password();
        $pdo->prepare('UPDATE admins SET password_hash = :p, must_change_password = 1 WHERE id = :id')->execute([
            'p' => password_hash($temp, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);
        flash_set('success', 'رمز موقت جدید «' . $target['username'] . '»: ' . $temp . ' — این رمز فقط همین یک بار نمایش داده می‌شود.', 'success');
    } elseif ($action === 'delete') {
        if ($isSelf || $isLastAdmin) {
            flash_set('error', $isSelf ? 'نمی‌توانید حساب خودتان را حذف کنید.' : 'حداقل یک مدیر فعال باید باقی بماند.', 'error');
            header('Location: ' . $back);
            exit;
        }
        $pdo->prepare('DELETE FROM admins WHERE id = :id')->execute(['id' => $userId]);
        flash_set('success', 'کاربر «' . $target['username'] . '» حذف شد.', 'success');
    }

    header('Location: ' . $back);
    exit;
}
