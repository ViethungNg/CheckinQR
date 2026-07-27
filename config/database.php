<?php
declare(strict_types=1);

/**
 * Lớp kết nối CSDL sử dụng PDO (Singleton Pattern)
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = env('DB_HOST', 'localhost');
            $db   = env('DB_NAME', 'checkin_local');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');
            $port = env('DB_PORT', '3306');
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Ở production sẽ không hiện lỗi chi tiết ra ngoài
                if (env('APP_ENV', 'production') === 'local') {
                    throw new PDOException($e->getMessage(), (int)$e->getCode());
                } else {
                    error_log("Database connection failed: " . $e->getMessage());
                    die("Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.");
                }
            }
        }

        return self::$instance;
    }
}
