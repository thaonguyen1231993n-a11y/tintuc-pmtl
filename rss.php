<?php
// KHÔNG ĐỂ DÒNG TRỐNG HAY KHOẢNG TRẮNG NÀO TRƯỚC DÒNG <?php NÀY
require_once 'db.php';

// Báo cho trình duyệt và các bot biết đây là định dạng RSS/XML
header("Content-Type: application/rss+xml; charset=utf-8");

$base_url = "https://tintuc.pmtl.site";

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
echo '<channel>' . "\n";
echo '  <title>Pháp Môn Tâm Linh 心靈法門</title>' . "\n";
echo '  <link>' . $base_url . '/</link>' . "\n";
echo '  <description>Trang tin tức mới nhất từ Pháp Môn Tâm Linh</description>' . "\n";
echo '  <language>vi</language>' . "\n";
echo '  <atom:link href="' . $base_url . '/rss.php" rel="self" type="application/rss+xml" />' . "\n";

try {
    $pdo = getDB();
    // Lấy 20 bài viết mới nhất
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC LIMIT 20");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $post_url = $base_url . "/post-" . $row['id'] . ".html";
        $pubDate = date(DATE_RSS, strtotime($row['created_at']));
        $title = htmlspecialchars(strip_tags($row['title']));
        
        // Tạo mô tả ngắn (250 ký tự) cho Email
        $plain_text = strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', $row['content']));
        $description = htmlspecialchars(mb_substr($plain_text, 0, 250, "UTF-8") . "...");
        
        // Trích xuất ảnh bìa để chèn vào Email
        $image_url = "";
        if (preg_match('/<img[^>]+src="([^">]+)"/i', $row['content'], $matches)) {
            $img_src = $matches[1];
            if (strpos($img_src, 'http') !== 0) {
                $image_url = $base_url . (strpos($img_src, '/') === 0 ? '' : '/') . $img_src;
            } else {
                $image_url = $img_src;
            }
        }

        echo "  <item>\n";
        echo "      <title>" . $title . "</title>\n";
        echo "      <link>" . $post_url . "</link>\n";
        echo "      <guid isPermaLink=\"true\">" . $post_url . "</guid>\n";
        echo "      <pubDate>" . $pubDate . "</pubDate>\n";
        echo "      <description>" . $description . "</description>\n";
        if ($image_url) {
            echo "      <media:content url=\"" . htmlspecialchars($image_url) . "\" medium=\"image\" />\n";
        }
        echo "  </item>\n";
    }
} catch (Exception $e) {
    // Bỏ qua lỗi để không làm hỏng cấu trúc XML
}

echo '</channel>' . "\n";
echo '</rss>';
?>
