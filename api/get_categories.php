<?php
// api/get_categories.php
session_start();
require '../config.php';

header('Content-Type: application/json');

try {
    // Get categories with series count
    $sql = "
        SELECT 
            c.id, 
            c.name, 
            c.image,
            COUNT(DISTINCT s.id) as series_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN series s ON s.id = p.series_id
        GROUP BY c.id, c.name, c.image
        ORDER BY c.name ASC
    ";
    
    $result = $conn->query($sql);
    $categories = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'image' => $row['image'] ?: 'placeholder.jpg',
                'seriesCount' => (int)$row['series_count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>