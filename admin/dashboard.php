<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');
$page_title = "Tableau de bord Admin";

// Statistiques
$etudiants_count = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$enseignants_count = $pdo->query("SELECT COUNT(*) FROM enseignants")->fetchColumn();
$classes_count = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$absences_count = $pdo->query("SELECT COUNT(*) FROM absences WHERE MONTH(date) = MONTH(CURRENT_DATE())")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f0f4f8;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* HEADER */
        header {
            background: linear-gradient(90deg, #0d6efd, #6610f2);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .logo-header {
            display: flex;
            align-items: center;
        }
        .logo-header img {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            border-radius: 50%;
        }
        .logo-header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
            margin: 0;
        }
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        nav ul li a:hover {
            text-decoration: underline;
        }

        /* FOOTER */
        footer {
            background: #0d6efd;
            color: white;
            padding: 30px 0;
            text-align: center;
            margin-top: 40px;
        }
        footer .social-icons a {
            color: white;
            margin: 0 10px;
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        footer .social-icons a:hover {
            transform: scale(1.2);
        }
        footer p {
            margin-top: 10px;
            font-size: 14px;
        }

        /* DASHBOARD STYLE (déjà dans ton code) */
        .dashboard-container {
            display: flex;
        }
        .sidebar {
            width: 250px;
            background: #0d6efd;
            color: #fff;
            min-height: 100vh;
            padding: 20px;
            position: fixed;
        }
        .sidebar h2 {
            margin-bottom: 30px;
            font-size: 22px;
            text-align: center;
            border-bottom: 2px solid #fff;
            padding-bottom: 10px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin: 15px 0;
        }
        .sidebar ul li a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .sidebar ul li a i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 270px;
            padding: 40px;
            width: 100%;
        }
        .cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .card {
            flex: 1 1 200px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card i {
            font-size: 30px;
            margin-bottom: 10px;
        }
        .bg-blue { background: #007bff; color: #fff; }
        .bg-green { background: #28a745; color: #fff; }
        .bg-orange { background: #fd7e14; color: #fff; }
        .bg-red { background: #dc3545; color: #fff; }
        .section {
            margin-top: 40px;
        }
        .section h2 {
            margin-bottom: 15px;
        }
        .alert-list li {
            margin: 5px 0;
        }
        .events {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .event {
            display: flex;
            align-items: center;
            background: #fff;
            border-left: 5px solid #0d6efd;
            padding: 15px;
            border-radius: 8px;
            flex: 1 1 300px;
        }
        .event .date {
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
            text-align: center;
            background: #0d6efd;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
        }
        .event .date span {
            font-size: 14px;
        }
        .event .details h4 {
            margin: 0 0 5px;
        }
    </style>
</head>
<body>

<header>
    <div class="logo-header">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/No_image_available.svg/480px-No_image_available.svg.png" alt="Logo">
        <h1><?= APP_NAME ?></h1>
    </div>
    <nav>
        <ul>
            <li><a href="#"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="#"><i class="fas fa-user-cog"></i> Profil</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </nav>
</header>

<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Admin</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="etudiants.php"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="enseignants.php"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a></li>
            <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="matieres.php"><i class="fas fa-book"></i> Matières</a></li>
            <li><a href="emploi.php"><i class="fas fa-calendar-alt"></i> Emplois du temps</a></li>
            <li><a href="absences.php"><i class="fas fa-calendar-times"></i> Absences</a></li>
            <li><a href="notes.php"><i class="fas fa-clipboard-list"></i> Notes</a></li>
            <li><a href="deliberations.php"><i class="fas fa-gavel"></i> Délibérations</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h1>Bienvenue sur le Tableau de bord</h1>

        <div class="cards">
            <div class="card bg-blue">
                <i class="fas fa-user-graduate"></i>
                <h3><?= $etudiants_count ?></h3>
                <p>Étudiants</p>
            </div>
            <div class="card bg-green">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3><?= $enseignants_count ?></h3>
                <p>Enseignants</p>
            </div>
            <div class="card bg-orange">
                <i class="fas fa-school"></i>
                <h3><?= $classes_count ?></h3>
                <p>Classes</p>
            </div>
            <div class="card bg-red">
                <i class="fas fa-calendar-times"></i>
                <h3><?= $absences_count ?></h3>
                <p>Absences ce mois</p>
            </div>
        </div>

        <div class="dashboard-sections">
            <div class="section">
                <h2><i class="fas fa-bell"></i> Alertes récentes</h2>
                <ul class="alert-list">
                    <li><i class="fas fa-exclamation-circle text-danger"></i> 5 absences non justifiées</li>
                    <li><i class="fas fa-info-circle text-info"></i> Réunion demain à 10h</li>
                </ul>
            </div>
            <div class="section">
                <h2><i class="fas fa-calendar-alt"></i> Événements à venir</h2>
                <div class="events">
                    <div class="event">
                        <div class="date">15<br><span>Juin</span></div>
                        <div class="details">
                            <h4>Conseil de classe</h4>
                            <p>Salle 12 - Terminale A</p>
                        </div>
                    </div>
                    <div class="event">
                        <div class="date">20<br><span>Juin</span></div>
                        <div class="details">
                            <h4>Examens finaux</h4>
                            <p>Toutes les classes</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<footer>
    <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-linkedin-in"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
    </div>
    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> - Tous droits réservés.</p>
</footer>

</body>
</html>
