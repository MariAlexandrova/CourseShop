<?php
require_once './includes/config.php';
require_once './includes/header.php';

// 1. Проверка прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 2. Получаем ID товара с проверкой
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = "Неверный идентификатор товара";
    header("Location: admin.php?section=products");
    exit;
}

$product_id = (int)$_GET['id']; // Приводим к целому числу

// Настройка загрузки фото
$upload_dir=$_SERVER['DOCUMENT_ROOT'] . '/images/';
$base_url='/images/';
// Создаем папку если не существует
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
// Обработка загрузки изображения
function handleImageUpload($current_image, $upload_dir) {
    // Если новое изображение не загружено
    if (empty($_FILES['image']['name'])) {
        return $current_image;
    }

    // Проверяем тип файла
    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $_FILES['image']['tmp_name']);
    
    if (!array_key_exists($mime_type, $allowed_types)) {
        throw new Exception("Допустимы только JPG/PNG изображения");
    }

    // Удаляем старое изображение
    if (!empty($current_image) && file_exists($upload_dir . $current_image)) {
        unlink($upload_dir . $current_image);
    }

    // Генерируем новое имя
    $ext = $allowed_types[$mime_type];
    $new_filename = uniqid('product_') . '.' . $ext;

    // Сохраняем файл
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
        throw new Exception("Ошибка сохранения файла");
    }

    return $new_filename;
}

// Получаем текущие данные товара
$stmt = $pdo->prepare("
    SELECT p.*, ps.quantity 
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = "Товар не найден";
    header("Location: admin.php?section=products");
    exit;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
         // Валидация данных
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $quantity = (int)$_POST['quantity'];
        $description = trim($_POST['description']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        if (empty($name)) throw new Exception("Название не может быть пустым");
        if ($price <= 0) throw new Exception("Цена должна быть больше 0");
        if ($quantity < 0) throw new Exception("Количество не может быть отрицательным");

        $pdo->beginTransaction();

         // Обработка изображения
        $image = handleImageUpload($product['image'], $upload_dir);
        //  Обновляем товар
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, price = ?, description = ?, image = ?, category_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $price, $description, $image, $category_id, $product_id]);
        //$updateCount = $stmt->rowCount(); // Проверяем обновление товара
        // Обновляем остатки
        $stmt = $pdo->prepare("
            INSERT INTO product_stock (product_id, quantity) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE quantity = ?
        ");
        $stmt->execute([$product_id, $quantity, $quantity]);
        // Обновляем акции
        $pdo->prepare("DELETE FROM product_promotions WHERE product_id = ?")->execute([$product_id]);
            
        if (!empty($_POST['promotions'])) {
            foreach ($_POST['promotions'] as $promotion_id) {                    
                $pdo->prepare("INSERT INTO product_promotions VALUES (?, ?)")
                ->execute([$product_id, (int)$promotion_id]);
            }
        }

        
        //if ($updateCount === 0) {throw new Exception("Данные товара не были обновлены");}
        $pdo->commit(); // Успешное завершение
        $_SESSION['success'] = "Товар успешно обновлен!";
        header("Location: admin.php?section=products");
        exit;    
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Ошибка: " . $e->getMessage();
        // Для отладки:
        error_log($error);
        if (isset($new_filename) && file_exists($upload_dir . $new_filename)) {
            unlink($upload_dir . $new_filename);
        }
    }
}
// Получаем данные для формы
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$product_promotions = $pdo->query("
    SELECT promotion_id FROM product_promotions WHERE product_id = $product_id
")->fetchAll(PDO::FETCH_COLUMN);
$active_promotions = $pdo->query("
    SELECT * FROM promotions WHERE start_date <= NOW() AND end_date >= NOW() ORDER BY name
")->fetchAll();

?>
<div class="container mt-4">
    <h1>Редактирование товара</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="product_id" value="<?= $product_id ?>">

        <div class="row">
            <div class="col-md-6">
                <!--NAME-->
                <div class="mb-3">
                    <label class="form-label">Название товара *</label>
                    <input type="text" name="name" class="form-control" 
                        value="<?= htmlspecialchars($product['name']) ?>" required>
                    <div class="invalid-feedback">Пожалуйста, укажите название</div>
                </div>
                <!--PRICE-->
                <div class="mb-3">
                    <label class="form-label">Цена *</label>
                    <div class="input-group">
                        <input type="number" name="price" step="0.01" min="0.01" 
                            class="form-control" 
                            value="<?= htmlspecialchars($product['price']) ?>" required>
                        <span class="input-group-text">₽</span>
                        <div class="invalid-feedback">Укажите корректную цену</div>
                    </div>
                </div>
                <!--QUANTITY-->
                <div class="mb-3">
                    <label class="form-label">Количество на складе *</label>
                    <input type="number" name="quantity" min="0" class="form-control" 
                           value="<?= htmlspecialchars($product['quantity'] ?? 0) ?>" required>
                </div>
                <!--CATEGORY-->
                 <div class="mb-3">
                    <label class="form-label">Категория</label>
                    <select name="category_id" class="form-select">
                        <option value="">Без категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" 
                                <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!--PROMOTIONS-->
                <div class="mb-3">
                    <label class="form-label">Акции</label>
                    <?php if (!empty($active_promotions)): ?>
                        <div class="border p-2" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($active_promotions as $promo): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="promotions[]" value="<?= $promo['id'] ?>"
                                           <?= in_array($promo['id'], $product_promotions) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        <?= htmlspecialchars($promo['name']) ?> (<?= $promo['discount'] ?>%)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info py-2">Нет активных акций</div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="6"><?= 
                        htmlspecialchars($product['description']) 
                    ?></textarea>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Изображение товара</h5>
                        
                        <div class="text-center mb-3">
                            <img id="imagePreview" src="/images/<?= 
                                htmlspecialchars($product['image']) 
                            ?>" class="img-fluid rounded" style="max-height: 200px">
                        </div>
                        
                        <div class="mb-3">
                            <label for="imageUpload" class="form-label">Новое изображение</label>
                            <input type="file" name="image" id="imageUpload" 
                                class="form-control" accept="image/jpeg, image/png">
                            <div class="form-text">
                                Макс. размер: 2MB. Форматы: JPG, PNG
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Сохранить
                            </button>
                            <a href="admin.php?section=products" class="btn btn-secondary">
                                Отмена
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<!-- Скрипт для превью изображения -->
<script>
    document.getElementById('imageUpload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('imagePreview').src = event.target.result;
            }
            reader.readAsDataURL(file);
        }
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