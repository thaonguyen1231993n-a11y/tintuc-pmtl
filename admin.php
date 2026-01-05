<?php
session_start();
require_once 'db.php';

$message = "";

// --- CẤU HÌNH DATABASE & LOGIN (GIỮ NGUYÊN CỦA BẠN) ---
try {
    $pdo = getDB();
} catch (Exception $e) {
    die("Lỗi kết nối Database: " . $e->getMessage());
}

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

// --- HÀM UPLOAD ẢNH SUPABASE (GIỮ NGUYÊN CỦA BẠN) ---
function uploadToSupabase($file) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    $bucketName = 'uploads';

    if (!$supabaseUrl || !$supabaseKey) {
        return ["error" => "Chưa cấu hình SUPABASE_URL hoặc KEY trên Render."];
    }
    $fileName = time() . '_' . basename($file['name']);
    $apiUrl = $supabaseUrl . '/storage/v1/object/' . $bucketName . '/' . $fileName;
    $fileContent = file_get_contents($file['tmp_name']);
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
        return ["success" => $supabaseUrl . '/storage/v1/object/public/' . $bucketName . '/' . $fileName];
    } else {
        return ["error" => "Lỗi upload ($httpCode): " . $response];
    }
}

// --- XỬ LÝ FORM LOGIN ---
if (isset($_POST['login'])) {
    if (checkLogin(trim($_POST['username']), $_POST['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = trim($_POST['username']);
        header("Location: admin.php");
        exit;
    } else {
        $message = "Sai tên đăng nhập hoặc mật khẩu!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- XỬ LÝ LƯU/XÓA BÀI VIẾT ---
if (isset($_SESSION['loggedin'])) {
    // Xóa bài
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute([':id' => $_GET['delete']]);
        header("Location: admin.php"); exit;
    }

    // Lưu bài
    if (isset($_POST['save_post'])) {
        $title = $_POST['title'];
        $content = $_POST['content']; // Nội dung này sẽ được JS lấy từ Quill
        $edit_id = $_POST['edit_id'];

        // Xử lý ảnh upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = uploadToSupabase($_FILES['image']);
            if (isset($uploadResult['success'])) {
                $imgTag = '<img src="' . $uploadResult['success'] . '" style="width:100%; border-radius:8px; margin-bottom:15px;">';
                $content = $imgTag . "\n" . $content;
            } else {
                $message = "Lỗi upload ảnh: " . $uploadResult['error'];
            }
        }

        if (empty($message)) {
            if ($edit_id !== "") {
                $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
                if ($stmt->execute([':title' => $title, ':content' => $content, ':id' => $edit_id])) {
                    $message = "Đã cập nhật bài viết!";
                    // Reset form sau khi lưu
                    $_GET['edit'] = null; 
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
                if ($stmt->execute([':title' => $title, ':content' => $content])) {
                    $message = "Đăng bài mới thành công!";
                }
            }
        }
    }
}

// --- LẤY DỮ LIỆU ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quản Lý Đăng Bài</title>
    <link rel="icon" href="logo.png" type="image/png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        
        /* Tùy chỉnh Quill để full height */
        #editor-wrapper {
            display: flex; flex-direction: column; 
            background: white; border-radius: 0 0 8px 8px; border: 1px solid #d1d5db; border-top: 0;
            min-height: 400px; /* Chiều cao tối thiểu */
        }
        .ql-container { flex-grow: 1; font-size: 16px; min-height: 300px; }
        .ql-toolbar { background: #f9fafb; border-radius: 8px 8px 0 0; border-color: #d1d5db !important; }
        .ql-editor { min-height: 300px; }
        
        /* Mobile adjustments */
        @media (max-width: 768px) {
            .container-custom { padding: 10px; }
            .ql-toolbar { padding: 5px; }
        }
    </style>
</head>
<body class="text-gray-800">

    <?php if(!empty($message)): ?>
    <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <?php echo $message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('toast').remove(), 3000);</script>
    <?php endif; ?>

    <?php if (!isset($_SESSION['loggedin'])): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Đăng Nhập Admin</h2>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" required class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-gray-50">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700 transition">
                    Đăng Nhập
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <header class="bg-white border-b sticky top-0 z-40 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <h1 class="font-bold text-gray-800 text-lg md:text-xl">Quản Trị</h1>
                <a href="index.php" target="_blank" class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200">Xem Web</a>
                <a href="admin.php" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">+ Viết mới</a>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 hidden md:inline">Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="?logout=true" class="text-sm text-red-600 hover:text-red-800 font-medium">Đăng xuất</a>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto p-4 md:p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="md:col-span-2 space-y-4">
            <form method="post" enctype="multipart/form-data" id="postForm">
                <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
                
                <input type="text" name="title" required placeholder="Nhập tiêu đề bài viết..." 
                       value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>"
                       class="w-full text-xl font-bold border-none focus:ring-0 p-2 bg-transparent placeholder-gray-400 outline-none">
                
                <div class="bg-white p-3 rounded-lg border border-dashed border-gray-300 mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ảnh minh họa (Upload lên Supabase):</label>
                    <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div id="toolbar-container">
                    <span class="ql-formats">
                        <button class="ql-bold"></button>
                        <button class="ql-italic"></button>
                        <button class="ql-underline"></button>
                        <select class="ql-header">
                            <option value="1"></option>
                            <option value="2"></option>
                            <option selected></option>
                        </select>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-list" value="ordered"></button>
                        <button class="ql-list" value="bullet"></button>
                        <button class="ql-link"></button>
                        <button class="ql-clean"></button>
                    </span>
                    <span class="ql-formats border-l pl-2 ml-2">
                        <button type="button" id="btn-insert-video" title="Chèn Link Video" style="width:auto; padding:0 5px;">
                            ▶ Video
                        </button>
                        <button type="button" id="btn-paste" title="Dán từ Clipboard">📋</button>
                        <button type="button" id="btn-clean-text" title="Làm sạch văn bản">🧹</button>
                    </span>
                </div>

                <div id="editor-wrapper">
                    <div id="editor">
                        <?php echo $edit_mode ? $editing_post['content'] : ''; ?>
                    </div>
                </div>

                <input type="hidden" name="content" id="hiddenContent">

                <div class="mt-4 flex gap-3">
                    <button type="submit" name="save_post" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 shadow-md transition">
                        <?php echo $edit_mode ? "Lưu Thay Đổi" : "🚀 Đăng Bài Ngay"; ?>
                    </button>
                    <?php if($edit_mode): ?>
                        <a href="admin.php" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">Hủy</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow border overflow-hidden">
                <div class="p-3 bg-gray-50 border-b font-bold text-gray-700">Danh sách bài viết</div>
                <div class="max-h-[600px] overflow-y-auto">
                    <?php if (empty($all_posts)): ?>
                        <p class="p-4 text-gray-500 text-center text-sm">Chưa có bài viết nào.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach ($all_posts as $post): ?>
                                <li class="p-3 hover:bg-blue-50 transition group">
                                    <div class="font-medium text-gray-800 line-clamp-2 mb-1">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-400"><?php echo date("d/m", strtotime($post['created_at'])); ?></span>
                                        <div class="flex gap-2">
                                            <a href="admin.php?edit=<?php echo $post['id']; ?>" class="text-blue-600 hover:underline">Sửa</a>
                                            <a href="admin.php?delete=<?php echo $post['id']; ?>" onclick="return confirm('Xóa bài này?')" class="text-red-600 hover:underline">Xóa</a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    
    <script>
        // 1. Khởi tạo Quill
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: '#toolbar-container'
            },
            placeholder: 'Soạn nội dung tại đây...'
        });

        // 2. Xử lý khi Submit Form
        var form = document.getElementById('postForm');
        form.onsubmit = function() {
            // Lấy HTML từ Quill và gán vào input ẩn để PHP đọc được
            var content = document.querySelector('input[name=content]');
            content.value = quill.root.innerHTML;
            
            // Validate sơ bộ
            if(content.value.trim() === '<p><br></p>' || content.value.trim() === '') {
                alert('Nội dung không được để trống!');
                return false;
            }
            return true;
        };

        // 3. Các nút chức năng Custom
        
        // --- Chèn Video (Giữ logic cũ của bạn nhưng tương thích Quill) ---
        document.getElementById('btn-insert-video').addEventListener('click', function() {
            let link = prompt("Dán đường link video (Youtube/Facebook) vào đây:", "");
            if (link && link.trim() !== "") {
                const range = quill.getSelection(true);
                // Chèn link dưới dạng text thuần + xuống dòng để script hiển thị video ở index.php bắt được
                quill.insertText(range.index, '\n' + link.trim() + '\n', 'user');
                quill.setSelection(range.index + link.length + 2);
            }
        });

        // --- Dán từ Clipboard ---
        document.getElementById('btn-paste').addEventListener('click', async () => {
            try {
                const text = await navigator.clipboard.readText();
                if (text) {
                    const range = quill.getSelection(true);
                    quill.insertText(range.index, text);
                }
            } catch (err) {
                alert('Trình duyệt không cho phép đọc Clipboard. Hãy dùng Ctrl+V.');
            }
        });

        // --- Làm sạch văn bản (Xóa Emoji, Format lạ) ---
        document.getElementById('btn-clean-text').addEventListener('click', () => {
            if(confirm('Bạn có muốn làm sạch văn bản (xóa định dạng thừa, emoji)?')) {
                let text = quill.getText();
                // Logic làm sạch cơ bản (giống mẫu bạn gửi)
                text = text.replace(/([\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD10-\uDDFF])/g, '');
                quill.setText(text);
            }
        });

    </script>
    <?php endif; ?>
</body>
</html>
