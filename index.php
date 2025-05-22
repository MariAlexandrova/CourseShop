<?php

require_once 'includes/config.php';
require_once 'includes/header.php';

// Получаем параметры
$sort = $_GET['sort'] ?? 'newest';
$category_id = $_GET['category'] ?? null;
$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;

// Базовый запрос с защитой от SQL-инъекций
$sql = "SELECT p.*, ps.quantity, c.name as category_name,
               MAX(pm.discount) as max_discount
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        LEFT JOIN product_promotions pp ON p.id = pp.product_id
        LEFT JOIN promotions pm ON pp.promotion_id = pm.id AND 
                                 pm.start_date <= NOW() AND 
                                 pm.end_date >= NOW()
        WHERE 1=1";
$params = [];

// Фильтр по категории
if ($category_id && ctype_digit($category_id)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

// Фильтр по цене
if (is_numeric($min_price)) {
    $sql .= " AND p.price >= ?";
    $params[] = $min_price;
}
if (is_numeric($max_price)) {
    $sql .= " AND p.price <= ?";
    $params[] = $max_price;
}
// Группировка и сортировка
$sql .= " GROUP BY p.id";
// Сортировка
$sort_options = [
    'newest' => 'p.id DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC'
];
$sort_order = $sort_options[$sort] ?? $sort_options['newest'];
$sql .= " ORDER BY " . $sort_order;

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Получаем список категорий для фильтра
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<div class="container mt-4">
    <!-- Форма фильтрации и сортировки -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Фильтры и сортировка</h5>
            <form method="get" class="row g-3">
                <!-- Сортировка -->
                <div class="col-md-3">
                    <label for="sort" class="form-label">Сортировка</label>
                    <select name="sort" id="sort" class="form-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Новые сначала</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Цена (по возрастанию)</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Цена (по убыванию)</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Название (А-Я)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Название (Я-А)</option>
                    </select>
                </div>
                
                <!-- Фильтр по категории -->
                <div class="col-md-3">
                    <label for="category" class="form-label">Категория</label>
                    <select name="category" id="category" class="form-select">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" 
                                <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Фильтр по цене -->
                <div class="col-md-3">
                    <label for="min_price" class="form-label">Цена от</label>
                    <input type="number" name="min_price" id="min_price" 
                           class="form-control" placeholder="Руб." 
                           value="<?= htmlspecialchars($min_price) ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="max_price" class="form-label">Цена до</label>
                    <input type="number" name="max_price" id="max_price" 
                           class="form-control" placeholder="Руб." 
                           value="<?= htmlspecialchars($max_price) ?>">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        Применить
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        Сбросить
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Список товаров -->
    <div class="row">
        <?php if (empty($products)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    Товары не найдены. Попробуйте изменить параметры фильтрации.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <?php
                // Рассчитываем цену с учетом скидки
                $original_price = $product['price'];
                $discount = $product['max_discount'] ?? 0;
                $final_price = $discount > 0 ? $original_price * (1 - $discount / 100) : $original_price;
                $quantity = $product['quantity'] ?? 0;
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <!-- Бейдж категории -->
                        <?php if ($product['category_name']): ?>
                            <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </span>
                        <?php endif; ?>
                        <!-- Бейдж акции -->
                        <?php if ($discount > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                                -<?= $discount ?>%
                            </span>
                        <?php endif; ?>
                        <!-- Бейдж количества 
                        <span class="badge bg-<?= $quantity > 0 ? 'success' : 'secondary' ?> position-absolute top-0 start-50 translate-middle-x m-2">
                            <?= $quantity > 0 ? "В наличии: $quantity шт." : 'Нет в наличии' ?>
                        </span>-->
                        <!-- Изображение товара -->
                        <img src="/images/<?= htmlspecialchars($product['image']) ?>" 
                             class="card-img-top" 
                             style="height: 200px; object-fit: cover;" 
                             alt="<?= htmlspecialchars($product['name']) ?>">
                        
                        <div class="mt-auto">
                            <!-- Цена -->
                                <div class="d-flex align-items-center mb-2">
                                    <?php if ($discount > 0): ?>
                                        <span class="text-decoration-line-through text-muted me-2">
                                            <?= number_format($original_price, 2, '.', ' ') ?> ₽
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-success fw-bold fs-5">
                                        <?= number_format($final_price, 2, '.', ' ') ?> ₽
                                    </span>
                                </div>
                                
                                <!-- Кнопка добавления в корзину -->
                                <form action="cart.php" method="POST" class="d-grid">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" name="add_to_cart" 
                                            class="btn btn-primary <?= $quantity <= 0 ? 'disabled' : '' ?>"
                                            <?= $quantity <= 0 ? 'disabled' : '' ?>>
                                        <?= $quantity > 0 ? 'В корзину' : 'Нет в наличии' ?>
                                    </button>
                                </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>