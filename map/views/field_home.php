<?php
/**
 * NFC Event Tag Scoring - Field work home (placers & admins)
 * محتوای کامل (نصب/تأیید/جمع‌آوری) در بخش بعدی اضافه می‌شود.
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$active_nav = 'field';
?>
<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">کار میدانی</h1>
        <p class="page-subtitle">سلام <?= e($me['display_name'] ?: $me['username']) ?>! برای نصب، تأیید یا جمع‌آوری، کافی است با همین گوشی و همین مرورگر تگ را لمس کنید.</p>
    </div>
</div>
