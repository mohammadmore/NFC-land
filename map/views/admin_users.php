<?php
/**
 * NFC Event Tag Scoring - Staff users management view
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'users';
$extra_scripts = ['/assets/admin.js'];
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">کاربران و نصاب‌ها</h1>
        <p class="page-subtitle">مدیران دسترسی کامل دارند. نصاب‌ها فقط می‌توانند تگ‌ها را نصب، تأیید و جمع‌آوری کنند و امتیازها، تیم‌ها و تنظیمات را نمی‌بینند.</p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h2 class="card-title">لیست کاربران (<?= fa_num(count($users)) ?>)</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>نام نمایشی و نقش</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): $uid = (int)$u['id']; $self = $uid === (int)$me['id']; ?>
                            <tr>
                                <td class="dir-ltr font-bold"><?= e($u['username']) ?><?= $self ? ' <span class="badge badge-info">شما</span>' : '' ?></td>
                                <td>
                                    <form method="POST" action="<?= e(path_url('/admin/users/' . $uid . '/update')) ?>" class="inline-form" style="flex-wrap: wrap;">
                                        <?= csrf_field() ?>
                                        <input type="text" name="display_name" value="<?= e($u['display_name']) ?>" class="form-control form-control-sm" placeholder="نام نمایشی" maxlength="100" style="max-width: 160px;">
                                        <select name="role" class="form-control form-control-sm form-select" style="max-width: 110px;">
                                            <?php foreach ($roles as $rk => $rl): ?>
                                                <option value="<?= e($rk) ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>><?= e($rl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="ذخیره">💾</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge badge-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">غیرفعال</span>
                                    <?php endif; ?>
                                    <?php if ((int)$u['must_change_password'] === 1): ?>
                                        <span class="badge badge-info" title="در اولین ورود باید رمز را تغییر دهد">رمز موقت</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons" style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        <?php if (!$self): ?>
                                            <form method="POST" action="<?= e(path_url('/admin/users/' . $uid . '/toggle')) ?>" class="inline-form">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-secondary"><?= (int)$u['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= e(path_url('/admin/users/' . $uid . '/reset-password')) ?>" class="inline-form" onsubmit="return confirm('برای این کاربر رمز موقت جدید ساخته شود؟ رمز فعلی دیگر کار نمی‌کند.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">رمز موقت جدید</button>
                                        </form>
                                        <?php if (!$self): ?>
                                            <form method="POST" action="<?= e(path_url('/admin/users/' . $uid . '/delete')) ?>" class="inline-form" onsubmit="return confirm('کاربر «<?= e($u['username']) ?>» حذف شود؟');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">افزودن کاربر</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= e(path_url('/admin/users/create')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">نام کاربری (انگلیسی) *</label>
                    <input type="text" name="username" class="form-control dir-ltr" required pattern="[a-zA-Z0-9_.\-]{3,50}" placeholder="مثال: ali.placer" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">نام نمایشی</label>
                    <input type="text" name="display_name" class="form-control" maxlength="100" placeholder="مثال: علی رضایی">
                </div>
                <div class="form-group">
                    <label class="form-label">نقش</label>
                    <select name="role" class="form-control form-select">
                        <option value="placer" selected>نصاب (فقط نصب، تأیید و جمع‌آوری)</option>
                        <option value="admin">مدیر (دسترسی کامل)</option>
                    </select>
                </div>
                <p class="text-muted" style="font-size: 0.85rem;">یک رمز موقت ساخته و یک بار نمایش داده می‌شود. کاربر باید در اولین ورود آن را تغییر دهد. پیشنهاد: پس از پایان هر رویداد، حساب نصاب‌ها را غیرفعال کنید.</p>
                <button type="submit" class="btn btn-success btn-block">ساخت کاربر</button>
            </form>
        </div>
    </div>
</div>
