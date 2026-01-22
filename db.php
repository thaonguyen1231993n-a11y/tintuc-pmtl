<?php
// db.php
function getDB() {
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT'); 

    // Bắt buộc có sslmode=require
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
            // Tắt giả lập Prepare (Code chạy "thật" hơn, tránh lỗi timeout ảo)
            PDO::ATTR_EMULATE_PREPARES => false, 
            // Không dùng Persistent Connection ở chế độ này để tránh giữ slot quá lâu
            PDO::ATTR_PERSISTENT => false 
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Lỗi kết nối Database: " . $e->getMessage());
    }
}
?>

