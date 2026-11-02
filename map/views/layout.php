<?php
/**
 * NFC Event Tag Scoring - Base Layout Template
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
$isLoggedIn = function_exists('is_admin_logged_in') && is_admin_logged_in();
$adminUser = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '';
// صفحات عمومی شرکت‌کنندگان (اسکن تگ و لیدربورد عمومی) بدون هدر پنل مدیریت نمایش داده می‌شوند
$isPublicView = !empty($body_class) && (strpos($body_class, 'is-scan-view') !== false || strpos($body_class, 'is-board-view') !== false);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= isset($page_title) ? e($page_title) . ' | ' : '' ?>سامانه امتیازدهی تگ NFC</title>
    <meta name="robots" content="noindex, nofollow">
    <?php $brandPalette = (isset($theme_palette) && is_array($theme_palette)) ? $theme_palette : default_brand_palette(); ?>
    <meta name="theme-color" content="<?= $isPublicView && strpos($body_class, 'is-scan-view') !== false ? '#0c0a1d' : e($brandPalette[2]) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/theme.css')) ?>">
    <style><?= brand_css_vars($brandPalette) ?></style>
</head>
<body class="app-body<?= !empty($body_class) ? ' ' . e($body_class) : '' ?>">
    <?= liquid_background_html() ?>
    <?php if (!$isPublicView): ?>
    <header class="app-header">
        <div class="header-container">
            <div class="header-brand">
                <a href="<?= e($isLoggedIn && function_exists('staff_home_url') ? staff_home_url() : path_url('/admin/login')) ?>" class="brand-link">
                    <span class="brand-icon">🏷️</span>
                    <span class="brand-text">امتیازدهی تگ‌های NFC</span>
                </a>
            </div>

            <?php if ($isLoggedIn): ?>
            <nav class="header-nav">
                <?php if (function_exists('is_admin_role') && is_admin_role()): ?>
                    <a href="<?= e(path_url('/admin/events')) ?>" class="nav-link <?= (isset($active_nav) && $active_nav === 'events') ? 'active' : '' ?>">رویدادها</a>
                    <a href="<?= e(path_url('/admin/venues')) ?>" class="nav-link <?= (isset($active_nav) && $active_nav === 'venues') ? 'active' : '' ?>">محل‌ها</a>
                    <a href="<?= e(path_url('/admin/users')) ?>" class="nav-link <?= (isset($active_nav) && $active_nav === 'users') ? 'active' : '' ?>">کاربران</a>
                <?php endif; ?>
                <a href="<?= e(path_url('/admin/field')) ?>" class="nav-link <?= (isset($active_nav) && $active_nav === 'field') ? 'active' : '' ?>">کار میدانی</a>
                <a href="<?= e(path_url('/admin/password')) ?>" class="nav-link <?= (isset($active_nav) && $active_nav === 'password') ? 'active' : '' ?>">تغییر رمز</a>
                <a href="<?= e(path_url('/admin/logout')) ?>" class="nav-link logout-link">خروج (<?= e($adminUser) ?>)</a>
            </nav>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <main class="app-main">
        <div class="main-container">
            <?php
            // نمایش پیام‌های Flash
            if (function_exists('flash_get')):
                $flashes = ['success', 'error', 'warning', 'info', 'auth_error'];
                foreach ($flashes as $fKey):
                    $fData = flash_get($fKey);
                    if ($fData):
                        $msgText = is_array($fData) ? $fData['message'] : $fData;
                        $msgType = is_array($fData) && isset($fData['type']) ? $fData['type'] : ($fKey === 'auth_error' ? 'error' : $fKey);
                        $typeClass = in_array($msgType, ['success', 'error', 'warning', 'info'], true) ? $msgType : 'info';
            ?>
                <div class="alert alert-<?= e($typeClass) ?>" role="alert">
                    <div class="alert-content">
                        <?php if ($typeClass === 'success'): ?><span class="alert-icon">✓</span><?php endif; ?>
                        <?php if ($typeClass === 'error'): ?><span class="alert-icon">✕</span><?php endif; ?>
                        <?php if ($typeClass === 'warning'): ?><span class="alert-icon">⚠</span><?php endif; ?>
                        <?php if ($typeClass === 'info'): ?><span class="alert-icon">ℹ</span><?php endif; ?>
                        <span><?= e($msgText) ?></span>
                    </div>
                </div>
            <?php
                    endif;
                endforeach;
            endif;
            ?>

            <?= isset($content) ? $content : '' ?>
        </div>
    </main>

    <footer class="app-footer">
        <div class="footer-container">
            <p class="footer-credit-line1">Powered by Asva Studio</p>
            <?php if (!$isPublicView): ?>
                <p class="footer-credit-line2">توسعه یافته توسط شرکت داده‌پردازان اسوا</p>
            <?php endif; ?>
        </div>
    </footer>

    <?php if (isset($extra_scripts) && is_array($extra_scripts)): ?>
        <?php foreach ($extra_scripts as $script): ?>
            <script src="<?= e(asset_url($script)) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
