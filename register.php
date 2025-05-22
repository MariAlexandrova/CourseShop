<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  // Проверка уникальности email
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  
  if ($stmt->rowCount() > 0) {
    $error = 'Этот email уже зарегистрирован!';
  } else {
    // Хеширование пароля
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Сохранение в БД
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $hashed_password]);
    
    header("Location: login.php?success=1");
    exit;
  }
}
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow">
        <div class="card-body">
          <h2 class="card-title text-center mb-4">Регистрация</h2>
          
          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Имя</label>
              <input type="text" name="name" class="form-control" required 
                     value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required 
                     value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Пароль</label>
              <input type="password" name="password" class="form-control" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary w-100">Зарегистрироваться</button>
          </form>

          <div class="mt-3 text-center">
            Уже есть аккаунт? <a href="login.php">Войти</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>