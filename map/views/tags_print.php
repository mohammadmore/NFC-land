<?php
/**
 * NFC Event Tag Scoring - Printable tag labels with QR codes
 * (QR کد به عنوان پشتیبان NFC: گوشی‌های بدون NFC هم می‌توانند تگ را اسکن کنند)
 */
if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/theme.css')) ?>">
    <style><?= brand_css_vars(event_brand_palette($event)) ?></style>
</head>
<body class="print-page">
    <?= liquid_background_html() ?>
    <div class="print-toolbar no-print">
        <div>
            <strong><?= e($event['name']) ?></strong>
            <span class="text-muted">— <?= fa_num(count($print_tags)) ?> تگ</span>
        </div>
        <label><input type="checkbox" id="optShowUrl" checked> نمایش لینک</label>
        <label><input type="checkbox" id="optLarge"> برچسب بزرگ</label>
        <button type="button" class="btn btn-primary" id="btnPrint">🖨️ چاپ</button>
        <a href="<?= e(path_url('/admin/events/' . $event['id'] . '?tab=tags')) ?>" class="btn btn-secondary">بازگشت</a>
    </div>

    <?php if (empty($print_tags)): ?>
        <p class="text-center text-muted p-4">هنوز تگی برای این رویداد ساخته نشده است.</p>
    <?php else: ?>
        <div class="print-grid" id="printGrid">
            <?php foreach ($print_tags as $pt):
                $ptUrl = url('/' . $pt['code']);
                $ptHex = normalize_hex($pt['color_hex'], '#94a3b8');
            ?>
                <div class="print-label" style="--label-color: <?= e($ptHex) ?>;">
                    <div class="print-label-stripe"></div>
                    <div class="print-label-name"><?= e($pt['name']) ?></div>
                    <div class="print-label-color"><?= e(!empty($pt['color_name']) ? $pt['color_name'] : 'بدون رنگ') ?></div>
                    <div class="print-qr" data-url="<?= e($ptUrl) ?>" aria-label="QR کد <?= e($pt['name']) ?>"></div>
                    <div class="print-label-url dir-ltr"><?= e($ptUrl) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script src="<?= e(asset_url('/assets/vendor/qrcode.js')) ?>"></script>
    <script src="<?= e(asset_url('/assets/print.js')) ?>"></script>
</body>
</html>
