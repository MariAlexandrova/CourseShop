<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

// Обработка добавления/редактирования акции
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $discount = (float)$_POST['discount'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        if (empty($name)) throw new Exception("Название акции обязательно!");
        if ($discount <= 0 || $discount >= 100) throw new Exception("Неверный размер скидки!");
        if (strtotime($start_date) >= strtotime($end_date)) throw new Exception("Неверный период акции!");

        if (isset($_POST['add_promotion'])) {
            $stmt = $pdo->prepare("
                INSERT INTO promotions (name, discount, start_date, end_date)
                VALUES (?, ?, ?, ?)
            ");
            $message = "Акция успешно создана!";
        } else {
            $stmt = $pdo->prepare("
                UPDATE promotions 
                SET name = ?, discount = ?, start_date = ?, end_date = ?
                WHERE id = ?
            ");
            $message = "Акция успешно обновлена!";
        }

        $params = [$name, $discount, $start_date, $end_date];
        if (isset($_POST['edit_promotion'])) {
            $params[] = (int)$_POST['promotion_id'];
        }

        $stmt->execute($params);
        $_SESSION['success'] = $message;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: admin.php?section=promotions");
    exit;
}

// Обработка удаления акции
if (isset($_GET['delete_promotion']) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
    $promotion_id = (int)$_POST['promotion_id'];
    try {
        // Проверяем, нет ли товаров с этой акцией
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_promotions WHERE promotion_id = ?");
        $stmt->execute([$promotion_id]);
        $count = $stmt->fetchColumn();
        // Если нет товаров с этой акцией - удаляем
        $pdo->beginTransaction();
            
        // Можно добавить дополнительные проверки/удаления связанных данных
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$promotion_id]);
            
        $pdo->commit();
        $_SESSION['success'] = "Акция успешно удалена!";
       
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Ошибка при удалении акции: " . $e->getMessage();
    }
    
    header("Location: admin.php?section=promotions");
    exit;
}

// Получаем список всех акций
$promotions = $pdo->query("SELECT * FROM promotions ORDER BY start_date DESC")->fetchAll();
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Управление акциями</h4>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPromotionModal">
                    <i class="bi bi-plus-lg"></i> Добавить акцию
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Скидка</th>
                            <th>Период</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotions as $promo): ?>
                        <tr>
                            <td><?= htmlspecialchars($promo['name']) ?></td>
                            <td><?= $promo['discount'] ?>%</td>
                            <td>
                                <?= date('d.m.Y', strtotime($promo['start_date'])) ?> - 
                                <?= date('d.m.Y', strtotime($promo['end_date'])) ?>
                            </td>
                            <td>
                                <?php 
                                $now = time();
                                $start = strtotime($promo['start_date']);
                                $end = strtotime($promo['end_date']);
                                
                                if ($now < $start) {
                                    echo '<span class="badge bg-info">Скоро</span>';
                                } elseif ($now > $end) {
                                    echo '<span class="badge bg-secondary">Завершена</span>';
                                } else {
                                    echo '<span class="badge bg-success">Активна</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editPromotionModal"
                                            onclick="loadPromotionData(<?= $promo['id'] ?>, '<?= htmlspecialchars($promo['name']) ?>', <?= $promo['discount'] ?>, '<?= $promo['start_date'] ?>', '<?= $promo['end_date'] ?>')">
                                        <i class="bi bi-pencil"></i> Изменить
                                    </button>
                                    <form method="GET" class="d-inline">
                                        <input type="hidden" name="promotion_id" value="<?= $promo['id'] ?>">
                                        <button type="submit" name="delete_promotion" 
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Удалить эту акцию?')">
                                            Удалить
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/promotion_modal.php'; ?>

<script>
function loadPromotionData(id, name, discount, start, end) {
    document.getElementById('edit_promotion_id').value = id;
    document.getElementById('edit_promotion_name').value = name;
    document.getElementById('edit_promotion_discount').value = discount;
    document.getElementById('edit_promotion_start').value = start.split(' ')[0];
    document.getElementById('edit_promotion_end').value = end.split(' ')[0];
}
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>