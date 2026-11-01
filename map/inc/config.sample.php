<?php
/**
 * NFC Event Tag Scoring - Sample Configuration
 * 
 * این فایل را به عنوان config.php در همین پوشه کپی کرده و اطلاعات پایگاه داده
 * و آدرس سایت را با توجه به تنظیمات cPanel خود تکمیل فرمایید.
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

// ==========================================
// ۱. نوع و تنظیمات پایگاه‌داده
// ==========================================
// با مقدار 'sqlite' نیازی به ساخت دیتابیس در cPanel نیست و خودبه‌خود ساخته می‌شود.
// برای استفاده از دیتابیس MySQL در cPanel، مقدار DB_DRIVER را به 'mysql' تغییر دهید.
define('DB_DRIVER', 'sqlite'); 
define('DB_PATH', __DIR__ . '/../data/database.sqlite');

// تنظیمات پایگاه‌داده MySQL / MariaDB (در صورت تنظیم DB_DRIVER روی 'mysql')
define('DB_HOST', 'localhost');
define('DB_NAME', 'asvashop_nfc_map');
define('DB_USER', 'asvashop_nfc_user');
define('DB_PASS', 'YourStrongPasswordHere');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// ==========================================
// ۲. تنظیمات مسیردهی و دامنه
// ==========================================
// آدرس اصلی بدون اسلش انتهایی (اگر خالی بماند، از روی دامنه درخواست تشخیص داده می‌شود)
define('BASE_URL', 'https://studio.asvashop.com');

// پیشوند مسیر URL برنامه (زیرپوشه وب‌سایت). به صورت خودکار از محل نصب تشخیص داده می‌شود؛
// در صورت نیاز می‌توانید آن را دستی مثلاً روی '/map' تنظیم کنید.
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/map/index.php')), '/'));

// ==========================================
// ۳. تنظیمات سیستمی و عیب‌یابی
// ==========================================
define('APP_DEBUG', false);
define('SESSION_NAME', 'nfc_map_session');
define('APP_TIMEZONE', 'Asia/Tehran');
