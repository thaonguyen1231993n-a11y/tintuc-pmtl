<?php
session_start();
require_once 'db.php';

$message = "";

// --- CẤU HÌNH DATABASE & LOGIN ---
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

// --- HÀM UPLOAD ẢNH SUPABASE ---
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

// --- XỬ LÝ LOGIN ---
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

// --- XỬ LÝ LƯU/XÓA ---
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
        /* Thiết lập Full màn hình không cuộn body */
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f3f4f6; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; /* Chặn cuộn toàn trang */
        }
        
        /* Container chính cho vùng soạn thảo */
        .editor-container-wrap {
            flex-grow: 1; /* Chiếm hết không gian còn lại */
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden; /* Để bo góc hoạt động */
            margin-bottom: 10px;
        }

        /* Thanh công cụ */
        .ql-toolbar { 
            background: #f9fafb; 
            border-top: none !important; 
            border-left: none !important; 
            border-right: none !important;
            border-bottom: 1px solid #e5e7eb !important;
            display: flex; 
            flex-wrap: wrap; 
            align-items: center; 
            padding: 8px !important;
        }

        /* Vùng chứa Editor - Quan trọng để cuộn bên trong */
        #editor-wrapper {
            flex-grow: 1;
            overflow-y: auto; /* Thanh cuộn nằm ở đây */
            position: relative;
        }
        
        .ql-container { 
            border: none !important; 
            font-size: 16px; 
            font-family: 'Inter', sans-serif;
            height: 100%; /* Full chiều cao của wrapper */
        }
        
        /* Custom buttons */
        .ql-custom-buttons { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            border-left: 1px solid #d1d5db; 
            padding-left: 10px; 
            margin-left: 10px;
        }
        
        /* Style cho nút icon trong toolbar */
        .custom-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            color: #4b5563;
            transition: all 0.2s;
            cursor: pointer;
        }
        .custom-icon-btn:hover { background-color: #e5e7eb; color: #000; }
        .custom-icon-btn svg { width: 20px; height: 20px; }
        
        /* Trạng thái khi đã chọn ảnh */
        .has-image { color: #2563eb !important; background-color: #dbeafe !important; }

        /* Modal transitions */
        .modal { transition: opacity 0.25s ease; }
    </style>
</head>
<body class="text-gray-800">

    <?php if(!empty($message)): ?>
    <div id="toast" class="fixed top-16 left-1/2 transform -translate-x-1/2 bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg z-[60]">
        <?php echo $message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('toast').remove(), 3000);</script>
    <?php endif; ?>

    <?php if (!isset($_SESSION['loggedin'])): ?>
    <div class="min-h-screen flex items-center justify-center p-4 w-full">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Đăng Nhập Admin</h2>
            <form method="post" class="space-y-4">
                <input type="text" name="username" required placeholder="Username" class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                <input type="password" name="password" required placeholder="Password" class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                <button type="submit" name="login" class="w-full bg-blue-600 text-white py-2 rounded-lg font-bold hover:bg-blue-700">Đăng Nhập</button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <header class="bg-white border-b shadow-sm z-40 flex-shrink-0">
        <div class="max-w-6xl mx-auto px-4 py-2 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="admin.php" class="text-lg font-bold text-gray-800 hover:text-blue-600">Admin</a>
                
                <button id="btn-open-list" class="flex items-center gap-1 text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-200 border">
                    📂 Danh Sách
                </button>

                <button type="button" id="btn-header-save" class="flex items-center gap-1 text-xs bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 font-bold shadow-sm">
                    🚀 Đăng Bài
                </button>
                
                <?php if($edit_mode): ?>
                    <a href="admin.php" class="text-xs text-red-500 underline ml-1">Hủy sửa</a>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="index.php" target="_blank" class="text-xs text-gray-500 hover:underline hidden md:inline">Xem Web</a>
                <a href="?logout=true" class="text-xs text-red-600 font-medium hover:underline">Thoát</a>
            </div>
        </div>
    </header>

    <div class="flex-grow flex flex-col max-w-4xl mx-auto w-full p-2 md:p-4 overflow-hidden">
        
        <form method="post" enctype="multipart/form-data" id="postForm" class="flex flex-col h-full">
            <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
            
            <input type="text" name="title" required placeholder="Tiêu đề bài viết..." 
                   value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>"
                   class="flex-shrink-0 w-full text-xl md:text-2xl font-bold border-none focus:ring-0 p-2 bg-transparent placeholder-gray-400 outline-none mb-2">
            
            <input type="file" name="image" id="hidden-image-input" accept="image/*" class="hidden">

            <div class="editor-container-wrap">
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
                    
                    <span class="ql-formats ql-custom-buttons">
                        <button type="button" id="btn-trigger-image" class="custom-icon-btn" title="Thêm Ảnh Minh Họa">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </button>

                        <button type="button" id="btn-insert-video" class="custom-icon-btn" title="Chèn Video (Mã Nhúng)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                        </button>

                        <button type="button" id="btn-paste" class="custom-icon-btn" title="Dán Văn Bản">📋</button>
                        <button type="button" id="btn-clean-text" class="custom-icon-btn" title="Làm Sạch Văn Bản">🧹</button>
                    </span>
                </div>

                <div id="editor-wrapper">
                    <div id="editor">
                        <?php echo $edit_mode ? $editing_post['content'] : ''; ?>
                    </div>
                </div>
            </div>

            <input type="hidden" name="content" id="hiddenContent">
            <button type="submit" name="save_post" id="btn-real-submit" class="hidden"></button>
        </form>
    </div>

    <div id="modal-post-list" class="hidden fixed inset-0 z-50 overflow-hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-lg shadow-xl flex flex-col max-h-[80vh]">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                <h3 class="text-lg font-bold text-gray-800">Danh Sách Bài Viết</h3>
                <button class="modal-close text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
            </div>
            <div class="flex-grow overflow-y-auto p-2">
                <?php if (empty($all_posts)): ?>
                    <p class="text-center text-gray-500 mt-10">Chưa có bài viết nào.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($all_posts as $post): ?>
                            <li class="p-3 hover:bg-blue-50 transition group rounded">
                                <div class="font-medium text-gray-800 mb-1 line-clamp-1">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-400"><?php echo date("d/m H:i", strtotime($post['created_at'])); ?></span>
                                    <div class="flex gap-2">
                                        <a href="admin.php?edit=<?php echo $post['id']; ?>" class="text-blue-600 font-medium hover:underline">Sửa</a>
                                        <a href="admin.php?delete=<?php echo $post['id']; ?>" onclick="return confirm('Xóa bài này?')" class="text-red-600 font-medium hover:underline">Xóa</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal-video-embed" class="hidden fixed inset-0 z-50 overflow-hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-lg shadow-xl p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                    Chèn Video
                </h3>
                <button class="video-modal-close text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <p class="text-sm text-gray-500 mb-2">Dán mã nhúng (iframe) từ Youtube/Facebook:</p>
            <textarea id="embed-code-input" rows="4" class="w-full p-3 border rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 text-xs font-mono mb-4" placeholder='<iframe src="...'></iframe>'></textarea>
            <div class="flex justify-end gap-2">
                <button class="video-modal-close px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">Hủy</button>
                <button id="btn-confirm-embed" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 font-bold">Chèn Ngay</button>
            </div>
        </div>
    </div>

    <script>
        // 1. Khởi tạo Quill
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: { toolbar: '#toolbar-container' },
            placeholder: 'Nội dung bài viết...'
        });

        // 2. Kích hoạt Submit từ Header
        document.getElementById('btn-header-save').onclick = function() {
            var content = document.querySelector('input[name=content]');
            content.value = quill.root.innerHTML;
            if(content.value.trim() === '<p><br></p>' || content.value.trim() === '') {
                alert('Nội dung trống!'); return;
            }
            // Kích hoạt nút submit thật
            document.getElementById('btn-real-submit').click();
        };

        // 3. Xử lý Nút Ảnh (Kích hoạt input ẩn)
        const btnImage = document.getElementById('btn-trigger-image');
        const hiddenInput = document.getElementById('hidden-image-input');
        
        btnImage.onclick = function() {
            hiddenInput.click();
        };
        
        // Khi chọn ảnh xong -> Đổi màu icon
        hiddenInput.onchange = function() {
            if(this.files && this.files[0]) {
                btnImage.classList.add('has-image');
                btnImage.title = "Đã chọn: " + this.files[0].name;
            } else {
                btnImage.classList.remove('has-image');
            }
        };

        // 4. Modal Danh Sách
        const listModal = document.getElementById('modal-post-list');
        const openListBtn = document.getElementById('btn-open-list');
        const closeListBtns = document.querySelectorAll('.modal-close');

        function toggleListModal() { listModal.classList.toggle('hidden'); }
        if(openListBtn) openListBtn.onclick = toggleListModal;
        closeListBtns.forEach(btn => btn.onclick = toggleListModal);

        // 5. Modal Video
        const videoModal = document.getElementById('modal-video-embed');
        const openVideoBtn = document.getElementById('btn-insert-video');
        const closeVideoBtns = document.querySelectorAll('.video-modal-close');
        const confirmEmbedBtn = document.getElementById('btn-confirm-embed');
        const embedInput = document.getElementById('embed-code-input');

        function toggleVideoModal() {
            videoModal.classList.toggle('hidden');
            if(!videoModal.classList.contains('hidden')) {
                embedInput.value = ''; embedInput.focus();
            }
        }
        if(openVideoBtn) openVideoBtn.onclick = toggleVideoModal;
        closeVideoBtns.forEach(btn => btn.onclick = toggleVideoModal);

        confirmEmbedBtn.onclick = function() {
            const code = embedInput.value.trim();
            if(code.includes('<iframe')) {
                const range = quill.getSelection(true);
                quill.insertText(range ? range.index : 0, '\n' + code + '\n', 'user');
                toggleVideoModal();
            } else {
                alert("Vui lòng dán đúng mã <iframe>!");
            }
        };

        // 6. Tiện ích khác
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
    </script>
    <?php endif; ?>
</body>
</html>
