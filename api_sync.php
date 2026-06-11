<?php
// Tắt cảnh báo lỗi hiển thị ra ngoài để tránh làm hỏng JSON response
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// 1. LẤY MẬT KHẨU BẢO VỆ API TỪ BIẾN MÔI TRƯỜNG DOCKER
$SECRET_TOKEN = getenv('API_SECRET_TOKEN');

// Kiểm tra an toàn: Nếu quên cấu hình trên Docker thì báo lỗi ngay
if (empty($SECRET_TOKEN)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Chưa cấu hình API_SECRET_TOKEN trên máy chủ!']);
    exit;
}

require_once 'db.php';

// Chỉ chấp nhận method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 2. KIỂM TRA BẢO MẬT (Xác thực Token)
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');

if ($authHeader !== 'Bearer ' . $SECRET_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sai Token xác thực!']);
    exit;
}

// 3. ĐỌC DỮ LIỆU TỪ GOOGLE APPS SCRIPT
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['title']) || empty($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu Tiêu đề hoặc Nội dung.']);
    exit;
}

$title = trim($input['title']);
$content = trim($input['content']);

try {
    $pdo = getDB();
    
    // Kiểm tra xem bài này đã tồn tại trong DB chưa (Check bằng title)
    $checkStmt = $pdo->prepare("SELECT id FROM posts WHERE title = :title LIMIT 1");
    $checkStmt->execute([':title' => $title]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Bài viết đã tồn tại (Bỏ qua).']);
        exit;
    }

    // Nạp bài viết vào Database
    $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
    $stmt->execute([':title' => $title, ':content' => $content]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Lỗi DB: ' . $e->getMessage()]);
}
?>
