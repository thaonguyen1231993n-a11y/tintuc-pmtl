<?php
// db.php
function getDB() {
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT'); 

    // Kết nối Direct (5432) vẫn cần sslmode=require
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    
    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15, // Tăng thời gian chờ lên 15s
            PDO::ATTR_EMULATE_PREPARES => false, // [QUAN TRỌNG] Tắt giả lập để tương thích tốt hơn với Postgres
            PDO::ATTR_PERSISTENT => true // [QUAN TRỌNG] Giữ kết nối bền vững vì web chạy trên Docker
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Lỗi kết nối Database: " . $e->getMessage());
    }
}
?>
