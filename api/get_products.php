<?php
// api/get_products.php
session_start();
require '../config.php';

header('Content-Type: application/json');

$seriesId = isset($_GET['series_id']) ? (int)$_GET['series_id'] : 0;
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (!$seriesId || !$categoryId) {
    echo json_encode([
        'success' => false,
        'error' => 'Series ID and Category ID are required'
    ]);
    exit();
}

try {
    // Get products for specific series and category
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.price, 
            p.image,
            p.stock
        FROM products p
        WHERE p.series_id = ? AND p.category_id = ?
        ORDER BY p.name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $seriesId, $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => number_format((float)$row['price'], 2),
                'image' => $row['image'] ?: 'placeholder.jpg',
                'stock' => (int)$row['stock']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
