<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'];
  $password = $_POST['password'];

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];

  $_SESSION['user_role'] = $user['role'];  // Добавляем роль в сессию
    header("Location: index.php");
  } else {
    echo "<div class='alert alert-danger'>Неверные данные!</div>";
  }
}
?>

<form method="POST" class="col-md-4 mx-auto mt-5">
  <h2>Вход</h2>
  <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
  <input type="password" name="password" class="form-control mb-2" placeholder="Пароль" required>
  <button type="submit" class="btn btn-primary w-100">Войти</button>
</form>

<?php require_once 'includes/footer.php'; ?>