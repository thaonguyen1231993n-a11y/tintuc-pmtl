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
    // (Định dạng cấu trúc y hệt đoạn mã do trình soạn thảo Quill sinh ra bên admin.php)
    $contentHtml = "";

    // 1. XỬ LÝ VIDEO
    if ($is_video && !empty($fb_url)) {
        $encoded_url = urlencode($fb_url);
        // KHÔNG bọc thẻ div bên ngoài. Xuất thẳng thẻ iframe với max-width y như JS admin.php đang làm.
        $contentHtml .= '<iframe src="https://www.facebook.com/plugins/video.php?href=' . $encoded_url . '&show_text=false" style="width: 100%; max-width: 100%; border: none; overflow: hidden;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>';
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

            if (downloadFbImage($imgUrl, $local_save_path)) {
                // Bọc ảnh bằng thẻ <p> kèm margin/max-width y như cách trình soạn thảo Quill đang định dạng.
                $contentHtml .= '<p><img src="/uploads/' . $new_file_name . '" alt="Ảnh Tin Tức" style="max-width: 100%; height: auto; display: block; margin: 10px auto;"></p>';
            }
        }
    }

    // 3. XỬ LÝ TEXT
    if (!empty($text)) {
        // Tách dòng văn bản và bọc trong thẻ <p> thay vì xài thẻ <br> (Đây chính là chuẩn của Quill editor)
        $paragraphs = explode("\n", str_replace("\r", "", $text));
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $contentHtml .= '<p>' . htmlspecialchars($p) . '</p>';
            } else {
                // Giữ lại khoảng trống y như khi gõ Enter rỗng trong Editor
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
