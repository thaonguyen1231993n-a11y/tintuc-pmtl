<?php
session_start();
require_once 'db.php'; // Vẫn cần file này để kết nối lưu bài viết

$message = "";

// LẤY KẾT NỐI DB (CHỈ ĐỂ LƯU BÀI VIẾT)
try {
    $pdo = getDB();
} catch (Exception $e) {
    die("Lỗi kết nối Database bài viết: " . $e->getMessage());
}

// 1. XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// 2. HÀM KIỂM TRA ĐĂNG NHẬP TỪ BIẾN MÔI TRƯỜNG
function checkLogin($input_user, $input_pass) {
    // Lấy chuỗi cấu hình từ Render: "admin:123456,mod:abcxyz"
    $env_accounts = getenv('ADMIN_ACCOUNTS'); 
    
    // Nếu chưa cấu hình, mặc định user là admin / 123456
    if (empty($env_accounts)) {
        return ($input_user === 'admin' && $input_pass === '123456');
    }

    // Tách chuỗi thành mảng các tài khoản
    $accounts = explode(',', $env_accounts);
    
    foreach ($accounts as $account) {
        // Tách user và pass (dùng dấu :)
        $parts = explode(':', trim($account));
        if (count($parts) === 2) {
            $valid_user = trim($parts[0]);
            $valid_pass = trim($parts[1]);

            // So sánh (Ở đây so sánh trực tiếp, không mã hóa để bạn dễ nhập trên Render)
            if ($input_user === $valid_user && $input_pass === $valid_pass) {
                return true;
            }
        }
    }
    return false;
}

// 3. XỬ LÝ KHI NGƯỜI DÙNG BẤM ĐĂNG NHẬP
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Mật khẩu nhập vào

    if (checkLogin($username, $password)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $message = "<span class='msg-error'>Sai tên đăng nhập hoặc mật khẩu!</span>";
    }
}

// --- CÁC CHỨC NĂNG QUẢN TRỊ (CHỈ CHẠY KHI ĐÃ LOGIN) ---
if (isset($_SESSION['loggedin'])) {

    // XỬ LÝ GỬI BÀI (INSERT / UPDATE VÀO SUPABASE)
    if (isset($_POST['save_post'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $edit_id = $_POST['edit_id'];

        if ($edit_id !== "") {
            // SỬA BÀI
            $sql = "UPDATE posts SET title = :title, content = :content WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':title' => $title, ':content' => $content, ':id' => $edit_id])) {
                $message = "<span class='msg-success'>Đã cập nhật bài viết!</span>";
            }
        } else {
            // THÊM MỚI
            $sql = "INSERT INTO posts (title, content) VALUES (:title, :content)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':title' => $title, ':content' => $content])) {
                $message = "<span class='msg-success'>Đăng bài mới thành công!</span>";
            }
        }
    }

    // XỬ LÝ XÓA BÀI
    if (isset($_GET['delete'])) {
        $delete_id = $_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        header("Location: admin.php"); 
        exit;
    }
}

// CHUẨN BỊ DỮ LIỆU HIỂN THỊ
$editing_post = null;
$edit_mode = false;
$all_posts = [];

if (isset($_SESSION['loggedin'])) {
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
    $all_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['edit'])) {
        $edit_id = $_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);
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
    <title>Quản Trị - Pháp Môn Tâm Linh</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
</head>
<body>

<div class="container">
    
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <h2 style="text-align:center; color:#8B4513;">Đăng Nhập Admin</h2>
        <p style="text-align:center"><?php echo $message; ?></p>
        <form method="post" style="max-width:400px; margin:0 auto;">
            <div class="form-group">
                <label>Tên đăng nhập:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" style="width:100%">Đăng Nhập</button>
        </form>
        <div style="text-align:center; margin-top:20px;">
            <a href="index.php">← Về trang chủ</a>
        </div>

    <?php else: ?>
        <header class="admin-header">
            <div>
                <h2>Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            </div>
            <div>
                <a href="admin.php" class="btn-secondary">Viết bài</a>
                <a href="index.php" target="_blank" class="btn-secondary">Xem trang</a>
                <a href="?logout=true" class="btn-logout">[Thoát]</a>
            </div>
        </header>
        
        <p><?php echo $message; ?></p>
        
        <form method="post" action="admin.php">
            <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
            
            <div class="form-group">
                <label>Tiêu đề:</label>
                <input type="text" name="title" required 
                       value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Nội dung:</label>
                <textarea name="content" rows="10" required><?php echo $edit_mode ? htmlspecialchars($editing_post['content']) : ''; ?></textarea>
            </div>
            
            <button type="submit" name="save_post">
                <?php echo $edit_mode ? "Lưu Thay Đổi" : "Đăng Bài Ngay"; ?>
            </button>
            <?php if($edit_mode): ?>
                <a href="admin.php" style="margin-left:10px; color:#666;">Hủy bỏ</a>
            <?php endif; ?>
        </form>

        <hr style="margin: 40px 0; border: 0; border-top: 1px solid #E6D5B8;">

        <h3 style="color:#8B4513;">Danh sách bài đã đăng</h3>
        <div class="admin-list">
            <?php if (!empty($all_posts)): ?>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#FFF8E1; color:#5D4037;">
                            <th style="padding:10px; text-align:left;">ID</th>
                            <th style="padding:10px; text-align:left;">Tiêu đề</th>
                            <th style="padding:10px; text-align:left;">Ngày đăng</th>
                            <th style="padding:10px; text-align:right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_posts as $post): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px; width:50px; color:#999;"><?php echo $post['id']; ?></td>
                                <td style="padding:10px; font-weight:bold; color:#3E2723;">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </td>
                                <td style="padding:10px; font-size:13px; color:#A67B5B;">
                                    <?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?>
                                </td>
                                <td style="padding:10px; text-align:right;">
                                    <a href="admin.php?edit=<?php echo $post['id']; ?>" class="action-btn edit-btn">Sửa</a>
                                    <a href="admin.php?delete=<?php echo $post['id']; ?>" 
                                       class="action-btn del-btn"
                                       onclick="return confirm('Bạn có chắc chắn muốn xóa bài này không?');">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Chưa có bài viết nào.</p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

</body>
</html>