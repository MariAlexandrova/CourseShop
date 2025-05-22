<?php
require_once './includes/config.php';
require_once './includes/header.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Настройки загрузки изображений
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/';
$base_url = '/images/';

// Создаем папку если не существует
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    file_put_contents($upload_dir . '.htaccess', "Options -Indexes\nDeny from all");
}

// Обработка добавления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        // Валидация данных
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $quantity = (int)$_POST['quantity']; // ОСТАТОК

        if (empty($name)) throw new Exception("Название товара обязательно!");
        if ($price <= 0) throw new Exception("Цена должна быть больше 0!");
        if ($quantity < 0) throw new Exception("Количество не может быть отрицательным!");
        
        // Обработка изображения
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Необходимо загрузить изображение товара!");
        }

        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        
        if (!array_key_exists($mime_type, $allowed_types)) {
            throw new Exception("Допустимы только JPG/PNG изображения!");
        }

        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            throw new Exception("Максимальный размер файла - 2MB!");
        }

        // Генерация уникального имени
        $ext = $allowed_types[$mime_type];
        $filename = uniqid('product_') . '.' . $ext;
        $destination = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            throw new Exception("Ошибка сохранения файла!");
        }

        // Начинаем транзакцию
        $pdo->beginTransaction();

        // Сохранение в БД
        // ТОВАРА
        $stmt = $pdo->prepare("
            INSERT INTO products (name, price, description, image, category_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $price, $description, $filename, $category_id]);
        $product_id = $pdo->lastInsertId();
        // КОЛИЧЕСТВА
        $stmt = $pdo->prepare("
            INSERT INTO product_stock (product_id, quantity) 
            VALUES (?, ?)
        ");
        $stmt->execute([$product_id, $quantity]);
        // Привязка акций
        if (!empty($_POST['promotions'])) {
            foreach ($_POST['promotions'] as $promotion_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_promotions (product_id, promotion_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$product_id, (int)$promotion_id]);
            }
        }
        $pdo->commit();
        $_SESSION['success'] = "Товар успешно добавлен!";
        header("Location: admin.php?section=products");
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isset($filename) && file_exists($destination)) {
            unlink($destination);
        }
        $error = $e->getMessage();
    }
}
// Обработка удаления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        
        // Начинаем транзакцию
        $pdo->beginTransaction();
        
        // 1. Получаем информацию о товаре
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Товар не найден");
        }

        // 2. Удаляем изображение
        if (!empty($product['image']) && file_exists($upload_dir . $product['image'])) {
            unlink($upload_dir . $product['image']);
        }

        // 3. Удаляем записи из связанных таблиц
        $pdo->prepare("DELETE FROM product_promotions WHERE product_id = ?")->execute([$product_id]);
        $pdo->prepare("DELETE FROM product_stock WHERE product_id = ?")->execute([$product_id]);
        $pdo->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$product_id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);

        $pdo->commit();
        $_SESSION['success'] = "Товар и его изображение успешно удалены!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Ошибка удаления: " . $e->getMessage();
    }
    
    header("Location: admin.php?section=products");
    exit;
}

// Полный запрос для получения товаров с остатками и акциями
$products = $pdo->query("
    SELECT 
        p.*, 
        ps.quantity,
        c.name as category_name,
        GROUP_CONCAT(DISTINCT pm.name SEPARATOR ', ') as promotion_names
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_promotions pp ON p.id = pp.product_id
    LEFT JOIN promotions pm ON pp.promotion_id = pm.id
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll();

// Получаем активные акции для формы добавления
$active_promotions = $pdo->query("
    SELECT * FROM promotions 
    WHERE start_date <= NOW() AND end_date >= NOW()
    ORDER BY name
")->fetchAll();
// Получаем все категории для формы
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<div class="card shadow">
    <div class="card-body">
        <!-- Основное содержимое -->
         <div class="mb-4">
            <h1 class="h2">Управление товарами</h1>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-lg"></i> Добавить товар
            </button>
        </div>
            <!-- Уведомления -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Изображение</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Остаток</th>
                            <th>Акции</th>
                            <th>Категория</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">Товары не найдены</td>
                            </tr>
                        <?php else: ?>
                            <?php $i=0; foreach ($products as $product): ?>
                                <tr>
                                    <td><?= $i + 1; ?></td>
                                    <?php $i++?>
                                    <td>
                                        <img src="<?= $base_url . htmlspecialchars($product['image']) ?>" 
                                            class="img-thumbnail" 
                                            style="width: 60px; height: 60px; object-fit: cover;" 
                                            onerror="this.src='/images/no-image.jpg'">
                                    </td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= number_format($product['price'], 2, '.', ' ') ?> ₽</td>
                                    <td>
                                        <span class="<?= $product['quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $product['quantity'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $product['promotion_names'] ?: '—' ?>
                                    </td>
                                    <td><?= $product['category_name'] ?? '—' ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="edit_product.php?id=<?= $product['id'] ?>" 
                                            class="btn btn-sm btn-warning" title="Редактировать">
                                                <i class="bi bi-pencil"></i> Изменить
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                <button type="submit" name="delete_product" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Удалить товар и изображение?')"
                                                        title="Удалить">
                                                    <i class="bi bi-trash-fill"></i> Удалить
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

    </div>
</div>

<!-- Модальное окно добавления товара -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Добавление товара</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Название *</label>
                                <input type="text" name="name" class="form-control" required>
                                <div class="invalid-feedback">Укажите название товара</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Цена *</label>
                                <div class="input-group">
                                    <input type="number" name="price" step="0.01" min="0.01" 
                                           class="form-control" required>
                                    <span class="input-group-text">₽</span>
                                    <div class="invalid-feedback">Укажите корректную цену</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                    <label class="form-label">Количество на складе *</label>
                                    <input type="number" name="quantity" min="0" 
                                           class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <textarea name="description" class="form-control" rows="4"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Категория</label>
                                <select name="category_id" class="form-select">
                                    <option value="">Без категории</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                    <label class="form-label">Акции</label>
                                    <?php if (!empty($active_promotions)): ?>
                                        <div class="border p-2" style="max-height: 150px; overflow-y: auto;">
                                            <?php foreach ($active_promotions as $promo): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="promotions[]" value="<?= $promo['id'] ?>">
                                                    <label class="form-check-label">
                                                        <?= htmlspecialchars($promo['name']) ?> 
                                                        (<?= $promo['discount'] ?>%)
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info py-2">Нет активных акций</div>
                                    <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-3">
                                        <label class="form-label">Изображение *</label>
                                        <input type="file" name="image" class="form-control" required
                                               accept="image/jpeg, image/png">
                                        <div class="form-text">
                                            JPG/PNG, не более 2MB
                                        </div>
                                    </div>
                                    
                                    <div class="mt-auto text-center">
                                        <img id="imagePreview" src="/images/no-image.jpg" 
                                             class="img-fluid rounded mb-2"
                                             style="max-height: 180px; width: auto;">
                                        <div class="text-muted small">Предпросмотр</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Превью изображения
document.getElementById('addProductModal').addEventListener('shown.bs.modal', function() {
    const fileInput = document.querySelector('#addProductModal input[name="image"]');
    const preview = document.getElementById('imagePreview');
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
});

// Валидация формы
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once './includes/footer.php'; ?>