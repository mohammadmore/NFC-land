<?php
/**
 * NFC Event Tag Scoring - One-time Setup & Database Installer
 */

define('APP_INIT', true);

$configFile = __DIR__ . '/inc/config.php';
if (!file_exists($configFile)) {
    $sampleFile = __DIR__ . '/inc/config.sample.php';
    if (file_exists($sampleFile)) {
        @copy($sampleFile, $configFile);
    }
}
$hasConfig = file_exists($configFile);

$step = 'config_check';
$error = '';
$success = '';

if ($hasConfig) {
    require_once $configFile;
    require_once __DIR__ . '/inc/db.php';
    require_once __DIR__ . '/inc/helpers.php';

    // اتصال به دیتابیس
    try {
        $pdo = get_db();
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';

        // ساخت جدول‌ها
        if ($driver === 'sqlite') {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL
                );

                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(150) NOT NULL,
                    positions_count TINYINT NOT NULL DEFAULT 4,
                    show_result_to_scanner TINYINT NOT NULL DEFAULT 1,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    logo_path VARCHAR(255) NULL,
                    logo_tint_mode TINYINT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL
                );

                CREATE TABLE IF NOT EXISTS colors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INT NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    hex CHAR(7) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS color_points (
                    color_id INT NOT NULL,
                    rank TINYINT NOT NULL,
                    points INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (color_id, rank),
                    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS teams (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    UNIQUE (event_id, name),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS tags (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    color_id INT NULL,
                    code CHAR(22) NOT NULL UNIQUE,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS claims (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tag_id INT NOT NULL,
                    team_id INT NOT NULL,
                    rank TINYINT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE (tag_id, team_id),
                    UNIQUE (tag_id, rank),
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kind VARCHAR(20) NOT NULL,
                    key_hash CHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_rate_limits_kind_key ON rate_limits (kind, key_hash, created_at);
            ');
        } else {
            $mySqlTables = [
                "CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    positions_count TINYINT NOT NULL DEFAULT 4,
                    show_result_to_scanner TINYINT NOT NULL DEFAULT 1,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    logo_path VARCHAR(255) NULL,
                    logo_tint_mode TINYINT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS colors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    hex CHAR(7) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS color_points (
                    color_id INT NOT NULL,
                    rank TINYINT NOT NULL,
                    points INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (color_id, rank),
                    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS teams (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    UNIQUE KEY unique_event_team (event_id, name),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS tags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    color_id INT NULL,
                    code CHAR(22) NOT NULL UNIQUE,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS claims (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tag_id INT NOT NULL,
                    team_id INT NOT NULL,
                    rank TINYINT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY unique_tag_team (tag_id, team_id),
                    UNIQUE KEY unique_tag_rank (tag_id, rank),
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kind VARCHAR(20) NOT NULL,
                    key_hash CHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_rate_kind_key (kind, key_hash, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ];

            foreach ($mySqlTables as $tableSql) {
                $pdo->exec($tableSql);
            }
        }

        // بررسی آیا ادمین از قبل ثبت شده است
        $admCheck = $pdo->query('SELECT COUNT(*) as cnt FROM admins');
        $adminCount = (int)$admCheck->fetch()['cnt'];

        if ($adminCount > 0) {
            $step = 'already_installed';
        } else {
            $step = 'create_admin';

            // پردازش ایجاد ادمین اولیه
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $password = isset($_POST['password']) ? $_POST['password'] : '';
                $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

                if ($username === '' || mb_strlen($password, 'UTF-8') < 8) {
                    $error = 'نام کاربری الزامی است و رمز عبور باید حداقل ۸ کاراکتر باشد.';
                } elseif ($password !== $confirm) {
                    $error = 'رمز عبور و تکرار آن یکسان نیستند.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (:u, :p, :c)');
                    $ins->execute([
                        'u' => $username,
                        'p' => $hash,
                        'c' => gmdate('Y-m-d H:i:s'),
                    ]);
                    $step = 'completed';
                    $success = 'مدیر اولیه سامانه با موفقیت ایجاد شد! اکنون فایل setup.php را فوراً از روی هاست حذف کنید.';
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'خطا در ارتباط با دیتابیس یا ساخت جداول: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب و راه‌اندازی سامانه امتیازدهی NFC</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-body">
    <header class="app-header">
        <div class="header-container">
            <div class="header-brand">
                <span class="brand-icon">⚙️</span>
                <span class="brand-text">راه‌انداز اولیه سامانه NFC</span>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="main-container max-w-700">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">مراحل نصب و استقرار سامانه</h2>
                </div>
                <div class="card-body">
                    <?php if (!$hasConfig): ?>
                        <div class="status-box status-warning">
                            <div class="status-icon">⚠️</div>
                            <div class="status-text">
                                فایل پیکربندی یافت نشد!
                            </div>
                        </div>
                        <p class="mb-3">
                            لطفاً فایل <code>inc/config.sample.php</code> را در همان پوشه با نام <code>inc/config.php</code> کپی کرده و اطلاعات اتصال به MySQL را طبق دیتابیسی که در cPanel ساخته‌اید وارد نمایید.
                        </p>
                        <p class="text-muted">پس از ذخیره فایل <code>inc/config.php</code>، این صفحه را رفرش نمایید.</p>

                    <?php elseif (!empty($error)): ?>
                        <div class="alert alert-error">
                            <span class="alert-icon">✕</span>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                    <?php elseif ($step === 'already_installed'): ?>
                        <div class="status-box status-info">
                            <div class="status-icon">🔒</div>
                            <div class="status-text">
                                نصب انجام شده است؛ این فایل را حذف کنید.
                            </div>
                        </div>
                        <p class="mt-3">
                            جداول پایگاه داده و کاربر ادمین از قبل ایجاد شده‌اند. به دلایل امنیتی، لطفاً فایل <code>setup.php</code> را از پوشه <code>map</code> در هاست حذف فرمایید.
                        </p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">ورود به پنل مدیریت</a>
                        </div>

                    <?php elseif ($step === 'completed'): ?>
                        <div class="status-box status-success">
                            <div class="status-icon">✓</div>
                            <div class="status-text">
                                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <p class="mt-3 text-danger font-bold">
                            ⚠️ مرحله بسیار مهم امنیتی: حتماً فایل <code>setup.php</code> را از طریق File Manager سی‌پنل حذف کنید تا امکان دسترسی دیگران به راه‌انداز وجود نداشته باشد.
                        </p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary btn-lg">ورود به صفحه ادمین</a>
                        </div>

                    <?php elseif ($step === 'create_admin'): ?>
                        <div class="status-box status-success">
                            <div class="status-icon">✓</div>
                            <div class="status-text">
                                جداول پایگاه داده با موفقیت ساخته شدند! اکنون مدیر اصلی را تعریف کنید:
                            </div>
                        </div>

                        <form method="POST" action="setup.php" class="mt-4">
                            <div class="form-group">
                                <label class="form-label">نام کاربری مدیر *</label>
                                <input type="text" name="username" class="form-control" required autofocus placeholder="مثال: admin">
                            </div>

                            <div class="form-group">
                                <label class="form-label">رمز عبور (حداقل ۸ کاراکتر) *</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                            </div>

                            <div class="form-group">
                                <label class="form-label">تکرار رمز عبور *</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>

                            <div class="form-actions mt-4">
                                <button type="submit" class="btn btn-success btn-lg btn-block">
                                    تکمیل نصب و ساخت مدیر
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
