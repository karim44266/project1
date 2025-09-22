<?php
// api/get_series_name.php
session_start();
require '../config.php';

header('Content-Type: application/json');

$seriesId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$seriesId) {
    echo json_encode([
        'success' => false,
        'error' => 'Series ID is required'
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT name FROM series WHERE id = ?");
    $stmt->bind_param("i", $seriesId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => ['name' => $row['name']]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Series not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>