<?php
session_start();
require_once 'db.php';

$message = "";

// LẤY KẾT NỐI DB
try {
    $pdo = getDB();
} catch (Exception $e) {
    die("Lỗi kết nối Database: " . $e->getMessage());
}

// 1. HÀM KIỂM TRA ĐĂNG NHẬP (GIỮ NGUYÊN)
function checkLogin($input_user, $input_pass) {
    $env_accounts = getenv('ADMIN_ACCOUNTS'); 
    if (empty($env_accounts)) {
        return ($input_user === 'admin' && $input_pass === '123456');
    }
    $accounts = explode(',', $env_accounts);
    foreach ($accounts as $account) {
        $parts = explode(':', trim($account));
        if (count($parts) === 2) {
            if ($input_user === trim($parts[0]) && $input_pass === trim($parts[1])) {
                return true;
            }
        }
    }
    return false;
}

// 2. HÀM UPLOAD ẢNH LÊN SUPABASE (MỚI)
function uploadToSupabase($file) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    $bucketName = 'uploads'; // Tên bucket bạn đã tạo

    if (!$supabaseUrl || !$supabaseKey) {
        return ["error" => "Chưa cấu hình SUPABASE_URL hoặc KEY trên Render."];
    }

    // Tạo tên file độc nhất để không bị trùng
    $fileName = time() . '_' . basename($file['name']);
    // URL API upload của Supabase
    $apiUrl = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $fileName;

    // Đọc nội dung file
    $fileContent = file_get_contents($file['tmp_name']);

    // Dùng CURL để gửi file
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: ' . $file['type']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        // Upload thành công, trả về link ảnh công khai
        return ["success" => $supabaseUrl . '/storage/v1/object/public/' . $bucketName . '/' . $fileName];
    } else {
        return ["error" => "Lỗi upload ($httpCode): " . $response];
    }
}

// 3. XỬ LÝ LOGIN
if (isset($_POST['login'])) {
    if (checkLogin(trim($_POST['username']), $_POST['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = trim($_POST['username']);
        header("Location: admin.php");
        exit;
    } else {
        $message = "<span class='msg-error'>Sai tên đăng nhập hoặc mật khẩu!</span>";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// 4. XỬ LÝ LƯU BÀI VIẾT (CÓ XỬ LÝ ẢNH)
if (isset($_SESSION['loggedin']) && isset($_POST['save_post'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $edit_id = $_POST['edit_id'];

    // --- XỬ LÝ UPLOAD ẢNH ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadResult = uploadToSupabase($_FILES['image']);
        if (isset($uploadResult['success'])) {
            // Nếu upload thành công, chèn thẻ IMG vào đầu nội dung
            $imgTag = '<img src="' . $uploadResult['success'] . '" style="width:100%; border-radius:8px; margin-bottom:15px;">';
            $content = $imgTag . "\n" . $content;
        } else {
            $message = "<span class='msg-error'>" . $uploadResult['error'] . "</span>";
        }
    }
    // -------------------------

    if (empty($message)) { // Chỉ lưu nếu không có lỗi upload
        if ($edit_id !== "") {
            $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
            if ($stmt->execute([':title' => $title, ':content' => $content, ':id' => $edit_id])) {
                $message = "<span class='msg-success'>Đã cập nhật bài viết!</span>";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
            if ($stmt->execute([':title' => $title, ':content' => $content])) {
                $message = "<span class='msg-success'>Đăng bài mới thành công!</span>";
            }
        }
    }
}

// XỬ LÝ XÓA
if (isset($_SESSION['loggedin']) && isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header("Location: admin.php"); exit;
}

// LẤY DỮ LIỆU HIỂN THỊ
$editing_post = null;
$edit_mode = false;
$all_posts = [];
if (isset($_SESSION['loggedin'])) {
    $all_posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id");
        $stmt->execute([':id' => $_GET['edit']]);
        $editing_post = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($editing_post) $edit_mode = true;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <h2 style="text-align:center">Đăng Nhập Admin</h2>
        <p style="text-align:center"><?php echo $message; ?></p>
        <form method="post" style="max-width:400px; margin:0 auto;">
            <div class="form-group"><label>Username:</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password:</label><input type="password" name="password" required></div>
            <button type="submit" name="login" style="width:100%">Đăng Nhập</button>
        </form>
    <?php else: ?>
        <header class="admin-header">
            <h2>Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <div>
                <a href="admin.php" class="btn-secondary">Viết bài</a>
                <a href="index.php" target="_blank" class="btn-secondary">Xem trang</a>
                <a href="?logout=true" class="btn-logout">[Thoát]</a>
            </div>
        </header>
        <p><?php echo $message; ?></p>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
            
            <div class="form-group">
                <label>Tiêu đề:</label>
                <input type="text" name="title" required value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>">
            </div>

            <div class="form-group" style="background: #fff; padding: 10px; border: 1px dashed #ccc;">
                <label>Ảnh minh họa (Tùy chọn):</label>
                <input type="file" name="image" accept="image/*">
                <small style="color:#666">Nếu chọn ảnh, ảnh sẽ được chèn lên đầu bài viết.</small>
            </div>
            
            <div class="form-group">
                <label>Nội dung:</label>
                <textarea name="content" rows="10" required><?php echo $edit_mode ? htmlspecialchars($editing_post['content']) : ''; ?></textarea>
            </div>
            
            <button type="submit" name="save_post"><?php echo $edit_mode ? "Lưu Thay Đổi" : "Đăng Bài Ngay"; ?></button>
        </form>
        <hr>
        <div class="admin-list">
            <?php if (!empty($all_posts)): ?>
                <table style="width:100%; border-collapse:collapse;">
                    <?php foreach ($all_posts as $post): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;"><b><?php echo htmlspecialchars($post['title']); ?></b></td>
                            <td style="padding:10px; text-align:right;">
                                <a href="admin.php?edit=<?php echo $post['id']; ?>" class="action-btn edit-btn">Sửa</a>
                                <a href="admin.php?delete=<?php echo $post['id']; ?>" class="action-btn del-btn" onclick="return confirm('Xóa?');">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
