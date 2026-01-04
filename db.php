<?php
// db.php
function getDB() {
    // Lấy thông tin từ biến môi trường trên Render
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT'); // Thường là 5432 hoặc 6543

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return $pdo;
    } catch (PDOException $e) {
        die("Lỗi kết nối Database: " . $e->getMessage());
    }
}

?>
