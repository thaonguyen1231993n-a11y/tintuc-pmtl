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

    <?php
    require_once 'db.php';

    // --- CẤU HÌNH PHÂN TRANG & LỌC ---
    $limit = 10; // Số bài mỗi trang
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // Lấy ngày lọc (nếu có)
    $filter_date = isset($_GET['date']) ? $_GET['date'] : '';

    try {
        $pdo = getDB();

        // 1. ĐẾM TỔNG SỐ BÀI (Để tính số trang)
        $sqlCount = "SELECT COUNT(*) FROM posts";
        if (!empty($filter_date)) {
            $sqlCount .= " WHERE DATE(created_at) = :fdate";
        }
        $stmtCount = $pdo->prepare($sqlCount);
        if (!empty($filter_date)) {
            $stmtCount->execute([':fdate' => $filter_date]);
        } else {
            $stmtCount->execute();
        }
        $total_records = $stmtCount->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        // 2. LẤY DỮ LIỆU BÀI VIẾT (Có phân trang)
        $sql = "SELECT * FROM posts";
        if (!empty($filter_date)) {
            $sql .= " WHERE DATE(created_at) = :fdate";
        }
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        if (!empty($filter_date)) {
            $stmt->bindValue(':fdate', $filter_date);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

    ?>

    <div class="filter-bar">
        <form method="get" action="index.php">
            <label>Xem bài ngày:</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <button type="submit" class="btn-filter">Xem</button>
            <?php if(!empty($filter_date)): ?>
                <a href="index.php" style="color:red; text-decoration:none; font-size:14px;">[Xóa lọc]</a>
            <?php endif; ?>
        </form>
        <span style="font-size:14px; color:#666;">
            Tổng: <?php echo $total_records; ?> bài
        </span>
    </div>

    <div class="news-list">
        <?php
        // Hàm xử lý hiển thị nội dung text
        function displayContent($content) {
            if (empty($content)) return "";
            // Sửa lỗi embed
            $content = preg_replace_callback('/<(blockquote|iframe|script|div)([^>]*)>/s', function ($matches) {
                return '<' . $matches[1] . str_replace(["\n", "\r"], " ", $matches[2]) . '>';
            }, $content);
            // Youtube embed
            $content = preg_replace('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', 
                '<div class="video-responsive"><iframe src="https://www.youtube.com/embed/$1" allowfullscreen></iframe></div>', $content);
            // Link text
            $content = preg_replace('/(?<!src="|href="|">)(https?:\/\/[^\s<]+)/', 
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $content);
            return nl2br($content);
        }

        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $title = $row['title'];
                $raw_content = $row['content'];
                $date = date("d/m/Y H:i", strtotime($row['created_at']));

                // --- LOGIC TÁCH ẢNH RA KHỎI NỘI DUNG ---
                // Tìm thẻ img đầu tiên trong nội dung
                $main_image = "";
                $text_content = $raw_content;

                if (preg_match('/(<img[^>]+>)/i', $raw_content, $matches)) {
                    $main_image = $matches[1]; // Lấy thẻ img
                    // Xóa ảnh đó khỏi nội dung text để không bị lặp
                    $text_content = str_replace($main_image, "", $raw_content);
                }
                // -----------------------------------------

                echo '<div class="news-item">';
                echo '<span class="date">' . $date . '</span>';
                echo '<h3 class="title">' . htmlspecialchars($title) . '</h3>';

                // 1. HIỂN THỊ ẢNH (NẾU CÓ) - LUÔN HIỆN FULL
                if (!empty($main_image)) {
                    // Thêm class để CSS định kiểu
                    echo str_replace('<img', '<img class="post-feature-image"', $main_image);
                }

                // 2. HIỂN THỊ VĂN BẢN (CÓ THU GỌN)
                echo '<div class="content-wrapper content-collapsed">';
                echo displayContent($text_content);
                echo '</div>';
                
                // Nút Xem Thêm
                echo '<button class="btn-readmore" onclick="toggleContent(this)">Xem thêm ▼</button>';
                echo '</div>';
            }
        } else {
            echo '<p class="empty" style="text-align:center; padding:30px;">Không tìm thấy bài viết nào.</p>';
        }
        ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
            // Nút Về trang đầu
            if ($page > 1) {
                echo '<a href="?page=1' . ($filter_date ? '&date='.$filter_date : '') . '">«</a>';
                echo '<a href="?page=' . ($page - 1) . ($filter_date ? '&date='.$filter_date : '') . '">‹</a>';
            }

            // Hiển thị các số trang
            // Chỉ hiện tối đa 5 trang xung quanh trang hiện tại để đỡ dài
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<a href="?page=' . $i . ($filter_date ? '&date='.$filter_date : '') . '" class="' . $active . '">' . $i . '</a>';
            }

            // Nút Đến trang cuối
            if ($page < $total_pages) {
                echo '<a href="?page=' . ($page + 1) . ($filter_date ? '&date='.$filter_date : '') . '">›</a>';
                echo '<a href="?page=' . $total_pages . ($filter_date ? '&date='.$filter_date : '') . '">»</a>';
            }
        ?>
    </div>
    <?php endif; ?>

    <?php
    } catch (Exception $e) {
        echo '<p class="empty" style="color:red">Lỗi kết nối: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>
    
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

    // Tự động ẩn nút xem thêm nếu text quá ngắn
    window.addEventListener('load', function() {
        setTimeout(function() {
            var contents = document.querySelectorAll('.content-wrapper');
            contents.forEach(function(div) {
                // Kiểm tra chiều cao phần TEXT thôi
                if (div.scrollHeight <= 280) {
                    div.classList.remove('content-collapsed'); 
                    var btn = div.nextElementSibling;
                    if (btn && btn.classList.contains('btn-readmore')) {
                        btn.style.display = 'none';
                    }
                }
            });
        }, 500); 
    });
</script>

</body>
</html>
