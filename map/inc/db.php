<?php
/**
 * NFC Event Tag Scoring - Database Connection (PDO) & Auto-Installer
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * دریافت اتصال پایگاه داده و ساخت خودکار جداول و داده‌های اولیه
 */
function get_db() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

    if ($driver === 'sqlite') {
        $dbPath = defined('DB_PATH') ? DB_PATH : (__DIR__ . '/../data/database.sqlite');
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // فقط در صورت عدم امکان نوشتن، دسترسی بازتر داده می‌شود (هاست‌های mod_php)
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
            clearstatcache(true, $dir);
            if (!is_writable($dir)) {
                @chmod($dir, 0777);
            }
        }

        $dsn = 'sqlite:' . $dbPath;
        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        } catch (PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('خطای اتصال به SQLite: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
            error_log('SQLite connection error: ' . $e->getMessage());
            die('خطا در اتصال به پایگاه داده SQLite. لطفاً مجوز نوشتن پوشه data را بررسی کنید.');
        }
    } else {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $dbname = defined('DB_NAME') ? DB_NAME : '';
        $user = defined('DB_USER') ? DB_USER : '';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('خطای اتصال به MySQL: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
            error_log('Database connection error: ' . $e->getMessage());
            die('خطا در اتصال به دیتابیس MySQL. لطفاً مشخصات را در inc/config.php بررسی کنید.');
        }
    }

    // ساخت و آماده‌سازی خودکار جداول و مدیر اولیه در صورت عدم وجود
    ensure_database_schema($pdo, $driver);

    return $pdo;
}

/**
 * ایجاد خودکار ساختار جداول و داده‌های پیش‌فرض مسابقه بدون نیاز به نصب دستی
 */
function ensure_database_schema($pdo, $driver) {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    try {
        if ($driver === 'sqlite') {
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    must_change_password TINYINT NOT NULL DEFAULT 0,
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
                    public_board TINYINT NOT NULL DEFAULT 0,
                    board_token VARCHAR(32) NULL,
                    lock_device_team TINYINT NOT NULL DEFAULT 0,
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
                    `rank` TINYINT NOT NULL,
                    points INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (color_id, `rank`),
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
                    `rank` TINYINT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE (tag_id, team_id),
                    UNIQUE (tag_id, `rank`),
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

                CREATE TABLE IF NOT EXISTS device_locks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INT NOT NULL,
                    device_hash CHAR(64) NOT NULL,
                    team_id INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE (event_id, device_hash),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_device_locks_team ON device_locks (team_id);

                CREATE TABLE IF NOT EXISTS venues (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(150) NOT NULL,
                    created_at DATETIME NOT NULL
                );

                CREATE TABLE IF NOT EXISTS floors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    venue_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS zones (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    floor_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS spots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    zone_id INT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    description VARCHAR(500) NULL,
                    photo_near VARCHAR(100) NULL,
                    photo_wide VARCHAR(100) NULL,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS tag_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tag_id INT NOT NULL,
                    event_id INT NOT NULL,
                    user_id INT NULL,
                    action VARCHAR(30) NOT NULL,
                    note VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_tag_log_tag ON tag_log (tag_id, created_at);

                CREATE TABLE IF NOT EXISTS event_assignments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INT NOT NULL,
                    user_id INT NOT NULL,
                    floor_id INT NULL,
                    zone_id INT NULL,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_assign_event_user ON event_assignments (event_id, user_id);
            ');
        } else {
            $mySqlTables = [
                "CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    must_change_password TINYINT NOT NULL DEFAULT 0,
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
                    public_board TINYINT NOT NULL DEFAULT 0,
                    board_token VARCHAR(32) NULL,
                    lock_device_team TINYINT NOT NULL DEFAULT 0,
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
                    `rank` TINYINT NOT NULL,
                    points INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (color_id, `rank`),
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
                    `rank` TINYINT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY unique_tag_team (tag_id, team_id),
                    UNIQUE KEY unique_tag_rank (tag_id, `rank`),
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kind VARCHAR(20) NOT NULL,
                    key_hash CHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_rate_kind_key (kind, key_hash, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS device_locks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    device_hash CHAR(64) NOT NULL,
                    team_id INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY unique_event_device (event_id, device_hash),
                    INDEX idx_device_locks_team (team_id),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS venues (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS floors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    venue_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS zones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    floor_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS spots (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    zone_id INT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    description VARCHAR(500) NULL,
                    photo_near VARCHAR(100) NULL,
                    photo_wide VARCHAR(100) NULL,
                    created_by INT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS tag_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tag_id INT NOT NULL,
                    event_id INT NOT NULL,
                    user_id INT NULL,
                    action VARCHAR(30) NOT NULL,
                    note VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_tag_log_tag (tag_id, created_at),
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS event_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT NOT NULL,
                    user_id INT NOT NULL,
                    floor_id INT NULL,
                    zone_id INT NULL,
                    INDEX idx_assign_event_user (event_id, user_id),
                    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ];

            foreach ($mySqlTables as $tableSql) {
                $pdo->exec($tableSql);
            }
        }

        // مهاجرت خودکار: افزودن ستون‌های جدید به پایگاه‌داده‌های ساخته‌شده با نسخه‌های قبلی
        $migrations = [
            'events' => [
                'logo_path' => 'VARCHAR(255) NULL',
                'logo_tint_mode' => 'TINYINT NOT NULL DEFAULT 1',
                'public_board' => 'TINYINT NOT NULL DEFAULT 0',
                'board_token' => 'VARCHAR(32) NULL',
                'lock_device_team' => 'TINYINT NOT NULL DEFAULT 0',
                'venue_id' => 'INT NULL',
                'brand_1' => 'VARCHAR(7) NULL',
                'brand_2' => 'VARCHAR(7) NULL',
                'brand_3' => 'VARCHAR(7) NULL',
            ],
            'floors' => [
                'plan_image' => 'VARCHAR(100) NULL',
                'sketch_json' => 'TEXT NULL',
            ],
            'spots' => [
                'plan_x' => 'DECIMAL(6,3) NULL',
                'plan_y' => 'DECIMAL(6,3) NULL',
            ],
            'tags' => [
                'spot_id' => 'INT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'new'",
                'placed_at' => 'DATETIME NULL',
                'placed_by' => 'INT NULL',
                'verified_at' => 'DATETIME NULL',
                'verified_by' => 'INT NULL',
                'collected_at' => 'DATETIME NULL',
                'collected_by' => 'INT NULL',
            ],
            'admins' => [
                'must_change_password' => 'TINYINT NOT NULL DEFAULT 0',
                'role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'",
                'display_name' => 'VARCHAR(100) NULL',
                'is_active' => 'TINYINT NOT NULL DEFAULT 1',
            ],
        ];
        foreach ($migrations as $table => $columns) {
            $existingColumns = [];
            if ($driver === 'sqlite') {
                foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll() as $col) {
                    $existingColumns[] = strtolower($col['name']);
                }
            } else {
                foreach ($pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll() as $col) {
                    $existingColumns[] = strtolower($col['Field']);
                }
            }
            foreach ($columns as $colName => $colDef) {
                if (!in_array($colName, $existingColumns, true)) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$colName} {$colDef}");
                }
            }
        }

        // ۱. بررسی و ایجاد مدیر اولیه خودکار (در صورت عدم وجود)
        $admCheck = $pdo->query('SELECT COUNT(*) as cnt FROM admins');
        $adminCount = (int)$admCheck->fetch()['cnt'];

        if ($adminCount === 0) {
            $defaultUser = 'admin';
            $defaultPass = 'admin1234';
            $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
            // رمز پیش‌فرض فقط برای اولین ورود است و پس از ورود، تغییر آن اجباری است
            $ins = $pdo->prepare('INSERT INTO admins (username, password_hash, must_change_password, created_at) VALUES (:u, :p, 1, :c)');
            $ins->execute([
                'u' => $defaultUser,
                'p' => $hash,
                'c' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        // ۲. ایجاد یک رویداد نمونه کامل (در صورت خالی بودن رویدادها)
        $evCheck = $pdo->query('SELECT COUNT(*) as cnt FROM events');
        $eventCount = (int)$evCheck->fetch()['cnt'];

        if ($eventCount === 0) {
            $nowUtc = gmdate('Y-m-d H:i:s');
            $insEv = $pdo->prepare('
                INSERT INTO events (name, positions_count, show_result_to_scanner, is_active, created_at)
                VALUES (:name, :pos, :show_res, :active, :created)
            ');
            $insEv->execute([
                'name' => 'مسابقه رویداد NFC (پیش‌فرض)',
                'pos' => 4,
                'show_res' => 1,
                'active' => 1,
                'created' => $nowUtc,
            ]);
            $eventId = (int)$pdo->lastInsertId();

            // افزودن رنگ‌های نمونه با امتیازات
            $colorsData = [
                ['name' => 'طلایی', 'hex' => '#f59e0b', 'points' => [1 => 100, 2 => 70, 3 => 50, 4 => 30]],
                ['name' => 'نقره‌ای', 'hex' => '#94a3b8', 'points' => [1 => 80, 2 => 50, 3 => 30, 4 => 20]],
                ['name' => 'قرمز', 'hex' => '#ef4444', 'points' => [1 => 60, 2 => 40, 3 => 20, 4 => 10]],
                ['name' => 'آبی', 'hex' => '#3b82f6', 'points' => [1 => 50, 2 => 30, 3 => 15, 4 => 5]],
            ];

            $insCol = $pdo->prepare('INSERT INTO colors (event_id, name, hex, sort_order) VALUES (:eid, :name, :hex, :order)');
            $insPt = $pdo->prepare('INSERT INTO color_points (color_id, `rank`, points) VALUES (:cid, :rank, :pts)');

            $firstColorId = null;
            $order = 1;
            foreach ($colorsData as $c) {
                $insCol->execute([
                    'eid' => $eventId,
                    'name' => $c['name'],
                    'hex' => $c['hex'],
                    'order' => $order++,
                ]);
                $cid = (int)$pdo->lastInsertId();
                if ($firstColorId === null) {
                    $firstColorId = $cid;
                }
                foreach ($c['points'] as $rnk => $pts) {
                    $insPt->execute([
                        'cid' => $cid,
                        'rank' => $rnk,
                        'pts' => $pts,
                    ]);
                }
            }

            // افزودن ۴ تیم اولیه
            $teamsList = ['تیم شاهین', 'تیم عقاب', 'تیم سیمرغ', 'تیم دماوند'];
            $insTm = $pdo->prepare('INSERT INTO teams (event_id, name) VALUES (:eid, :name)');
            foreach ($teamsList as $tName) {
                $insTm->execute(['eid' => $eventId, 'name' => $tName]);
            }

            // افزودن ۳ تگ آماده با کدهای تصادفی ۲۲ کاراکتری
            $insTag = $pdo->prepare('INSERT INTO tags (event_id, name, color_id, code, created_at) VALUES (:eid, :name, :cid, :code, :created)');
            for ($i = 1; $i <= 3; $i++) {
                $code = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
                $insTag->execute([
                    'eid' => $eventId,
                    'name' => 'تگ شماره ' . str_replace(['1', '2', '3'], ['۱', '۲', '۳'], (string)$i),
                    'cid' => $firstColorId,
                    'code' => $code,
                    'created' => $nowUtc,
                ]);
            }
        }
    } catch (Exception $e) {
        // خطای ساخت جداول نباید بی‌صدا نادیده گرفته شود؛ ثبت در لاگ و توقف با پیام قابل فهم
        error_log('Database auto-initialization error: ' . $e->getMessage());
        throw new RuntimeException('خطا در آماده‌سازی خودکار جداول پایگاه‌داده. جزئیات در لاگ خطای سرور ثبت شده است.', 0, $e);
    }
}
