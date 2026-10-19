<?php
declare(strict_types=1);

/**
 * Lớp kết nối CSDL sử dụng PDO (Singleton Pattern)
 * Hỗ trợ linh hoạt cả PostgreSQL (Supabase) và MySQL (Local/XAMPP)
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $databaseUrl = env('DATABASE_URL');
            
            if (!empty($databaseUrl)) {
                // Tự động phân tích chuỗi URI: postgresql://user:pass@host:port/dbname
                $parsed = parse_url($databaseUrl);
                $driver = 'pgsql';
                $host = $parsed['host'] ?? 'localhost';
                $port = (string)($parsed['port'] ?? 5432);
                $user = isset($parsed['user']) ? urldecode($parsed['user']) : 'postgres';
                $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
                $db   = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'postgres';
                
                $sslmode = env('DB_SSLMODE', 'require');
                $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
            } else {
                $driver = env('DB_CONNECTION', 'pgsql');
                $host = env('DB_HOST', 'localhost');
                $port = (string)env('DB_PORT', ($driver === 'pgsql' ? '5432' : '3306'));
                $db   = env('DB_NAME', ($driver === 'pgsql' ? 'postgres' : 'checkin_local'));
                $user = env('DB_USER', ($driver === 'pgsql' ? 'postgres' : 'root'));
                $pass = env('DB_PASS', '');

                if ($driver === 'pgsql') {
                    $sslmode = env('DB_SSLMODE', 'require');
                    $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
                } else {
                    $charset = 'utf8mb4';
                    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                }
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
            ];


            // Chỉ đặt charset init command nếu driver là MySQL
            if ($driver === 'mysql' && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (env('APP_ENV', 'production') === 'local' || env('APP_DEBUG', false) === true) {
                    throw new PDOException("Lỗi kết nối CSDL ({$driver}): " . $e->getMessage(), (int)$e->getCode());
                } else {
                    error_log("Database connection failed: " . $e->getMessage());
                    die("Lỗi kết nối cơ sở dữ liệu. Vui lòng kiểm tra lại cấu hình kết nối.");
                }
            }
        }

        return self::$instance;
    }
}

