<?php
/**
 * بخش انتخاب دو عکس نقطه (نزدیک و باز). مرورگر عکس‌ها را پیش از ارسال فشرده می‌کند (field.js).
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
?>
<div class="field-photo-pickers">
    <label class="field-photo-picker">
        <input type="file" name="photo_near" accept="image/*" class="js-photo-input" hidden>
        <span class="field-photo-preview js-photo-preview">📷</span>
        <span class="field-photo-label">عکس نزدیک<br><small>خود تگ و جای دقیقش</small></span>
    </label>
    <label class="field-photo-picker">
        <input type="file" name="photo_wide" accept="image/*" class="js-photo-input" hidden>
        <span class="field-photo-preview js-photo-preview">🏞️</span>
        <span class="field-photo-label">عکس باز<br><small>فضای اطراف برای پیدا کردن محل</small></span>
    </label>
</div>
