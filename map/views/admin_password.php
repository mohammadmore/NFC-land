<?php
/**
 * NFC Event Tag Scoring - Admin Change Password View
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'password';
?>

<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">تغییر رمز عبور</h1>
        <p class="page-subtitle">تعیین رمز عبور جدید برای ورود به پنل مدیریت</p>
    </div>
</div>

<div class="card max-w-500">
    <div class="card-body">
        <?php if (!empty($pwd_error)): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon">✕</span>
                <span><?= e($pwd_error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(path_url('/admin/password')) ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="current_password" class="form-label">رمز عبور فعلی *</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">رمز عبور جدید (حداقل ۸ کاراکتر) *</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">تکرار رمز عبور جدید *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    به‌روزرسانی رمز عبور
                </button>
            </div>
        </form>
    </div>
</div>
