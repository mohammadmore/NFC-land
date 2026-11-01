<?php
/**
 * NFC Event Tag Scoring - 404 Not Found View
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
?>

<div class="error-container text-center">
    <div class="error-card">
        <div class="error-icon">🔍</div>
        <h1 class="error-title">صفحه پیدا نشد (۴۰۴)</h1>
        <p class="error-desc text-muted">
            آدرس مورد نظر شما در سامانه وجود ندارد یا کد تگ معتبر نمی‌باشد.
        </p>
        <div class="error-actions mt-4">
            <a href="<?= e(path_url('/admin/events')) ?>" class="btn btn-outline-primary">
                ورود به صفحه اصلی
            </a>
        </div>
    </div>
</div>
