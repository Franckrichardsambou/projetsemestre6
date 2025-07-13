<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getUserRole() {
    return $_SESSION['user']['type_user'] ?? 'guest';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?? 'Accueil' ?></title>
    <link rel="stylesheet" href="<?= CSS_PATH ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="animated-header">
        <div class="container">
            <div class="logo-container">
                <img src="<?= APP_LOGO ?>" alt="Logo" class="logo">

                <h1><?= APP_NAME ?></h1>
            </div>
            <nav class="main-nav">
                <ul>
                    <?php if (isset($_SESSION['user']['type_user'])): ?>
                    <li><a href="<?= BASE_URL ?>/<?= $_SESSION['user']['type_user'] ?>/dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                    <li><a href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?= BASE_URL ?>/index.php"><i class="fas fa-home"></i> Accueil</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/login.php"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
