<?php
/**
 * NFC Event Tag Scoring - Auto Permission Fixer
 * این اسکریپت با یک بار باز شدن در مرورگر، به طور خودکار تمام پوشه‌ها را روی 755
 * و تمام فایل‌ها را روی 644 تنظیم می‌کند و سپس خود را حذف می‌نماید.
 */

define('APP_INIT', true);

$baseDir = __DIR__;

function fix_permissions($dir, &$dirCount, &$fileCount) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            // پوشه data دسترسی ۷۷۷ برای نوشتن دیتابیس، بقیه پوشه‌ها ۷۵۵
            if ($item === 'data') {
                @chmod($path, 0777);
            } else {
                @chmod($path, 0755);
            }
            $dirCount++;
            fix_permissions($path, $dirCount, $fileCount);
        } else {
            // فایل دیتابیس ۶۶۶، بقیه فایل‌ها ۶۴۴
            if (pathinfo($path, PATHINFO_EXTENSION) === 'sqlite') {
                @chmod($path, 0666);
            } else {
                @chmod($path, 0644);
            }
            $fileCount++;
        }
    }
}

$dirCount = 0;
$fileCount = 0;

// تنظیم پرمیشن خود پوشه اصلی
@chmod($baseDir, 0755);
$dirCount++;

fix_permissions($baseDir, $dirCount, $fileCount);

// حذف خودکار این فایل پس از انجام کار برای امنیت
@unlink(__FILE__);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اصلاح خودکار دسترسی‌ها</title>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8fafc; color: #0f172a; padding: 2rem; direction: rtl; text-align: center; }
        .card { max-width: 500px; margin: 3rem auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h2 { color: #16a34a; margin-bottom: 0.5rem; }
        p { color: #475569; line-height: 1.6; }
        .stats { background: #f1f5f9; padding: 1rem; border-radius: 8px; margin: 1.5rem 0; font-weight: bold; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h2>دسترسی‌ها با موفقیت اصلاح شدند!</h2>
        <div class="stats">
            تعداد پوشه‌ها روی 755: <?= $dirCount ?><br>
            تعداد فایل‌ها روی 644: <?= $fileCount ?>
        </div>
        <p>فایل تنظیم دسترسی برای امنیت بیشتر به طور خودکار حذف شد.</p>
        <a href="admin/login" class="btn">ورود به پنل مدیریت</a>
    </div>
</body>
</html>
