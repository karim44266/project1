<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$cartIds = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cartIds)) {
    header("Location: cart.php");
    exit();
}

// Calculate total and insert order
$total = 0;

$stmt = $conn->prepare("INSERT INTO orders (user_id, total) VALUES (?, 0)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$order_id = $stmt->insert_id;
$stmt->close();

// Fetch product details for all cart items
$placeholders = implode(',', array_fill(0, count($cartIds), '?'));
$types = str_repeat('i', count($cartIds));
$sql = "SELECT id, price FROM products WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$cartIds);
$stmt->execute();
$result = $stmt->get_result();

$insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, ?)");
while ($row = $result->fetch_assoc()) {
    $price = $row['price'];
    $total += $price;
    $insertItem->bind_param("iid", $order_id, $row['id'], $price);
    $insertItem->execute();
}
$insertItem->close();
$stmt->close();

// Update order total
$update = $conn->prepare("UPDATE orders SET total = ? WHERE id = ?");
$update->bind_param("di", $total, $order_id);
$update->execute();
$update->close();

// Clear cart
unset($_SESSION['cart']);

header("Location: order_success.php");
exit();
