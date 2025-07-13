<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Vérifier le rôle et la connexion
check_role('enseignant');

// Récupérer les informations de l'enseignant
$user_id = $_SESSION['user']['id'];
$page_title = "Tableau de Bord Enseignant";

try {
    require_once '../config/db.php';
    
    // Récupérer les informations de l'enseignant
    $stmt = $pdo->prepare("SELECT e.*, u.nom, u.prenom, u.email 
                         FROM enseignants e
                         JOIN utilisateurs u ON e.utilisateur_id = u.id
                         WHERE e.utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enseignant) {
        $_SESSION['error'] = "Impossible de trouver les informations de l'enseignant.";
        header("Location: ../logout.php");
        exit;
    }

    // Statistiques pour le dashboard
    // Nombre de matières enseignées
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT matiere_id) FROM emplois_du_temps WHERE enseignant_id = ?");
    $stmt->execute([$enseignant['id']]);
    $matieres_count = $stmt->fetchColumn();

    // Nombre de classes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT classe_id) FROM emplois_du_temps WHERE enseignant_id = ?");
    $stmt->execute([$enseignant['id']]);
    $classes_count = $stmt->fetchColumn();

    // Cours déposés
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cours WHERE enseignant_id = ?");
    $stmt->execute([$enseignant['id']]);
    $cours_count = $stmt->fetchColumn();

    // Prochains cours
    $stmt = $pdo->prepare("SELECT edt.*, m.nom as matiere_nom, c.nom as classe_nom 
                          FROM emplois_du_temps edt
                          JOIN matieres m ON edt.matiere_id = m.id
                          JOIN classes c ON edt.classe_id = c.id
                          WHERE edt.enseignant_id = ? 
                          AND edt.jour_semaine = ?
                          AND edt.heure_debut > ?
                          ORDER BY edt.heure_debut ASC
                          LIMIT 3");
    $today = date('l'); // jour actuel (Monday, Tuesday...)
    $current_time = date('H:i:s');
    $stmt->execute([$enseignant['id'], $today, $current_time]);
    $prochains_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de récupération des données : " . $e->getMessage();
}


?>

<div class="teacher-container">
    <!-- Sidebar -->
    <div class="teacher-sidebar">
        <div class="sidebar-header">
            <div class="teacher-avatar">
                <img src="<?= IMG_PATH ?>teacher-avatar.png" alt="<?= $enseignant['prenom'] ?> <?= $enseignant['nom'] ?>">
            </div>
            <div class="teacher-info">
                <h3>Prof. <?= $enseignant['prenom'] ?> <?= $enseignant['nom'] ?></h3>
                <p><?= $enseignant['email'] ?></p>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <ul>
                <li class="active">
                    <a href="<?= BASE_URL ?>/enseignant/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/enseignant/notes.php">
                        <i class="fas fa-edit"></i>
                        <span>Saisie des notes</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/enseignant/cours.php">
                        <i class="fas fa-book"></i>
                        <span>Gestion des cours</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/enseignant/emploi.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Emploi du temps</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/enseignant/absences.php">
                        <i class="fas fa-user-times"></i>
                        <span>Gestion des absences</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-chart-bar"></i>
                        <span>Statistiques</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-cog"></i>
                        <span>Paramètres</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="calendar-widget">
                <div class="calendar-header">
                    <span><?= date('F Y') ?></span>
                </div>
                <div class="calendar-day"><?= date('d') ?></div>
                <div class="calendar-weekday"><?= date('l') ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="teacher-main">
        <div class="teacher-dashboard">
            <!-- Section Bienvenue -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h1 class="welcome-title">Bienvenue, <span>Prof. <?= $enseignant['prenom'] ?></span> !</h1>
                    <p class="welcome-text">Voici votre tableau de bord personnel</p>
                </div>
                <div class="welcome-image">
                    <img src="<?= IMG_PATH ?>teacher-dashboard.svg" alt="Dashboard Illustration">
                </div>
            </div>
            
            <!-- Section Statistiques -->
            <div class="stats-section">
                <div class="stats-grid">
                    <!-- Carte 1 - Matières -->
                    <div class="stat-card card-1">
                        <div class="card-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="counter" data-target="<?= $matieres_count ?>">0</h3>
                            <p>Matières enseignées</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 2 - Classes -->
                    <div class="stat-card card-2">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="counter" data-target="<?= $classes_count ?>">0</h3>
                            <p>Classes</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 3 - Cours déposés -->
                    <div class="stat-card card-3">
                        <div class="card-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="counter" data-target="<?= $cours_count ?>">0</h3>
                            <p>Cours déposés</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 4 - Prochain cours -->
                    <div class="stat-card card-4">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3>
                                <?php if (!empty($prochains_cours)): ?>
                                    <?= date('H:i', strtotime($prochains_cours[0]['heure_debut'])) ?>
                                <?php else: ?>
                                    Aucun
                                <?php endif; ?>
                            </h3>
                            <p>Prochain cours</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                </div>
            </div>

            <!-- Section Prochains Cours -->
            <div class="courses-section">
                <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Prochains Cours</h2>
                
                <?php if (!empty($prochains_cours)): ?>
                    <div class="courses-grid">
                        <?php foreach ($prochains_cours as $cours): ?>
                            <div class="course-card">
                                <div class="course-time">
                                    <?= date('H:i', strtotime($cours['heure_debut'])) ?> - <?= date('H:i', strtotime($cours['heure_fin'])) ?>
                                </div>
                                <div class="course-content">
                                    <h3><?= $cours['matiere_nom'] ?></h3>
                                    <p>Classe: <?= $cours['classe_nom'] ?></p>
                                    <p>Salle: <?= $cours['salle'] ?? 'Non spécifiée' ?></p>
                                </div>
                                <div class="course-icon">
                                    <i class="fas fa-chalkboard"></i>
                                </div>
                                <div class="course-wave"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-courses">
                        <i class="fas fa-smile-beam"></i>
                        <p>Aucun cours prévu pour aujourd'hui</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section Actions rapides -->
            <div class="quick-actions-section">
                <h2 class="section-title"><i class="fas fa-bolt"></i> Actions rapides</h2>
                
                <div class="actions-grid">
                    <a href="<?= BASE_URL ?>/enseignant/notes.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3>Saisir des notes</h3>
                        <p>Enregistrer les notes des étudiants</p>
                    </a>
                    
                    <a href="<?= BASE_URL ?>/enseignant/cours.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-upload"></i>
                        </div>
                        <h3>Déposer un cours</h3>
                        <p>Partager un document avec vos étudiants</p>
                    </a>
                    
                    <a href="<?= BASE_URL ?>/enseignant/absences.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <h3>Enregistrer une absence</h3>
                        <p>Marquer un étudiant comme absent</p>
                    </a>
                    
                    <a href="<?= BASE_URL ?>/enseignant/emploi.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>Voir l'emploi du temps</h3>
                        <p>Consulter votre planning</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles spécifiques au dashboard enseignant -->
<style>
    /* Structure principale */
    .teacher-container {
        display: flex;
        min-height: 100vh;
        background: #f5f7fa;
    }
    
    /* Sidebar */
    .teacher-sidebar {
        width: 280px;
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        position: fixed;
        height: 100vh;
        z-index: 100;
    }
    
    .sidebar-header {
        padding: 30px 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .teacher-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin: 0 auto 15px;
        border: 4px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
        animation: pulse 2s infinite;
    }
    
    .teacher-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .teacher-info h3 {
        font-size: 1.2rem;
        margin-bottom: 5px;
    }
    
    .teacher-info p {
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .sidebar-menu {
        flex: 1;
        padding: 20px 0;
        overflow-y: auto;
    }
    
    .sidebar-menu ul {
        list-style: none;
    }
    
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s;
        position: relative;
    }
    
    .sidebar-menu li a:hover,
    .sidebar-menu li.active a {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .sidebar-menu li.active a::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: #3498db;
    }
    
    .sidebar-menu li a i {
        width: 24px;
        text-align: center;
        margin-right: 10px;
        font-size: 1.1rem;
    }
    
    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .calendar-widget {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
    }
    
    .calendar-header {
        margin-bottom: 10px;
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    .calendar-day {
        font-size: 2.5rem;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .calendar-weekday {
        font-size: 1rem;
        opacity: 0.8;
    }
    
    /* Contenu principal */
    .teacher-main {
        flex: 1;
        margin-left: 280px;
        padding: 20px;
    }
    
    /* Dashboard Enseignant */
    .teacher-dashboard {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* Section Bienvenue */
    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #3498db, #2ecc71);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
    }
    
    .welcome-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        transform: rotate(30deg);
    }
    
    .welcome-title {
        font-size: 2.2rem;
        margin-bottom: 10px;
        position: relative;
        z-index: 2;
    }
    
    .welcome-title span {
        color: #f1c40f;
    }
    
    .welcome-text {
        font-size: 1.1rem;
        opacity: 0.9;
        position: relative;
        z-index: 2;
    }
    
    .welcome-image {
        width: 200px;
        position: relative;
        z-index: 2;
        animation: float 6s ease-in-out infinite;
    }
    
    .welcome-image img {
        width: 100%;
        height: auto;
    }
    
    /* Section Statistiques */
    .stats-section {
        margin-bottom: 40px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        z-index: 1;
    }
    
    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 100%);
        z-index: -1;
    }
    
    .card-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
    }
    
    .card-content h3 {
        font-size: 2.2rem;
        margin-bottom: 5px;
        color: #2c3e50;
    }
    
    .card-content p {
        color: #7f8c8d;
        font-size: 1rem;
    }
    
    .card-light {
        position: absolute;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        filter: blur(50px);
        opacity: 0.7;
        z-index: -1;
        transition: all 0.5s ease;
    }
    
    .stat-card:hover .card-light {
        transform: scale(1.5);
        opacity: 0.9;
    }
    
    /* Couleurs des cartes */
    .card-1 {
        border-left: 5px solid #3498db;
    }
    
    .card-1 .card-icon {
        color: #3498db;
    }
    
    .card-1 .card-light {
        background: #3498db;
        top: -30px;
        right: -30px;
    }
    
    .card-2 {
        border-left: 5px solid #9b59b6;
    }
    
    .card-2 .card-icon {
        color: #9b59b6;
    }
    
    .card-2 .card-light {
        background: #9b59b6;
        bottom: -30px;
        left: -30px;
    }
    
    .card-3 {
        border-left: 5px solid #2ecc71;
    }
    
    .card-3 .card-icon {
        color: #2ecc71;
    }
    
    .card-3 .card-light {
        background: #2ecc71;
        top: -30px;
        left: -30px;
    }
    
    .card-4 {
        border-left: 5px solid #f39c12;
    }
    
    .card-4 .card-icon {
        color: #f39c12;
    }
    
    .card-4 .card-light {
        background: #f39c12;
        bottom: -30px;
        right: -30px;
    }
    
    /* Section Cours */
    .section-title {
        font-size: 1.8rem;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .courses-section {
        margin-bottom: 40px;
    }
    
    .courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .course-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .course-time {
        background: #3498db;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        font-weight: bold;
        min-width: 100px;
        text-align: center;
    }
    
    .course-content {
        flex: 1;
    }
    
    .course-content h3 {
        font-size: 1.2rem;
        margin-bottom: 5px;
        color: #2c3e50;
    }
    
    .course-content p {
        color: #7f8c8d;
        font-size: 0.9rem;
    }
    
    .course-icon {
        font-size: 1.5rem;
        color: #3498db;
    }
    
    .course-wave {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, #3498db, #2ecc71);
    }
    
    /* Section Actions rapides */
    .quick-actions-section {
        margin-bottom: 40px;
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .action-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        color: #2c3e50;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .action-icon {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #3498db;
    }
    
    .action-card h3 {
        font-size: 1.3rem;
        margin-bottom: 10px;
    }
    
    .action-card p {
        color: #7f8c8d;
        font-size: 0.9rem;
    }
    
    /* Aucun cours */
    .no-courses {
        background: white;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .no-courses i {
        font-size: 3rem;
        color: #bdc3c7;
        margin-bottom: 15px;
    }
    
    .no-courses p {
        color: #7f8c8d;
        font-size: 1.1rem;
    }
    
    /* Animations */
    @keyframes float {
        0% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
        100% {
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .teacher-sidebar {
            width: 250px;
        }
        
        .teacher-main {
            margin-left: 250px;
        }
        
        .welcome-section {
            flex-direction: column;
            text-align: center;
        }
        
        .welcome-image {
            margin-top: 20px;
        }
    }
    
    @media (max-width: 768px) {
        .teacher-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
        }
        
        .teacher-sidebar.active {
            transform: translateX(0);
        }
        
        .teacher-main {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar-toggle {
            display: block;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #3498db;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .actions-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Scripts pour les animations -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des compteurs
    const counters = document.querySelectorAll('.counter');
    const speed = 200;
    
    function animateCounters() {
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            
            if (isNaN(target)) return;
            
            const increment = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(animateCounters, 1);
            } else {
                counter.innerText = target;
            }
        });
    }
    
    // Lancer l'animation quand la section est visible
    const statsSection = document.querySelector('.stats-section');
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            animateCounters();
            observer.unobserve(statsSection);
        }
    });
    
    observer.observe(statsSection);
    
    // Effet de lumière sur les cartes au survol
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const light = card.querySelector('.card-light');
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            light.style.left = `${x - 50}px`;
            light.style.top = `${y - 50}px`;
        });
    });
    
    // Animation des cartes au chargement
    const animatedElements = document.querySelectorAll('.stat-card, .course-card, .action-card');
    
    function checkAnimation() {
        animatedElements.forEach((element, index) => {
            const elementPosition = element.getBoundingClientRect().top;
            const screenPosition = window.innerHeight / 1.2;
            
            if (elementPosition < screenPosition) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }
    
    // Initialiser les éléments comme invisibles
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.6s ease';
    });
    
    window.addEventListener('scroll', checkAnimation);
    checkAnimation(); // Vérifier au chargement
    
    // Toggle sidebar mobile
    const sidebarToggle = document.createElement('div');
    sidebarToggle.className = 'sidebar-toggle';
    sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.body.appendChild(sidebarToggle);
    
    sidebarToggle.addEventListener('click', function() {
        document.querySelector('.teacher-sidebar').classList.toggle('active');
    });
    
    // Fermer le sidebar quand on clique à l'extérieur
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.teacher-sidebar');
        if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
            sidebar.classList.remove('active');
        }
    });
});
</script>

