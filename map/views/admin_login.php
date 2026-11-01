<?php
/**
 * NFC Event Tag Scoring - Admin Login View
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
?>

<div class="auth-card-wrapper">
    <div class="card auth-card">
        <div class="card-header text-center">
            <h2 class="card-title">ورود به پنل مدیریت</h2>
            <p class="card-subtitle">سامانه داوری و امتیازدهی تگ‌های رویداد</p>
        </div>

        <div class="card-body">
            <?php if (!empty($login_error)): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span><?= e($login_error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(path_url('/admin/login')) ?>" class="auth-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username" class="form-label">نام کاربری</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           autocomplete="username" required autofocus 
                           value="<?= isset($username) ? e($username) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">رمز عبور</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           autocomplete="current-password" required>
                </div>

                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        ورود به پنل
                    </button>
                </div>
            </form>

            <div class="auth-footer-note text-center mt-3 text-muted">
                <p class="badge badge-info p-2" style="display: block; font-size: 13px; line-height: 1.6;">
                    🔑 نام کاربری پیش‌فرض: <strong>admin</strong> | رمز عبور: <strong>admin1234</strong><br>
                    <small>پس از ورود، می‌توانید از منوی «تغییر رمز» آن را تغییر دهید.</small>
                </p>
                <small class="text-muted mt-2 d-block">حداکثر ۵ تلاش در ۱۵ دقیقه مجاز است.</small>
            </div>
        </div>
    </div>
</div>
