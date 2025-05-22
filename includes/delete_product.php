<?php
// Проверка аутентификации
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Доступ запрещен');
}

// Получаем ID товара
$product_id = (int)($_POST['product_id'] ?? 0);

try {
    // 1. Получаем информацию о товаре
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception("Товар не найден");
    }

    // 2. Удаляем изображение
    if (!empty($product['image'])) {
        $image_path = $_SERVER['DOCUMENT_ROOT'] . '/images/' . $product['image'];
        if (file_exists($image_path) && is_writable($image_path)) {
            unlink($image_path);
        }
    }

    // 3. Удаляем записи из связанных таблиц
    $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM product_features WHERE product_id = ?")->execute([$product_id]);

    // 4. Удаляем сам товар
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);

    $_SESSION['success'] = "Товар успешно удален";
} catch (Exception $e) {
    $_SESSION['error'] = "Ошибка удаления: " . $e->getMessage();
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>