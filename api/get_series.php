<?php
// api/get_series.php
session_start();
require '../config.php';

header('Content-Type: application/json');

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (!$categoryId) {
    echo json_encode([
        'success' => false,
        'error' => 'Category ID is required'
    ]);
    exit();
}

try {
    // Get series for specific category with product count
    $sql = "
        SELECT 
            s.id, 
            s.name, 
            s.image,
            COUNT(p.id) as products_count
        FROM series s
        INNER JOIN products p ON s.id = p.series_id
        WHERE p.category_id = ?
        GROUP BY s.id, s.name, s.image
        ORDER BY s.name ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $series = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $series[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'image' => $row['image'] ?: 'placeholder.jpg',
                'productsCount' => (int)$row['products_count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $series
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
