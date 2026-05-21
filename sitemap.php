<?php
require_once 'db.php';

// Báo cho trình duyệt và Google biết đây là file XML
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// QUAN TRỌNG: Sửa lại thành tên miền thực tế của bạn
$base_url = "https://tintuc.pmtl.site";

// Index trang chủ
echo "  <url>\n";
echo "      <loc>" . $base_url . "/</loc>\n";
echo "      <changefreq>daily</changefreq>\n";
echo "      <priority>1.0</priority>\n";
echo "  </url>\n";

// Vòng lặp lấy danh sách bài viết từ Database
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, created_at FROM posts ORDER BY id DESC");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Chuẩn hóa thời gian theo định dạng ISO 8601 (Yêu cầu của Google)
        $date = date("c", strtotime($row['created_at']));
        
        echo "  <url>\n";
        echo "      <loc>" . $base_url . "/post.php?id=" . $row['id'] . "</loc>\n";
        echo "      <lastmod>" . $date . "</lastmod>\n";
        echo "      <changefreq>monthly</changefreq>\n";
        echo "      <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
} catch (Exception $e) {
    // Bỏ qua lỗi DB để không làm hỏng cấu trúc file XML
}

echo '</urlset>';
?>
