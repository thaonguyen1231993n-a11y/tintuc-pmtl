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
        // Tạo description ngắn
        $plain_text = strip_tags($post['content']);
        $meta_desc = mb_substr($plain_text, 0, 150, "UTF-8") . "...";
        
        // Tìm ảnh đầu tiên làm Thumbnail cho Facebook/Zalo
        $og_image = "https://tintuc.pmtl.site/logo.png"; // Ảnh mặc định
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
</head>
<body>
    <div class="container" style="padding: 20px;">
        <h1 style="color: #8B4513;"><?php echo htmlspecialchars($post['title']); ?></h1>
        <span style="font-size: 12px; color: #A67B5B;"><?php echo date("d/m/Y H:i", strtotime($post['created_at'])); ?></span>
        
        <div class="content-wrapper" style="margin-top: 20px;">
            <?php echo $post['content']; ?>
        </div>
        
        <a href="index.php" style="display: inline-block; margin-top: 30px; color: #8B4513;">&larr; Về trang chủ</a>
    </div>
</body>
</html>
