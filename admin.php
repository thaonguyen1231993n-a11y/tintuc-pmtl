<?php
// --- CẤU HÌNH LƯU PHIÊN ĐĂNG NHẬP 30 NGÀY ---
$lifetime = 2592000; // 30 ngày (tính bằng giây)

// 1. Bắt buộc Server giữ file session lâu tương ứng (Quan trọng)
ini_set('session.gc_maxlifetime', $lifetime);

// --- KIỂM TRA ĐƯỜNG TRUYỀN (HTTP LAN hay HTTPS Internet) ---
$is_secure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $is_secure = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $is_secure = true; // Nhận diện HTTPS từ Cloudflare Tunnel
}

// 2. Cấu hình Cookie trình duyệt
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '', 
    'secure' => $is_secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// --- XỬ LÝ UPLOAD ẢNH LOCAL ĐÃ TỐI ƯU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ajax_image']) && !isset($_POST['save_post']) && $_FILES['ajax_image']['error'] === UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    
    // Bảo mật: Chỉ người đã đăng nhập mới được upload
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        echo json_encode(['success' => false, 'error' => 'Từ chối truy cập']);
        exit;
    }

    $target_dir = __DIR__ . "/uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["ajax_image"]["name"], PATHINFO_EXTENSION));
    // Tạo tên file ngẫu nhiên siêu an toàn
    $new_file_name = uniqid() . '_' . bin2hex(random_bytes(2)) . '.' . $file_extension;
    $target_file = $target_dir . $new_file_name;

    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES["ajax_image"]["tmp_name"]);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime_type, $allowed_mimes)) {
        echo json_encode(['success' => false, 'error' => 'File không hợp lệ hoặc chứa mã độc!']);
        exit;
    }
    if (in_array($file_extension, $allowed_types) && move_uploaded_file($_FILES["ajax_image"]["tmp_name"], $target_file)) {
        // Trả về đúng link local /uploads/...
        echo json_encode(['success' => true, 'url' => '/uploads/' . $new_file_name]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Lỗi lưu file, vui lòng kiểm tra quyền thư mục']);
    }
    exit;
}

// --- XỬ LÝ DỌN DẸP ẢNH RÁC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup_images') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        echo json_encode(['success' => false, 'error' => 'Từ chối truy cập']);
        exit;
    }

    require_once 'db.php';
    try {
        $pdo = getDB();
        $upload_dir = __DIR__ . '/uploads/';
        if (!file_exists($upload_dir)) {
            echo json_encode(['success' => true, 'message' => 'Thư mục rỗng!']);
            exit;
        }

        $files = array_diff(scandir($upload_dir), ['.', '..']);
        $deleted_count = 0;
        $freed_space = 0;

        // Chuẩn bị sẵn câu lệnh SQL (Chỉ quét, không lôi data vào RAM)
        $stmtCheck = $pdo->prepare("SELECT 1 FROM posts WHERE content LIKE :filename LIMIT 1");

        foreach ($files as $file) {
            $file_path = $upload_dir . $file;
            if (is_file($file_path)) {
                // Kiểm tra xem tên file có nằm trong bài viết nào không
                $stmtCheck->execute([':filename' => '%' . $file . '%']);
                if (!$stmtCheck->fetch()) { 
                    // Không có bài nào chứa tên file này -> Xóa
                    $freed_space += filesize($file_path);
                    unlink($file_path);
                    $deleted_count++;
                }
            }
        }

        $freed_mb = round($freed_space / 1048576, 2);
        echo json_encode(['success' => true, 'message' => "Đã dọn $deleted_count ảnh rác, giải phóng $freed_mb MB!"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. Gia hạn Cookie mỗi khi người dùng vào lại trang
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    setcookie(session_name(), session_id(), time() + $lifetime, "/", "", $is_secure, true);
}

// --- CẤU HÌNH MÚI GIỜ & DB ---
date_default_timezone_set('Asia/Ho_Chi_Minh'); 
require_once 'db.php';

$message = "";
try {
    $pdo = getDB();
} catch (Exception $e) { die("Lỗi DB: " . $e->getMessage()); }

function checkLogin($input_user, $input_pass) {
    $env_accounts = getenv('ADMIN_ACCOUNTS'); 
    if (empty($env_accounts)) return ($input_user === 'admin' && $input_pass === '123456');
    $accounts = explode(',', $env_accounts);
    foreach ($accounts as $account) {
        $parts = explode(':', trim($account));
        if (count($parts) === 2 && $input_user === trim($parts[0]) && $input_pass === trim($parts[1])) return true;
    }
    return false;
}

if (isset($_POST['login'])) {
    if (checkLogin(trim($_POST['username']), $_POST['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = trim($_POST['username']);
        header("Location: admin.php"); exit;
    } else { $message = "Sai thông tin đăng nhập!"; }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// --- XỬ LÝ KHÔI PHỤC DATABASE ---
if (isset($_POST['restore_db']) && isset($_SESSION['loggedin']) && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'sql') {
            try {
                $sql_content = file_get_contents($_FILES['backup_file']['tmp_name']);
                $pdo->exec($sql_content); // Chạy trực tiếp file SQL
                $message = "🎉 Khôi phục dữ liệu thành công!";
            } catch (Exception $e) {
                $message = "❌ Lỗi khôi phục: " . $e->getMessage();
            }
        } else { $message = "⚠️ Chỉ chấp nhận file .sql!"; }
    }
}

// --- XỬ LÝ TẢI BACKUP THỦ CÔNG ---
if (isset($_GET['download_backup']) && isset($_SESSION['loggedin'])) {
    $date = date("Y-m-d_H-i");
    $filename = "tintuc_backup_$date.sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "-- Backup Database tintuc.pmtl.site\n";
    echo "-- Date: $date\n\n";
    
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $title = $pdo->quote($row['title']);
        $content = $pdo->quote($row['content']);
        $created_at = $pdo->quote($row['created_at']);
        echo "INSERT INTO posts (title, content, created_at) VALUES ($title, $content, $created_at);\n";
    }
    exit;
}

// --- XỬ LÝ LƯU/XÓA BÀI VIẾT ---
if (isset($_SESSION['loggedin'])) {
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute([':id' => $_GET['delete']]);
        header("Location: admin.php"); exit;
    }

    if (isset($_POST['save_post'])) {
        $title = $_POST['title'];
        $content = $_POST['content']; 
        $edit_id = $_POST['edit_id'];

        if ($edit_id !== "") {
            $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
            if ($stmt->execute([':title' => $title, ':content' => $content, ':id' => $edit_id])) {
                $message = "Đã cập nhật bài viết!";
                $_GET['edit'] = null; 
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
            if ($stmt->execute([':title' => $title, ':content' => $content])) {
                $message = "Đăng bài thành công!";
            }
        }
    }
}

$editing_post = null; $edit_mode = false; $all_posts = [];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Biên Tập Bài Viết</title>
    <link rel="icon" href="logo.png" type="image/png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <style>
        html, body { height: 100%; overflow: hidden; font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        #app-layout { display: flex; flex-direction: column; height: 100%; }

        .editor-container-wrap {
            flex-grow: 1; display: flex; flex-direction: column;
            background: white; border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden; margin-bottom: 60px; 
        }
        @media (min-width: 768px) { .editor-container-wrap { margin-bottom: 10px; } }

        .ql-toolbar { 
            background: #f9fafb; border-top: none !important; border-left: none !important; border-right: none !important;
            border-bottom: 1px solid #e5e7eb !important; display: flex; flex-wrap: wrap; align-items: center; padding: 8px !important;
        }

        #editor-wrapper { flex-grow: 1; overflow-y: auto; position: relative; }
        .ql-container { border: none !important; font-size: 16px; height: 100%; }
        /* Style cho nội dung bên trong Editor */
        .ql-editor img { max-width: 100%; height: auto; border-radius: 4px; display: block; margin: 10px auto; }
        .ql-editor iframe { max-width: 100%; margin: 10px auto; display: block; }
        .ql-editor a { color: #2563eb; text-decoration: underline; }

        .custom-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 4px; color: #4b5563; transition: all 0.2s; cursor: pointer; }
        .custom-icon-btn:hover { background-color: #e5e7eb; color: #000; }
        #mobile-nav-bar { padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="text-gray-800">

    <?php if(!empty($message)): ?>
    <div id="toast" class="fixed top-16 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg z-[100]">
        <?php echo $message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('toast').remove(), 5000);</script>
    <?php endif; ?>

    <?php if (!isset($_SESSION['loggedin'])): ?>
    <div class="min-h-screen flex items-center justify-center p-4 w-full overflow-y-auto">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
            <div class="flex justify-center mb-4">
                <img src="logo.png" alt="Logo" class="h-20 w-auto object-contain">
            </div>
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Đăng Nhập</h2>
            <form method="post" class="space-y-4">
                <input type="text" name="username" required placeholder="Username" class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                <input type="password" name="password" required placeholder="Password" class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                <button type="submit" name="login" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700">Vào Quản Trị</button>
            </form>
            <a href="index.php" class="block w-full text-center mt-4 bg-gray-100 text-gray-700 py-2 rounded-lg font-bold hover:bg-gray-200 transition duration-150">
                🏠 Quay về Trang Chủ
            </a>
        </div>
    </div>

    <?php else: ?>
    
    <div id="app-layout">
        <header class="bg-white border-b shadow-sm z-40 flex-shrink-0">
            <div class="max-w-6xl mx-auto px-4 py-2 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="admin.php" class="text-lg font-bold text-gray-800">Admin</a>
                    <div class="hidden md:flex gap-2">
                        <a href="admin.php?download_backup=true" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 font-bold shadow-sm inline-block">Tải Backup</a>
                        <button type="button" id="btn-trigger-restore" class="text-xs bg-orange-500 text-white px-3 py-1.5 rounded hover:bg-orange-600 font-bold shadow-sm">Khôi Phục</button>
                        <button type="button" class="btn-cleanup text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 font-bold shadow-sm">Dọn Rác</button>
                        <button id="btn-open-list-pc" class="text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200 border">QL Bài Đăng</button>
                        <button type="button" id="btn-header-save" class="text-xs bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 font-bold shadow-sm">
                            <?php echo $edit_mode ? '💾 Cập Nhật' : '🚀 Đăng Bài'; ?>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="index.php" target="_blank" class="text-xs text-gray-500 hover:underline hidden md:inline">Xem Web</a>
                    <a href="?logout=true" class="text-xs text-red-600 font-medium hover:underline">Thoát</a>
                </div>
            </div>
        </header>

        <form method="post" enctype="multipart/form-data" id="restoreForm" class="hidden">
            <input type="file" name="backup_file" id="hidden-restore-input" accept=".sql">
            <input type="hidden" name="restore_db" value="1">
        </form>

        <div class="flex-grow flex flex-col max-w-4xl mx-auto w-full p-2 md:p-4 overflow-hidden relative">
            <form method="post" enctype="multipart/form-data" id="postForm" class="flex flex-col h-full">
                <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
                <div class="flex items-center gap-2 mb-2">
                    <input type="text" name="title" required placeholder="Tiêu đề bài viết..." 
                           value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>"
                           onkeydown="if(event.key === 'Enter') { event.preventDefault(); return false; }"
                           class="flex-grow w-full text-xl md:text-2xl font-bold border-none focus:ring-0 p-2 bg-transparent placeholder-gray-400 outline-none">
                           
                    <?php if ($edit_mode): ?>
                        <a href="admin.php" class="flex-shrink-0 text-sm bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-300 font-bold border shadow-sm transition-colors">
                            ❌ Hủy sửa
                        </a>
                    <?php endif; ?>
                </div>
                <input type="file" name="ajax_image" id="hidden-image-input" accept="image/*" class="hidden">
                <div class="editor-container-wrap">
                    <div id="toolbar-container">
                        <span class="ql-formats">
                            <button class="ql-bold"></button> <button class="ql-italic"></button> <button class="ql-underline"></button>
                            <select class="ql-header"><option value="1"></option><option value="2"></option><option selected></option></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered"></button> <button class="ql-list" value="bullet"></button>
                        </span>
                        
                        <span class="ql-formats border-l pl-2 ml-2 flex items-center gap-1">
                            <button type="button" id="btn-trigger-image-pc" class="custom-icon-btn" title="Chèn Ảnh">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                            </button>
                            <button type="button" id="btn-custom-link" class="custom-icon-btn" title="Chèn Liên Kết">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                            </button>
                            <button type="button" id="btn-insert-video" class="custom-icon-btn" title="Chèn Video">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" /></svg>
                            </button>
                            <button type="button" id="btn-paste" class="custom-icon-btn" title="Dán (Plain Text)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                            </button>
                            <button type="button" id="btn-clean-text" class="custom-icon-btn" title="Làm Sạch">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>
                            </button>
                        </span>
                    </div>

                    <div id="editor-wrapper">
                        <div id="editor"><?php echo $edit_mode ? $editing_post['content'] : ''; ?></div>
                    </div>
                </div>

                <input type="hidden" name="content" id="hiddenContent">
                <button type="submit" name="save_post" id="btn-real-submit" class="hidden"></button>
            </form>
        </div>

        <div id="mobile-nav-bar" class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 flex items-center justify-around py-2 z-50 shadow-[0_-2px_10px_rgba(0,0,0,0.1)]">
            <button id="btn-open-list-mobile" class="flex flex-col items-center text-gray-600 hover:text-blue-600 w-1/4">
                <span class="text-xl">📂</span><span class="text-[10px] font-medium mt-1">Danh Sách</span>
            </button>
            
            <button id="btn-trigger-image-mobile" class="flex flex-col items-center text-gray-600 hover:text-blue-600 w-1/4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                <span class="text-[10px] font-medium mt-1">Thêm Ảnh</span>
            </button>

            <button id="btn-insert-video-mobile" class="flex flex-col items-center text-gray-600 hover:text-blue-600 w-1/4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" /></svg>
                <span class="text-[10px] font-medium mt-1">Thêm Video</span>
            </button>

            <button id="btn-mobile-save" class="flex flex-col items-center text-blue-600 w-1/4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                <span class="text-[10px] font-bold mt-1"><?php echo $edit_mode ? 'Cập Nhật' : 'Đăng Bài'; ?></span>
            </button>
        </div>
    </div>

    <div id="modal-post-list" class="hidden fixed inset-0 z-[60] bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-lg shadow-xl flex flex-col max-h-[80vh]">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold">Danh Sách Bài Viết</h3>
                <button class="modal-close text-2xl">&times;</button>
            </div>
            <div class="flex-grow overflow-y-auto p-2">
                <div class="p-2 mb-2 border-b md:hidden text-center flex flex-col gap-2">
                    <a href="admin.php?download_backup=true" class="w-full bg-green-100 text-green-700 py-2 rounded font-bold border border-green-300 block">Tải Backup (.sql)</a>
                    <button type="button" id="btn-trigger-restore-mobile" class="w-full bg-orange-100 text-orange-700 py-2 rounded font-bold border border-orange-300">Khôi Phục Database (.sql)</button>
                    <button type="button" class="btn-cleanup w-full bg-red-100 text-red-700 py-2 rounded font-bold border border-red-300 mt-2">Dọn Dẹp Rác</button>
                </div>

                <?php if (empty($all_posts)): ?> <p class="text-center mt-4">Chưa có bài nào.</p> <?php else: ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($all_posts as $post): ?>
                        <li class="p-3 hover:bg-gray-50 rounded">
                            <div class="font-bold mb-1"><?php echo htmlspecialchars($post['title']); ?></div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400"><?php echo date("d/m H:i", strtotime($post['created_at'])); ?></span>
                                <div class="flex gap-3">
                                    <a href="post-<?php echo $post['id']; ?>.html" target="_blank" class="text-emerald-600 font-bold">Xem</a>
                                    
                                    <a href="admin.php?edit=<?php echo $post['id']; ?>" class="text-blue-600 font-bold">Sửa</a>
                                    <a href="admin.php?delete=<?php echo $post['id']; ?>" onclick="return confirm('Xóa?')" class="text-red-600 font-bold">Xóa</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal-video-embed" class="hidden fixed inset-0 z-[60] bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-lg shadow-xl p-6">
            <h3 class="font-bold text-lg mb-3">Dán mã nhúng (iframe)</h3>
            <textarea id="embed-code-input" rows="4" class="w-full p-2 border rounded bg-gray-50 text-xs font-mono mb-4" placeholder='<iframe src="..."></iframe>'></textarea>
            <div class="flex justify-end gap-2">
                <button class="video-modal-close px-3 py-1 bg-gray-200 rounded">Hủy</button>
                <button id="btn-confirm-embed" class="px-3 py-1 bg-blue-600 text-white rounded font-bold">Chèn</button>
            </div>
        </div>
    </div>

    <script>
        var quill = new Quill('#editor', {
            theme: 'snow', modules: { toolbar: '#toolbar-container' }, placeholder: 'Nội dung bài viết...'
        });

        const restoreInput = document.getElementById('hidden-restore-input');
        const triggerRestore = function() { restoreInput.click(); };
        const btnRestorePc = document.getElementById('btn-trigger-restore');
        const btnRestoreMobile = document.getElementById('btn-trigger-restore-mobile');
        if(btnRestorePc) btnRestorePc.onclick = triggerRestore;
        if(btnRestoreMobile) btnRestoreMobile.onclick = triggerRestore;

        restoreInput.onchange = function() {
            if(this.files && this.files[0]) {
                const fileName = this.files[0].name;
                if(confirm('⚠️ CẢNH BÁO: Bạn có chắc chắn muốn nạp file SQL: ' + fileName + ' ?')) {
                    document.getElementById('restoreForm').submit();
                } else {
                    this.value = ''; 
                }
            }
        };

        quill.root.addEventListener('paste', function(e) {
            e.preventDefault(); 
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            if (text) {
                var range = quill.getSelection(true); 
                var index = (range) ? range.index : quill.getLength();
                if (range && range.length > 0) quill.deleteText(range.index, range.length);
                quill.insertText(index, text, 'user');
                quill.setSelection(index + text.length);
                quill.scrollIntoView(); 
            }
        });

        document.getElementById('btn-custom-link').onclick = function() {
            var range = quill.getSelection(true);
            if (!range) return; 
            var url = prompt("Nhập đường dẫn (URL):", "https://");
            if (url) {
                if (range.length > 0) {
                    quill.format('link', url);
                } else {
                    var text = prompt("Nhập tên hiển thị:", "Bấm vào đây");
                    if (text) {
                        quill.insertText(range.index, text, 'link', url);
                        quill.setSelection(range.index + text.length);
                    }
                }
            }
        };

        // --- XỬ LÝ ẢNH (AJAX NÉN & ĐẨY LÊN LOCAL) ---
        const hiddenInput = document.getElementById('hidden-image-input');
        document.getElementById('btn-trigger-image-mobile').onclick = () => hiddenInput.click();
        const btnPc = document.getElementById('btn-trigger-image-pc');
        if(btnPc) btnPc.onclick = () => hiddenInput.click();
        
        hiddenInput.onchange = function(e) {
            if(this.files && this.files[0]) {
                const file = this.files[0];
                const max_width = 1200; 
                
                const range = quill.getSelection(true);
                const index = range ? range.index : quill.getLength();
                const loadingText = '⏳ Đang nén & tải ảnh...'; 
                quill.insertText(index, loadingText, 'bold', true);

                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function(event) {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;

                        if (width > max_width) {
                            height = Math.round((height * max_width) / width);
                            width = max_width;
                        }
                        canvas.width = width; canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        canvas.toBlob(async function(blob) {
                            const compressedFile = new File([blob], "image.jpg", { type: 'image/jpeg' });
                            const formData = new FormData();
                            formData.append('ajax_image', compressedFile);
                            
                            try {
                                const response = await fetch('admin.php', { 
                                    method: 'POST', 
                                    body: formData,
                                    credentials: 'same-origin' // <--- Bổ sung dòng này để ép trình duyệt gửi kèm phiên đăng nhập
                                });
                                const data = await response.json();
                                
                                quill.deleteText(index, loadingText.length); 
                                
                                // ĐÃ SỬA: Dùng data.url thay vì data.success để chèn đúng link ảnh
                                if (data.success && data.url) {
                                    quill.insertEmbed(index, 'image', data.url);
                                    quill.setSelection(index + 1); 
                                } else { 
                                    alert('Lỗi: ' + data.error); 
                                }
                            } catch (err) { 
                                quill.deleteText(index, loadingText.length);
                                alert('Lỗi kết nối máy chủ'); 
                            } finally { 
                                hiddenInput.value = ''; 
                            }
                        }, 'image/jpeg', 0.75);
                    };
                };
            }
        };

        const videoModal = document.getElementById('modal-video-embed');
        const embedInput = document.getElementById('embed-code-input');
        function toggleVideoModal() { 
            videoModal.classList.toggle('hidden'); 
            if(!videoModal.classList.contains('hidden')) embedInput.focus();
        }
        document.getElementById('btn-insert-video').onclick = toggleVideoModal;
        document.getElementById('btn-insert-video-mobile').onclick = toggleVideoModal; 
        document.querySelectorAll('.video-modal-close').forEach(b => b.onclick = toggleVideoModal);

        document.getElementById('btn-confirm-embed').onclick = function() {
            const code = embedInput.value.trim();
            if(code.includes('<iframe')) {
                const range = quill.getSelection(true);
                const index = range ? range.index : quill.getLength();
                quill.clipboard.dangerouslyPasteHTML(index, code);
                toggleVideoModal(); embedInput.value = '';
            } else { alert("Vui lòng dán đúng mã <iframe>!"); }
        };

        function submitPost() {
            var content = document.querySelector('input[name=content]');
            content.value = quill.root.innerHTML;
            if(content.value.trim() === '<p><br></p>' || content.value.trim() === '') { alert('Nội dung trống!'); return; }
            document.getElementById('btn-real-submit').click();
        }
        document.getElementById('btn-header-save').onclick = submitPost;
        document.getElementById('btn-mobile-save').onclick = submitPost;

        const listModal = document.getElementById('modal-post-list');
        function toggleList() { listModal.classList.toggle('hidden'); }
        document.getElementById('btn-open-list-pc').onclick = toggleList;
        document.getElementById('btn-open-list-mobile').onclick = toggleList;
        document.querySelectorAll('.modal-close').forEach(b => b.onclick = toggleList);

        document.getElementById('btn-paste').onclick = async () => {
            try {
                const text = await navigator.clipboard.readText();
                if (text) {
                    const range = quill.getSelection(true);
                    quill.insertText(range ? range.index : 0, text);
                }
            } catch (err) {}
        };
        
        document.getElementById('btn-clean-text').onclick = () => {
            if(confirm('Làm sạch văn bản?')) {
                let text = quill.getText();
                text = text.replace(/([\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD10-\uDDFF])/g, '');
                quill.setText(text);
            }
        };

        // --- XỬ LÝ NÚT DỌN RÁC ---
        document.querySelectorAll('.btn-cleanup').forEach(btn => {
            btn.onclick = async () => {
                if (!confirm('⚠️ Bạn có chắc chắn muốn quét và tiêu hủy toàn bộ ảnh không nằm trong bất kỳ bài viết nào? (Hành động này không thể hoàn tác)')) return;
                
                const originalText = btn.innerHTML;
                btn.innerHTML = '⏳ Đang quét...';
                try {
                    const formData = new FormData();
                    formData.append('action', 'cleanup_images');
                    const res = await fetch('admin.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                    const data = await res.json();
                    
                    if (data.success) {
                        alert('✅ ' + data.message);
                    } else {
                        alert('❌ Lỗi: ' + data.error);
                    }
                } catch (err) {
                    alert('❌ Lỗi kết nối máy chủ!');
                } finally {
                    btn.innerHTML = originalText;
                }
            };
        });
    </script>
    <?php endif; ?>
</body>
</html>
