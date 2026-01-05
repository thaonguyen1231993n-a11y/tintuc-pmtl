<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pháp Môn Tâm Linh 心靈法門</title>
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
</head>
<body>

<div class="container">
    <header class="main-header">
        <img src="logo.png" alt="Logo" class="logo">
        <div class="header-content">
            <h1>Pháp Môn Tâm Linh 心靈法門</h1>
            <p>Trang tin tức mới nhất</p>
        </div>
        <a href="admin.php" class="btn-login-header">
            Đăng nhập
        </a>
    </header>

    <?php
    require_once 'db.php';

    // --- CẤU HÌNH ---
    $limit = 10; 
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $filter_date = isset($_GET['date']) ? $_GET['date'] : '';

    try {
        $pdo = getDB();
        
        // ĐẾM TỔNG BÀI
        $sqlCount = "SELECT COUNT(*) FROM posts";
        if (!empty($filter_date)) $sqlCount .= " WHERE DATE(created_at) = :fdate";
        $stmtCount = $pdo->prepare($sqlCount);
        if (!empty($filter_date)) $stmtCount->execute([':fdate' => $filter_date]);
        else $stmtCount->execute();
        $total_records = $stmtCount->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        // LẤY DỮ LIỆU
        $sql = "SELECT * FROM posts";
        if (!empty($filter_date)) $sql .= " WHERE DATE(created_at) = :fdate";
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if (!empty($filter_date)) $stmt->bindValue(':fdate', $filter_date);
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
        <span style="font-size:14px; color:#666;">Tổng: <?php echo $total_records; ?> bài</span>
    </div>

    <div class="news-list">
        <?php
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $title = $row['title'];
                // Lấy nội dung gốc từ DB
                $raw_content = $row['content'];
                $date = date("d/m/Y H:i", strtotime($row['created_at']));

                $featured_media = "";
                $final_content = $raw_content;

                // --- LOGIC TÁCH MEDIA (Hỗ trợ Iframe thủ công) ---
                // 1. Tìm thẻ IFRAME
                if (preg_match('/(<iframe.*?>.*?<\/iframe>)/is', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                } 
                // 2. Nếu không có Iframe, tìm thẻ IMG
                elseif (preg_match('/(<img[^>]+>)/i', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                }

                // Xử lý link text
                $final_content = preg_replace(
                    '/(?<!src="|href="|">)(https?:\/\/[^\s<]+)/', 
                    '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', 
                    $final_content
                );
                
                // Chuyển xuống dòng
                $final_content = nl2br($final_content);

                echo '<div class="news-item">';
                echo '<span class="date">' . $date . '</span>';
                echo '<h3 class="title">' . htmlspecialchars($title) . '</h3>';

                // HIỂN THỊ MEDIA BOX
                if (!empty($featured_media)) {
                    echo '<div class="media-box">';
                    echo $featured_media; 
                    echo '</div>';
                }

                echo '<div class="content-wrapper content-collapsed">';
                echo $final_content; 
                echo '</div>';
                
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
            if ($page > 1) {
                echo '<a href="?page=1' . ($filter_date ? '&date='.$filter_date : '') . '">«</a>';
                echo '<a href="?page=' . ($page - 1) . ($filter_date ? '&date='.$filter_date : '') . '">‹</a>';
            }
            $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<a href="?page=' . $i . ($filter_date ? '&date='.$filter_date : '') . '" class="' . $active . '">' . $i . '</a>';
            }
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
    <footer><a href="admin.php">Đăng nhập quản trị</a></footer>
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
                    if (btn && btn.classList.contains('btn-readmore')) btn.style.display = 'none';
                }
            });
        }, 500); 
    });
</script>
</body>
</html>

