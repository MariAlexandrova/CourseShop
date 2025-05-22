<?php
// Добавление категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        header("Refresh:0"); // Обновляем страницу
    }
}

// Удаление категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = (int)$_POST['category_id'];
    
    // Проверяем, нет ли товаров в категории
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
    } else {
        $_SESSION['error'] = "Нельзя удалить категорию с товарами!";
    }
    
    header("Refresh:0");
}
?>

<div class="card shadow">
    <div class="card-body">
        <h4 class="mb-4">Управление категориями</h4>
        
        <!-- Форма добавления -->
        <form method="POST" class="mb-4">
            <div class="input-group">
                <input type="text" name="category_name" class="form-control" 
                       placeholder="Новая категория" required>
                <button type="submit" name="add_category" class="btn btn-success">
                    Добавить
                </button>
            </div>
        </form>
        
        <!-- Список категорий -->
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
                foreach ($categories as $cat):
                ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td>
                        <a href="edit_category.php?id=<?= $cat['id'] ?>" 
                           class="btn btn-sm btn-warning">Изменить</a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <button type="submit" name="delete_category" 
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Удалить категорию?')">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>