<?php
// KHÔNG ĐỂ DÒNG TRỐNG HAY KHOẢNG TRẮNG NÀO TRƯỚC DÒNG <?php NÀY
require_once 'db.php';

// Báo cho trình duyệt và Google biết đây là file XML
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Tên miền thực tế
$base_url = "https://tintuc.pmtl.site";

try {
    $pdo = getDB();
    
    // TỐI ƯU 1: Lấy ngày giờ của bài viết mới nhất để làm <lastmod> cho Trang chủ
    $stmt_latest = $pdo->query("SELECT created_at FROM posts ORDER BY id DESC LIMIT 1");
    $latest_post = $stmt_latest->fetch(PDO::FETCH_ASSOC);
    // Nếu có bài viết thì lấy ngày bài mới nhất, nếu không thì lấy ngày hiện tại
    $home_lastmod = $latest_post ? date("c", strtotime($latest_post['created_at'])) : date("c");

    // Index trang chủ
    echo "  <url>\n";
    echo "      <loc>" . $base_url . "/</loc>\n";
    echo "      <lastmod>" . $home_lastmod . "</lastmod>\n"; 
    echo "      <changefreq>daily</changefreq>\n";
    echo "      <priority>1.0</priority>\n";
    echo "  </url>\n";

    // Vòng lặp lấy danh sách bài viết từ Database
    $stmt = $pdo->query("SELECT id, created_at FROM posts ORDER BY id DESC");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Chuẩn hóa thời gian theo định dạng ISO 8601 (Yêu cầu của Google)
        $date = date("c", strtotime($row['created_at']));
        
        echo "  <url>\n";
        
        echo "      <loc>" . $base_url . "/post-" . $row['id'] . ".html</loc>\n";
        
        
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
