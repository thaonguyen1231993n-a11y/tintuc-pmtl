<?php
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        die("Bài viết không tồn tại!");
    }
} catch (Exception $e) {
    die("Lỗi kết nối Database.");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    
    <?php 
        // Trích xuất văn bản làm thẻ mô tả SEO
        $plain_text = strip_tags($post['content']);
        $meta_desc = mb_substr($plain_text, 0, 150, "UTF-8") . "...";
        
        // Tìm ảnh trong bài để làm Thumbnail khi share Facebook, Zalo
        $og_image = "https://tintuc.pmtl.site/logo.png";
        if (preg_match('/<img[^>]+src="([^">]+)"/i', $post['content'], $matches)) {
            $img_src = $matches[1];
            if (strpos($img_src, 'http') !== 0) {
                $og_image = "https://tintuc.pmtl.site" . (strpos($img_src, '/') === 0 ? '' : '/') . $img_src;
            } else {
                $og_image = $img_src;
            }
        }
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_desc); ?>">
    
    <meta property="og:url" content="https://tintuc.pmtl.site/post-<?php echo $post['id']; ?>.html" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_desc); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>" />

    <link rel="stylesheet" href="style.css?v=1.1"> 
    <link rel="icon" href="logo.png" type="image/png">
    
    <link rel="canonical" href="https://tintuc.pmtl.site/post-<?php echo $post['id']; ?>.html" />
    <script async type="application/javascript"
            src="https://news.google.com/swg/js/v1/swg-basic.js"></script>
    <script>
      (self.SWG_BASIC = self.SWG_BASIC || []).push( basicSubscriptions => {
        basicSubscriptions.init({
          type: "NewsArticle",
          isPartOfType: ["Product"],
          isPartOfProductId: "CAowwPvGDA:openaccess",
          clientOptions: { theme: "light", lang: "vi" },
        });
      });
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

        <div style="padding: 30px 20px;">
            
            <h1 style="color: #5D4037; font-size: 26px; margin-top: 0; margin-bottom: 15px; font-weight: bold; line-height: 1.4;">
                <?php echo htmlspecialchars($post['title']); ?>
            </h1>
            
            <div style="margin-bottom: 25px;">
                <span style="font-size: 13px; background: #FFF8E1; color: #A67B5B; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                    ⏰ <?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?>
                </span>
            </div>
            
            <?php 
                $raw_content = $post['content'];
                $featured_media = "";
                $final_content = $raw_content;

                // TÁCH MEDIA (VIDEO HOẶC ẢNH) ĐỂ CHO VÀO KHUNG CHUẨN
                if (preg_match('/(<iframe.*?>.*?<\/iframe>)/is', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                } elseif (preg_match('/(<img[^>]+>)/i', $raw_content, $matches)) {
                    $featured_media = $matches[1];
                    $final_content = str_replace($featured_media, "", $raw_content);
                }

                // Tự động chuyển link text thành link click được
                $final_content = preg_replace('/(?<!src="|href="|">)(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $final_content);
                
                // 1. In khung Media (Nếu có video hoặc ảnh)
                if (!empty($featured_media)) {
                    echo '<div class="media-box" style="margin-bottom: 20px;">';
                    echo $featured_media; 
                    echo '</div>';
                }
            ?>
            
            <div class="content-wrapper">
                <?php echo $final_content; ?>
            </div>
            
            <div style="margin-top: 50px; text-align: center; border-top: 1px solid #eee; padding-top: 30px;">
                <a href="/" style="display: inline-block; padding: 10px 30px; background-color: #8B4513; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.1);" onmouseover="this.style.backgroundColor='#6D360F'; this.style.transform='translateY(-2px)';" onmouseout="this.style.backgroundColor='#8B4513'; this.style.transform='translateY(0)';">
                    &larr; Quay về Trang Chủ
                </a>
            </div>

        </div>
        
        <footer></footer>
        
    </div>
    <script>
        window.addEventListener('load', function() {
            var iframes = document.querySelectorAll('.media-box iframe, .content-wrapper iframe');
            iframes.forEach(function(iframe) {
                var w = iframe.getAttribute('width');
                var h = iframe.getAttribute('height');
                if (w && h) {
                    iframe.style.aspectRatio = w + " / " + h;
                } else {
                    iframe.style.aspectRatio = "16 / 9";
                }
            });
        });
    </script>
</body>
</html>
