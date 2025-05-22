<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
?>

<div class="container mt-4">
    <h1 class="mb-4">Административная панель</h1>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <div class="row">
        <!-- Навигация -->
        <div class="col-md-3">
            <div class="list-group">
                <a href="admin.php?section=products" class="list-group-item list-group-item-action">Товары</a>
                <a href="admin.php?section=categories" class="list-group-item list-group-item-action">Категории</a>
                <a href="admin.php?section=promotions" class="list-group-item list-group-item-action">Акции</a>
                <a href="admin.php?section=orders" class="list-group-item list-group-item-action">Заказы</a>
                <a href="admin.php?section=users" class="list-group-item list-group-item-action">Пользователи</a>
                
            </div>
        </div>
        <!-- Контент -->
        <div class="col-md-9">
            <?php
            $section = $_GET['section'] ?? 'products';
            switch ($section) {
                case 'products':
                    include 'admin_sections/products.php';
                    break;
                case 'orders':
                    include 'admin_sections/orders.php';
                    break;
                case 'users':
                    include 'admin_sections/users.php';
                    break;
                case 'categories':
                    include 'admin_sections/categories.php';
                    break;
                case 'promotions':
                    include 'admin_sections/promotions.php';
                    break;
            }
            ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>