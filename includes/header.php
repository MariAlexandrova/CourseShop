<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru" class="h-100">
<head>
  <meta charset="UTF-8">
  <title>Интернет-магазин</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column h-100">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="index.php">Магазин</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link active" href="index.php">Каталог</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="about.php">О нас</a>
          </li>
        </ul>
        <div class="d-flex">
          <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin'): ?>
              <li class="nav-item">
                  <a href="admin.php" class="nav-link text-danger">Админка</a>
              </li>
          <?php endif; ?>
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="cart.php" class="btn btn-light me-2">Корзина</a>
            <a href="logout.php" class="btn btn-danger">Выйти</a>
          <?php else: ?>
            <a href="login.php" class="btn btn-light me-2">Войти</a>
            <a href="register.php" class="btn btn-primary">Регистрация</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
  <main class="flex-shrink-0">
    <div class="container mt-4"></div>