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
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        
        /* CSS Quill */
        #editor-wrapper {
            display: flex; flex-direction: column; 
            background: white; border-radius: 0 0 8px 8px; border: 1px solid #d1d5db; border-top: 0;
            min-height: 500px; /* Tăng chiều cao mặc định lên */
        }
        .ql-container { flex-grow: 1; font-size: 16px; min-height: 400px; font-family: 'Inter', sans-serif;}
        .ql-toolbar { background: #f9fafb; border-radius: 8px 8px 0 0; border-color: #d1d5db !important; display: flex; flex-wrap: wrap; align-items: center; }
        .ql-editor { min-height: 400px; }
        
        /* Custom buttons in toolbar */
        .ql-custom-buttons { display: flex; align-items: center; gap: 5px; border-left: 1px solid #ddd; padding-left: 8px; margin-left: 8px;}
        .ql-custom-buttons button { width: 28px !important; height: 24px !important; }

        /* Modal transitions */
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
</head>
<body class="text-gray-800">

    <?php if(!empty($message)): ?>
    <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg z-[60]">
        <?php echo $message; ?>
    </div>
    <script>setTimeout(() => document.getElementById('toast').remove(), 3000);</script>
    <?php endif; ?>

    <?php if (!isset($_SESSION['loggedin'])): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
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
    <header class="bg-white border-b sticky top-0 z-40 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <h1 class="font-bold text-gray-800 text-lg hidden md:block">Quản Trị</h1>
                <a href="index.php" target="_blank" class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200">Xem Web</a>
                <a href="admin.php" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">+ Viết mới</a>
                
                <button id="btn-open-list" class="flex items-center gap-1 text-xs bg-yellow-100 text-yellow-800 px-3 py-1 rounded hover:bg-yellow-200 font-bold border border-yellow-300">
                    📂 Danh Sách Bài Viết
                </button>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 hidden md:inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="?logout=true" class="text-sm text-red-600 hover:text-red-800 font-medium">Thoát</a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto p-4 md:p-6">
        
        <form method="post" enctype="multipart/form-data" id="postForm">
            <input type="hidden" name="edit_id" value="<?php echo $edit_mode ? $editing_post['id'] : ''; ?>">
            
            <input type="text" name="title" required placeholder="Nhập tiêu đề bài viết..." 
                   value="<?php echo $edit_mode ? htmlspecialchars($editing_post['title']) : ''; ?>"
                   class="w-full text-2xl font-bold border-none focus:ring-0 p-2 bg-transparent placeholder-gray-400 outline-none mb-4">
            
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
                <span class="ql-formats ql-custom-buttons">
                    <button type="button" id="btn-insert-video" title="Dán mã nhúng Video" style="width:auto; padding:0 5px;">
                        ▶ Embed
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

            <div class="mt-6 flex gap-3">
                <button type="submit" name="save_post" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 shadow-md transition text-lg">
                    <?php echo $edit_mode ? "Lưu Thay Đổi" : "Đăng Bài Ngay"; ?>
                </button>
                <?php if($edit_mode): ?>
                    <a href="admin.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div id="modal-post-list" class="hidden fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex">
        <div class="relative p-6 bg-white w-full max-w-2xl m-auto flex-col flex rounded-lg shadow-xl h-[80vh]">
            <div class="flex justify-between items-center pb-4 border-b">
                <h3 class="text-xl font-bold text-gray-800">Danh Sách Bài Viết</h3>
                <button class="modal-close cursor-pointer z-50 text-gray-500 hover:text-gray-800 text-3xl leading-none">&times;</button>
            </div>
            <div class="flex-grow overflow-y-auto mt-4">
                <?php if (empty($all_posts)): ?>
                    <p class="text-center text-gray-500 mt-10">Chưa có bài viết nào.</p>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($all_posts as $post): ?>
                            <li class="p-4 hover:bg-blue-50 transition group rounded-lg">
                                <div class="font-bold text-gray-800 mb-1">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-400"><?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?></span>
                                    <div class="flex gap-3">
                                        <a href="admin.php?edit=<?php echo $post['id']; ?>" class="text-blue-600 hover:font-bold">Sửa</a>
                                        <a href="admin.php?delete=<?php echo $post['id']; ?>" onclick="return confirm('Xóa bài này?')" class="text-red-600 hover:font-bold">Xóa</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal-video-embed" class="hidden fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex">
        <div class="relative p-6 bg-white w-full max-w-lg m-auto flex-col flex rounded-lg shadow-xl">
            <div class="flex justify-between items-center pb-2">
                <h3 class="text-lg font-bold text-gray-800">Chèn Video (Mã Nhúng)</h3>
                <button class="video-modal-close cursor-pointer text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-gray-500 mb-3">Copy mã iframe từ Youtube/Facebook và dán vào đây:</p>
            <textarea id="embed-code-input" rows="5" class="w-full p-3 border rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono" placeholder='<iframe src="...'></iframe>'></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <button class="video-modal-close px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Hủy</button>
                <button id="btn-confirm-embed" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold">Chèn Video</button>
            </div>
        </div>
    </div>

    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: { toolbar: '#toolbar-container' },
            placeholder: 'Soạn nội dung tại đây...'
        });

        var form = document.getElementById('postForm');
        form.onsubmit = function() {
            var content = document.querySelector('input[name=content]');
            content.value = quill.root.innerHTML;
            if(content.value.trim() === '<p><br></p>' || content.value.trim() === '') {
                alert('Nội dung không được để trống!'); return false;
            }
            return true;
        };

        // --- XỬ LÝ MODAL DANH SÁCH BÀI VIẾT ---
        const listModal = document.getElementById('modal-post-list');
        const openListBtn = document.getElementById('btn-open-list');
        const closeListBtns = document.querySelectorAll('.modal-close');

        function toggleListModal() {
            listModal.classList.toggle('hidden');
            document.body.classList.toggle('modal-active');
        }

        if(openListBtn) openListBtn.onclick = toggleListModal;
        closeListBtns.forEach(btn => btn.onclick = toggleListModal);

        // --- XỬ LÝ MODAL CHÈN VIDEO (MÃ NHÚNG) ---
        const videoModal = document.getElementById('modal-video-embed');
        const openVideoBtn = document.getElementById('btn-insert-video');
        const closeVideoBtns = document.querySelectorAll('.video-modal-close');
        const confirmEmbedBtn = document.getElementById('btn-confirm-embed');
        const embedInput = document.getElementById('embed-code-input');

        function toggleVideoModal() {
            videoModal.classList.toggle('hidden');
            if(!videoModal.classList.contains('hidden')) {
                embedInput.value = ''; // Reset khi mở
                embedInput.focus();
            }
        }

        if(openVideoBtn) openVideoBtn.onclick = toggleVideoModal;
        closeVideoBtns.forEach(btn => btn.onclick = toggleVideoModal);

        // Khi bấm nút "Chèn Video" trong Modal
        confirmEmbedBtn.onclick = function() {
            const code = embedInput.value.trim();
            if(code) {
                const range = quill.getSelection(true);
                // Chèn mã iframe vào Quill
                // Mẹo: Chèn text thuần bao quanh bởi xuống dòng để PHP nhận diện
                quill.insertText(range.index, '\n' + code + '\n', 'user');
                toggleVideoModal();
            } else {
                alert("Vui lòng dán mã nhúng vào!");
            }
        };

        // --- CÁC NÚT KHÁC ---
        document.getElementById('btn-paste').addEventListener('click', async () => {
            try {
                const text = await navigator.clipboard.readText();
                if (text) {
                    const range = quill.getSelection(true);
                    quill.insertText(range.index, text);
                }
            } catch (err) { alert('Không đọc được Clipboard. Dùng Ctrl+V.'); }
        });

        document.getElementById('btn-clean-text').addEventListener('click', () => {
            if(confirm('Làm sạch văn bản?')) {
                let text = quill.getText();
                text = text.replace(/([\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD10-\uDDFF])/g, '');
                quill.setText(text);
            }
        });

        // Đóng modal khi click ra ngoài
        window.onclick = function(event) {
            if (event.target == listModal) toggleListModal();
            if (event.target == videoModal) toggleVideoModal();
        }
    </script>
    <?php endif; ?>
</body>
</html>
