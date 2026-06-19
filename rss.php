<?php
// KHÔNG ĐỂ DÒNG TRỐNG HAY KHOẢNG TRẮNG NÀO TRƯỚC DÒNG <?php NÀY
require_once 'db.php';

// Định dạng trả về là XML
header("Content-Type: application/rss+xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo '<channel>' . "\n";
echo '  <title>Tin Tức PMTL</title>' . "\n";
echo '  <link>https://tintuc.pmtl.site/</link>' . "\n";
echo '  <description>Trang tin tức mới nhất từ Pháp Môn Tâm Linh</description>' . "\n";
echo '  <language>vi</language>' . "\n";
echo '  <atom:link href="https://tintuc.pmtl.site/rss.php" rel="self" type="application/rss+xml" />' . "\n";

try {
    $pdo = getDB();
    
    // Lấy 30 bài viết mới nhất để nạp vào Google News
    $stmt = $pdo->query("SELECT id, title, content, created_at FROM posts ORDER BY id DESC LIMIT 30");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Đường dẫn bài viết
        $post_url = "https://tintuc.pmtl.site/post-" . $row['id'] . ".html";
        
        // Chuẩn hóa thời gian theo định dạng RFC 2822 (Bắt buộc với RSS)
        $pub_date = date("r", strtotime($row['created_at']));
        
        // Tạo đoạn mô tả ngắn (bỏ thẻ HTML, cắt 200 ký tự đầu)
        $plain_text = strip_tags($row['content']);
        $description = mb_substr($plain_text, 0, 200, "UTF-8") . "...";
        
        echo "  <item>\n";
        echo "      <title><![CDATA[" . $row['title'] . "]]></title>\n";
        echo "      <link>" . $post_url . "</link>\n";
        echo "      <guid isPermaLink=\"true\">" . $post_url . "</guid>\n";
        echo "      <pubDate>" . $pub_date . "</pubDate>\n";
        echo "      <description><![CDATA[" . $description . "]]></description>\n";
        
        // Cung cấp toàn bộ nội dung HTML cho Google News hiển thị
        echo "      <content:encoded><![CDATA[" . $row['content'] . "]]></content:encoded>\n";
        echo "  </item>\n";
    }
} catch (Exception $e) {
    // Nếu có lỗi db thì bỏ qua để không phá vỡ cấu trúc XML
}

echo '</channel>' . "\n";
echo '</rss>';
?>
