<?php
/**
 * NFC Event Tag Scoring - Generic friendly message page (errors, limits, expired forms)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$msgIcon = isset($message_icon) ? $message_icon : 'ℹ️';
?>

<div class="error-container text-center">
    <div class="error-card">
        <div class="error-icon"><?= e($msgIcon) ?></div>
        <h1 class="error-title"><?= e(isset($message_title) ? $message_title : 'پیام سامانه') ?></h1>
        <p class="error-desc text-muted"><?= e(isset($message_text) ? $message_text : '') ?></p>
        <?php if (!empty($message_link)): ?>
            <div class="error-actions mt-4">
                <a href="<?= e($message_link) ?>" class="btn btn-outline-primary"><?= e(isset($message_link_text) ? $message_link_text : 'بازگشت') ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
