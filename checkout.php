<?php
session_start();
require_once './includes/config.php';

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Создаем заказ
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total) VALUES (?, ?)");
    $total = calculateTotal($_SESSION['cart'], $pdo);
    $stmt->execute([$_SESSION['user_id'], $total]);
    $order_id = $pdo->lastInsertId();
    
    // Добавляем товары
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $product = getProduct($product_id, $pdo);
        $stmt = $pdo->prepare("INSERT INTO order_items VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
    }
    
    unset($_SESSION['cart']);
    header("Location: order_success.php?id=$order_id");
}

function calculateTotal($cart, $pdo) {
    $total = 0;
    foreach ($cart as $product_id => $quantity) {
        $product = getProduct($product_id, $pdo);
        $total += $product['price'] * $quantity;
    }
    return $total;
}
?>