<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pháp Môn Tâm Linh 心靈法門</title>
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <script async src="https://www.tiktok.com/embed.js"></script>
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/vi_VN/sdk.js#xfbml=1&version=v18.0"></script>
</head>
<body>

<div class="container">
    <header class="main-header">
        <img src="logo.png" alt="Logo" class="logo">
        <div class="header-content">
            <h1>Pháp Môn Tâm Linh 心靈法門</h1>
            <p>Trang tin tức mới nhất</p>
        </div>
    </header>

    <div class="news-list">
        <?php
        // 1. GỌI FILE KẾT NỐI DATABASE
        require_once 'db.php';

        // Hàm xử lý hiển thị nội dung
        function displayContent($content) {
            if (empty($content)) return "";

            // Sửa lỗi TikTok/Facebook embed
            $content = preg_replace_callback(
                '/<(blockquote|iframe|script|div)([^>]*)>/s',
                function ($matches) {
                    return '<' . $matches[1] . str_replace(["\n", "\r"], " ", $matches[2]) . '>';
                },
                $content
            );

            // Link Youtube
            $content = preg_replace(
                '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', 
                '<div class="video-responsive"><iframe src="https://www.youtube.com/embed/$1" allowfullscreen></iframe></div>', 
                $content
            );

            // Link Text thành thẻ a
            $content = preg_replace(
                '/(?<!src="|href="|">)(https?:\/\/[^\s<]+)/', 
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', 
                $content
            );

            // Xuống dòng
            return nl2br($content);
        }

        // 2. LẤY DỮ LIỆU TỪ DATABASE
        try {
            $pdo = getDB();
            
            // Lấy tất cả bài viết, sắp xếp ID giảm dần (bài mới nhất lên đầu)
            $stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");

            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $title = $row['title'];
                    $content = $row['content'];
                    // Chuyển đổi thời gian
                    $date = date("d/m/Y H:i", strtotime($row['created_at']));

                    echo '<div class="news-item">';
                    echo '<span class="date">' . $date . '</span>';
                    echo '<h3 class="title">' . htmlspecialchars($title) . '</h3>';
                    
                    // Nội dung
                    echo '<div class="content-wrapper content-collapsed">';
                    echo displayContent($content);
                    echo '</div>';
                    
                    // Nút Xem Thêm
                    echo '<button class="btn-readmore" onclick="toggleContent(this)">Xem thêm ▼</button>';
                    
                    echo '</div>';
                }
            } else {
                echo '<p class="empty">Chưa có tin tức nào.</p>';
            }

        } catch (Exception $e) {
            // Nếu lỗi kết nối hoặc truy vấn
            echo '<p class="empty" style="color:red">Lỗi kết nối: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    <footer>
        <a href="admin.php">Đăng nhập quản trị</a>
    </footer>
</div>

<script>
    function toggleContent(btn) {
        var contentDiv = btn.previousElementSibling;
        
        if (contentDiv.classList.contains('content-collapsed')) {
            contentDiv.classList.remove('content-collapsed');
            contentDiv.style.maxHeight = "none"; 
            btn.innerHTML = "Thu gọn ▲";
        } else {
            contentDiv.classList.add('content-collapsed');
            contentDiv.style.maxHeight = null; 
            btn.innerHTML = "Xem thêm ▼";
            btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    window.addEventListener('load', function() {
        setTimeout(function() {
            var contents = document.querySelectorAll('.content-wrapper');
            contents.forEach(function(div) {
                if (div.scrollHeight <= 280) {
                    div.classList.remove('content-collapsed'); 
                    var btn = div.nextElementSibling;
                    if (btn && btn.classList.contains('btn-readmore')) {
                        btn.style.display = 'none';
                    }
                }
            });
        }, 1000); 
    });
</script>

</body>
</html>