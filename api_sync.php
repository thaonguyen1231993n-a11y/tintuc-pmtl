<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$SECRET_TOKEN = getenv('API_SECRET_TOKEN');

if (empty($SECRET_TOKEN)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Chưa cấu hình API_SECRET_TOKEN trên máy chủ!']);
    exit;
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');

if ($authHeader !== 'Bearer ' . $SECRET_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sai Token xác thực!']);
    exit;
}

// Đọc dữ liệu JSON thô gửi từ GAS
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu Tiêu đề.']);
    exit;
}

$title = trim($input['title']);
$text = isset($input['text']) ? trim($input['text']) : '';
$fb_url = isset($input['fb_url']) ? trim($input['fb_url']) : '';
$images = isset($input['images']) ? $input['images'] : [];
$is_video = isset($input['is_video']) ? (bool)$input['is_video'] : false;

try {
    $pdo = getDB();
    
    // Kiểm tra trùng lặp bài viết bằng Tiêu đề
    $checkStmt = $pdo->prepare("SELECT id FROM posts WHERE title = :title LIMIT 1");
    $checkStmt->execute([':title' => $title]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Bài viết đã tồn tại (Bỏ qua).']);
        exit;
    }

    // --- BẮT ĐẦU DỰNG NỘI DUNG HTML ---
    $contentHtml = "";

    // 1. XỬ LÝ VIDEO: Giả lập 100% mã nguồn do trình soạn thảo Quill sinh ra
    if ($is_video && !empty($fb_url)) {
        $encoded_url = urlencode($fb_url);
        // Sử dụng class="ql-video" và width="560" height="314" để JS trên index.php tính đúng tỷ lệ
        $contentHtml .= '<iframe class="ql-video" frameborder="0" allowfullscreen="true" src="https://www.facebook.com/plugins/video.php?height=314&width=560&href=' . $encoded_url . '&show_text=false" height="314" width="560"></iframe>';
    } 
    // 2. XỬ LÝ ẢNH LOCAL
    elseif (!empty($images) && is_array($images)) {
        $target_dir = __DIR__ . "/uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        if (!function_exists('downloadFbImage')) {
            function downloadFbImage($url, $savePath) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $data = curl_exec($ch);
                curl_close($ch);
                if ($data) {
                    return file_put_contents($savePath, $data) !== false;
                }
                return false;
            }
        }

        foreach ($images as $imgObj) {
            $imgUrl = is_array($imgObj) ? (isset($imgObj['url']) ? $imgObj['url'] : '') : $imgObj;
            if (empty($imgUrl)) continue;

            $file_extension = strtolower(pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $file_extension = 'jpg'; 
            }

            $new_file_name = uniqid() . '_' . bin2hex(random_bytes(2)) . '.' . $file_extension;
            $local_save_path = $target_dir . $new_file_name;

            // Xuất thẻ img trơn y như cách Quill làm (không bọc div, không dùng style inline)
            if (downloadFbImage($imgUrl, $local_save_path)) {
                $contentHtml .= '<img src="/uploads/' . $new_file_name . '">';
            }
        }
    }

    // 3. XỬ LÝ TEXT: Tách dòng bằng thẻ <p>
    if (!empty($text)) {
        $paragraphs = explode("\n", str_replace("\r", "", $text));
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $contentHtml .= '<p>' . htmlspecialchars($p) . '</p>';
            } else {
                $contentHtml .= '<p><br></p>';
            }
        }
    }

    // Lưu bài viết vào Database
    $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
    $stmt->execute([':title' => $title, ':content' => $contentHtml]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
?>
