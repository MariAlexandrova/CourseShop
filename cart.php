<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Добавление товара в корзину
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($quantity > 0) {
        // Проверяем доступное количество
        $stmt = $pdo->prepare("SELECT quantity FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $stock = $stmt->fetchColumn();
        
        if ($stock >= $quantity) {
            $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + $quantity;
            $_SESSION['success'] = "Товар добавлен в корзину!";
        } else {
            $_SESSION['error'] = "Недостаточно товара на складе! Доступно: $stock";
        }
    }
}

// Удаление товара из корзины
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success'] = "Товар удален из корзины!";
    }
}

// Оформление заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!empty($_SESSION['cart'])) {
        try {
            $pdo->beginTransaction();
            // Проверяем доступность всех товаров перед оформлением
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $stmt = $pdo->prepare("SELECT quantity FROM product_stock WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $stock = $stmt->fetchColumn();
                
                if ($stock < $quantity) {
                    throw new Exception("Недостаточно товара '{$product_id}' на складе! Доступно: $stock");
                }
            }
            // Рассчитываем общую сумму с учетом скидок
            $total = 0;
            $order_items = [];
            
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                // Получаем информацию о товаре и акциях
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                    (SELECT MAX(discount) FROM promotions pr 
                    JOIN product_promotions pp ON pr.id = pp.promotion_id 
                    WHERE pp.product_id = p.id AND NOW() BETWEEN pr.start_date AND pr.end_date) as discount
                    FROM products p WHERE p.id = ?
                ");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                $price = $product['price'];
                $discount = $product['discount'] ?? 0;
                $final_price = $price * (1 - $discount / 100);
                $item_total = $final_price * $quantity;
                
                $total += $item_total;
                
                $order_items[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'discount' => $discount
                ];
                // Уменьшаем количество на складе
                $stmt = $pdo->prepare("
                    UPDATE product_stock 
                    SET quantity = quantity - ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$quantity, $product_id]);
            }
            
            // Создаем заказ
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $total]);
            $order_id = $pdo->lastInsertId();
            
            // Добавляем товары в заказ
            foreach ($order_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'] * (1 - $item['discount'] / 100) // Сохраняем цену с учетом скидки
                ]);
            }
            
            $pdo->commit();
            unset($_SESSION['cart']);
            $_SESSION['success'] = "Заказ успешно оформлен! Номер вашего заказа: #".$order_id;
            header("Location: cart.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Ошибка при оформлении заказа: ".$e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <!-- Уведомления -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Ваша корзина</h2>
            
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="alert alert-info">Ваша корзина пуста</div>
                <a href="index.php" class="btn btn-primary">Вернуться к покупкам</a>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Количество</th>
                                <th>Скидка</th>
                                <th>Итого</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = 0;
                            $total_discount = 0;
                            foreach ($_SESSION['cart'] as $product_id => $quantity): 
                                // Получаем информацию о товаре и акциях
                                $stmt = $pdo->prepare("
                                    SELECT p.*, 
                                    (SELECT MAX(discount) FROM promotions pr 
                                    JOIN product_promotions pp ON pr.id = pp.promotion_id 
                                    WHERE pp.product_id = p.id AND NOW() BETWEEN pr.start_date AND pr.end_date) as discount
                                    FROM products p WHERE p.id = ?
                                ");
                                $stmt->execute([$product_id]);
                                $product = $stmt->fetch();
                                
                                $price = $product['price'];
                                $discount = $product['discount'] ?? 0;
                                $discount_amount = $price * ($discount / 100);
                                $final_price = $price - $discount_amount;
                                $item_total = $final_price * $quantity;
                                
                                $total += $item_total;
                                $total_discount += $discount_amount * $quantity;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= number_format($price, 2, '.', ' ') ?> руб.</td>
                                    <td><?= $quantity ?></td>
                                    <td>
                                        <?php if ($discount > 0): ?>
                                            <span class="badge bg-success">-<?= $discount ?>%</span>
                                            (<?= number_format($discount_amount, 2, '.', ' ') ?> руб.)
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($item_total, 2, '.', ' ') ?> руб.</td>
                                    <td>
                                        <a href="cart.php?remove=<?= $product_id ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Удалить товар из корзины?')">
                                            Удалить
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Общая скидка:</th>
                                <th class="text-success">-<?= number_format($total_discount, 2, '.', ' ') ?> руб.</th>
                                <th><?= number_format($total, 2, '.', ' ') ?> руб.</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-outline-primary">Продолжить покупки</a>
                        <form method="POST">
                            <button type="submit" name="checkout" class="btn btn-success btn-lg">
                                <i class="bi bi-cart-check"></i> Оформить заказ
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>