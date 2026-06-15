<?php
// BƯỚC 1: XỬ LÝ DỮ LIỆU CHIA SẺ (OPEN GRAPH) CHO FACEBOOK / ZALO
require_once 'db.php';
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'];
$current_url = $domain . $_SERVER['REQUEST_URI'];

// Khởi tạo thẻ mặc định nếu chia sẻ trang chủ
$og_title = "Pháp Môn Tâm Linh 心靈法門";
$og_desc = "Trang tin tức mới nhất";
$og_image = $domain . "/logo.png"; 

// Nếu phát hiện có chia sẻ bài viết cụ thể (?id=)
if (isset($_GET['id'])) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $share_post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($share_post) {
            $og_title = strip_tags($share_post['title']);
            
            // Trích xuất văn bản ngắn (khoảng 150 ký tự) để làm mô tả
            $plain_text = strip_tags($share_post['content']);
            $og_desc = mb_substr($plain_text, 0, 150, "UTF-8") . "...";

            // Tìm ảnh đầu tiên trong bài viết để làm Thumbnail
            if (preg_match('/<img[^>]+src="([^">]+)"/i', $share_post['content'], $matches)) {
                $img_src = $matches[1];
                // Nếu ảnh là đường dẫn tương đối (/uploads/...), ta gắn thêm domain vào
                if (strpos($img_src, 'http') !== 0) {
                    $og_image = $domain . (strpos($img_src, '/') === 0 ? '' : '/') . $img_src;
                } else {
                    $og_image = $img_src;
                }
            }
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pháp Môn Tâm Linh 心靈法門</title>
    <meta property="og:url" content="<?php echo $current_url; ?>" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($og_desc); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>" />
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="style.css?v=1.1">
    <link rel="canonical" href="https://tintuc.pmtl.site/" />
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-8XXJ311L68"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-8XXJ311L68');
    </script>
</head>
<body>

<div class="container">
    <header class="main-header">
        <img src="logo.png" alt="Logo" class="logo">
        <div class="header-content">
            <h1>Pháp Môn Tâm Linh 心靈法門</h1>
            <p>Trang tin tức mới nhất</p>
        </div>
        <a href="admin.php" class="btn-login-header">Đăng nhập</a>
    </header>

    <nav class="main-menu">
        <a href="https://www.pmtl.site/" target="_blank" rel="noopener noreferrer">Trang Chủ</a>
        <a href="https://radio.pmtl.site/" target="_blank" rel="noopener noreferrer">Kênh Radio</a>
        <a href="https://blogs.pmtl.site/" target="_blank" rel="noopener noreferrer">Blogs</a>
        <a href="https://thuvien.pmtl.site/" target="_blank" rel="noopener noreferrer">Thư Viện</a>
        <a href="https://phungsuvienao.pmtl.site/" target="_blank" rel="noopener noreferrer">Phụng Sự Viên Ảo</a>
    </nav>
    <?php
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    require_once 'db.php';
    $limit = 10; 
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $filter_date = isset($_GET['date']) ? $_GET['date'] : '';

    try {
        $pdo = getDB();
        $sqlCount = "SELECT COUNT(*) FROM posts";
        if (!empty($filter_date)) $sqlCount .= " WHERE DATE(created_at) = :fdate";
        $stmtCount = $pdo->prepare($sqlCount);
        if (!empty($filter_date)) $stmtCount->execute([':fdate' => $filter_date]); else $stmtCount->execute();
        $total_records = $stmtCount->fetchColumn();
        $total_pages = ceil($total_records / $limit);

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
                $raw_content = $row['content'];
                $date = date("d/m/Y H:i", strtotime($row['created_at']));

                $featured_media = "";
                $final_content = $raw_content;

                // TÁCH MEDIA
                if (preg_match('/(<iframe.*?>.*?<\/iframe>)/is', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                } 
                elseif (preg_match('/(<img[^>]+>)/i', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                }

                $final_content = preg_replace('/(?<!src="|href="|">)(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $final_content);

                echo '<div id="post-' . $row['id'] . '" class="news-item">';

                // MỚI THÊM: Tạo link và nút share
                $share_link = "https://tintuc.pmtl.site/post-" . $row['id'] . ".html";
                echo '<button class="btn-share" onclick="copyShareLink(\'' . $share_link . '\', this)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                        Chia sẻ
                      </button>';

                echo '<span class="date">' . $date . '</span>';
                echo '<h3 class="title">' . htmlspecialchars($title) . '</h3>';

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
    <footer></footer>
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
            // 1. Xử lý thu gọn bài viết
            var contents = document.querySelectorAll('.content-wrapper');
            contents.forEach(function(div) {
                if (div.scrollHeight <= 280) {
                    div.classList.remove('content-collapsed'); 
                    var btn = div.nextElementSibling;
                    if (btn && btn.classList.contains('btn-readmore')) btn.style.display = 'none';
                }
            });

            // 2. Xử lý Tự động tỷ lệ Video (QUAN TRỌNG)
            var iframes = document.querySelectorAll('.media-box iframe');
            iframes.forEach(function(iframe) {
                var w = iframe.getAttribute('width');
                var h = iframe.getAttribute('height');
                // Nếu mã nhúng có sẵn kích thước, ta dùng nó để tính tỷ lệ
                if (w && h) {
                    iframe.style.aspectRatio = w + " / " + h;
                } else {
                    // Nếu không có, mặc định là 16/9
                    iframe.style.aspectRatio = "16 / 9";
                }
            });

        }, 500); 
    });
</script>
<script>
    // Hàm Copy link khi bấm nút Share
    function copyShareLink(url, btn) {
        navigator.clipboard.writeText(url).then(function() {
            var originalText = btn.innerHTML;
            btn.innerHTML = "✅ Đã copy link!";
            btn.style.backgroundColor = "#4CAF50";
            btn.style.color = "white";
            
            setTimeout(function() {
                btn.innerHTML = originalText;
                btn.style.backgroundColor = "";
                btn.style.color = "";
            }, 2000);
        }).catch(function(err) {
            alert("Lỗi copy: " + err);
        });
    }

    // Tự động cuộn đến bài viết nếu người xem click link từ Facebook/Zalo
    window.addEventListener('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const targetId = urlParams.get('id');
        if (targetId) {
            const targetPost = document.getElementById('post-' + targetId);
            if (targetPost) {
                // Tự động mở khung bài viết nếu nó đang bị thu gọn
                const contentDiv = targetPost.querySelector('.content-collapsed');
                if (contentDiv) {
                    contentDiv.classList.remove('content-collapsed');
                    contentDiv.style.maxHeight = "none";
                    const btnMore = targetPost.querySelector('.btn-readmore');
                    if(btnMore) btnMore.style.display = 'none';
                }
                // Cuộn mượt mà đến bài viết đó
                setTimeout(() => {
                    targetPost.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 500);
            }
        }
    });
</script>
</body>
</html>

