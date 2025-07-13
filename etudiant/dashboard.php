<?php
// On suppose que ces fichiers existent et sont configurés
 require_once '../config/config.php'; 
 require_once '../includes/functions.php';

// === DÉBUT DE LA SIMULATION (pour le test sans session réelle) ===
// !! IMPORTANT: Dans votre application, vous utiliserez votre propre système de session
if (!isset($_SESSION)) { session_start(); }
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = ['id' => 1]; // Simule un utilisateur connecté avec l'ID 1
}
// Vous pouvez commenter/supprimer cette partie quand votre système de login est fonctionnel
// === FIN DE LA SIMULATION ===


// Vérifier le rôle et la connexion (à décommenter quand votre fonction est prête)
// check_role('etudiant');

// Récupérer les informations de l'étudiant connecté
$user_id = $_SESSION['user']['id'];
$page_title = "Tableau de Bord Étudiant";

// --- Début de votre logique PHP (inchangée) ---
try {
    // Connexion à la base de données (adaptez le chemin si nécessaire)
    require_once '../config/db.php'; 
    
    // Récupérer les informations de l'étudiant
    $stmt = $pdo->prepare("SELECT e.*, u.nom, u.prenom, c.nom AS classe_nom, c.niveau AS classe_niveau
                       FROM etudiants e
                       JOIN utilisateurs u ON e.utilisateur_id = u.id
                       JOIN classes c ON e.classe_id = c.id
                       WHERE e.utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etudiant) {
        // Dans une vraie app, gérer cette erreur proprement
        die("Erreur : Impossible de trouver les informations de l'étudiant. Assurez-vous d'avoir des données de test pour l'utilisateur ID 1.");
    }

    // Nombre de cours disponibles
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cours WHERE classe_id = ?");
    $stmt->execute([$etudiant['classe_id']]);
    $cours_count = $stmt->fetchColumn();

    // Nombre d'absences non justifiées
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE etudiant_id = ? AND justifie = FALSE");
    $stmt->execute([$etudiant['id']]);
    $absences_count = $stmt->fetchColumn();

    // Moyenne générale (avec coefficients pour plus de précision)
    $stmt_moyenne = $pdo->prepare("
        SELECT SUM(n.note * m.coefficient) / SUM(m.coefficient) AS moyenne_generale
        FROM notes n
        JOIN matieres m ON n.matiere_id = m.id
        WHERE n.etudiant_id = ?
    ");
    $stmt_moyenne->execute([$etudiant['id']]);
    $moyenne = $stmt_moyenne->fetchColumn();
    $moyenne = $moyenne ? round($moyenne, 2) : 'N/A';

    // Prochains cours (emploi du temps)
    $stmt = $pdo->prepare("SELECT edt.*, m.nom as matiere_nom 
                          FROM emplois_du_temps edt
                          JOIN matieres m ON edt.matiere_id = m.id
                          WHERE edt.classe_id = ? 
                          AND edt.jour_semaine = ? 
                          AND edt.heure_debut > ?
                          ORDER BY edt.heure_debut ASC
                          LIMIT 3");
    // Conversion du jour en anglais (ex: Lundi -> Monday)
    $jours_fr_en = ['Lundi' => 'Monday', 'Mardi' => 'Tuesday', 'Mercredi' => 'Wednesday', 'Jeudi' => 'Thursday', 'Vendredi' => 'Friday', 'Samedi' => 'Saturday', 'Dimanche' => 'Sunday'];
    $today_fr = date('l'); // 'Monday', 'Tuesday'...
    $today_db_format = array_search($today_fr, $jours_fr_en) ?: 'Lundi'; // Récupère le nom FR pour la DB
    $current_time = date('H:i:s');
    $stmt->execute([$etudiant['classe_id'], $today_db_format, $current_time]);
    $prochains_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Derniers cours déposés
    $stmt = $pdo->prepare("SELECT c.*, m.nom as matiere_nom, u.nom as enseignant_nom, u.prenom as enseignant_prenom
                          FROM cours c
                          JOIN matieres m ON c.matiere_id = m.id
                          JOIN enseignants ens ON c.enseignant_id = ens.id
                          JOIN utilisateurs u ON ens.utilisateur_id = u.id
                          WHERE c.classe_id = ?
                          ORDER BY c.date_ajout DESC
                          LIMIT 3");
    $stmt->execute([$etudiant['classe_id']]);
    $derniers_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de récupération des données : " . $e->getMessage());
}


?>

<div class="page-wrapper">
    <!-- ================== NOUVELLE SIDEBAR ================== -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-school-flag"></i> Mon Établissement</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt fa-fw"></i> 
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li>
                    <a href="bulletin.php" class="nav-link">
                        <i class="fas fa-award fa-fw"></i> 
                        <span>Mon Bulletin</span>
                    </a>
                </li>
                <li>
                    <a href="cours.php" class="nav-link">
                        <i class="fas fa-book fa-fw"></i> 
                        <span>Mes Cours</span>
                    </a>
                </li>
                <li>
                    <a href="emploi.php" class="nav-link">
                        <i class="fas fa-calendar-week fa-fw"></i> 
                        <span>Emploi du temps</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <img src="/etablissement/assets/images/student-avatar.png" alt="Avatar">
                <div class="user-info">
                    <span><?= htmlspecialchars($etudiant['prenom']) ?></span>
                    <small><?= htmlspecialchars($etudiant['classe_nom']) ?></small>
                </div>
            </div>
            <a href="/etablissement/auth/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt fa-fw"></i>
            </a>
        </div>
    </aside>

    <!-- ================== CONTENU PRINCIPAL (VOTRE CODE) ================== -->
    <main class="main-content">
        <div class="student-dashboard">
            <!-- Section Bienvenue -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h1 class="welcome-title">Bienvenue, <span><?= htmlspecialchars($etudiant['prenom']) ?></span> !</h1>
                    <p class="welcome-text">Vous êtes en <strong><?= htmlspecialchars($etudiant['classe_niveau']) ?> - <?= htmlspecialchars($etudiant['classe_nom']) ?></strong></p>
                </div>
                <div class="welcome-image">
                    <img src="/etablissement/assets/images/student-avatar.png" alt="Avatar Étudiant" class="animated-avatar">
                </div>
            </div>
            
            <!-- Section Statistiques avec cartes animées -->
            <div class="stats-section">
                <div class="stats-grid">
                    <!-- Carte 1 - Cours disponibles -->
                    <div class="stat-card card-1">
                        <div class="card-icon"><i class="fas fa-book-open"></i></div>
                        <div class="card-content">
                            <h3 class="counter" data-target="<?= $cours_count ?>">0</h3>
                            <p>Cours disponibles</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 2 - Absences -->
                    <div class="stat-card card-2">
                        <div class="card-icon"><i class="fas fa-calendar-times"></i></div>
                        <div class="card-content">
                            <h3 class="counter" data-target="<?= $absences_count ?>">0</h3>
                            <p>Absences non justifiées</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 3 - Moyenne -->
                    <div class="stat-card card-3">
                        <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="card-content">
                            <h3><?= $moyenne ?></h3>
                            <p>Moyenne générale</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                    
                    <!-- Carte 4 - Prochain cours -->
                    <div class="stat-card card-4">
                        <div class="card-icon"><i class="fas fa-clock"></i></div>
                        <div class="card-content">
                            <h3>
                                <?php if (!empty($prochains_cours)): ?>
                                    <?= date('H:i', strtotime($prochains_cours[0]['heure_debut'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </h3>
                            <p>Prochain cours</p>
                        </div>
                        <div class="card-light"></div>
                    </div>
                </div>
            </div>
            
            <!-- Section Grid 2 colonnes -->
            <div class="dashboard-bottom-grid">
                <!-- Section Prochains Cours -->
                <div class="courses-section">
                    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Prochains Cours du Jour</h2>
                    
                    <?php if (!empty($prochains_cours)): ?>
                        <div class="courses-grid">
                            <?php foreach ($prochains_cours as $cours): ?>
                                <div class="course-card">
                                    <div class="course-time">
                                        <?= date('H:i', strtotime($cours['heure_debut'])) ?> - <?= date('H:i', strtotime($cours['heure_fin'])) ?>
                                    </div>
                                    <div class="course-content">
                                        <h3><?= htmlspecialchars($cours['matiere_nom']) ?></h3>
                                    </div>
                                    <div class="course-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                    <div class="course-wave"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data-card">
                            <i class="fas fa-smile-beam"></i>
                            <p>Aucun autre cours prévu pour aujourd'hui. Profitez-en !</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Section Derniers Cours Déposés -->
                <div class="latest-courses-section">
                    <h2 class="section-title"><i class="fas fa-download"></i> Derniers Cours Déposés</h2>
                    
                    <?php if (!empty($derniers_cours)): ?>
                        <div class="courses-list">
                            <?php foreach ($derniers_cours as $cours): ?>
                                <a href="#" class="course-item">
                                    <div class="course-file">
                                        <?php 
                                            $icon = 'fa-file-alt';
                                            if ($cours['type_fichier'] === 'pdf') $icon = 'fa-file-pdf';
                                            elseif ($cours['type_fichier'] === 'word') $icon = 'fa-file-word';
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div class="course-details">
                                        <h3><?= htmlspecialchars($cours['titre']) ?></h3>
                                        <p><?= htmlspecialchars($cours['matiere_nom']) ?> - Par <?= htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']) ?></p>
                                    </div>
                                    <div class="course-actions">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                         <div class="text-center mt-4">
                            <a href="cours.php" class="btn btn-outline-primary">
                                Voir tous les cours <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="no-data-card">
                            <i class="fas fa-book-open"></i>
                            <p>Aucun cours disponible pour le moment</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ================== STYLES CSS (VOS STYLES + NOUVEAUX STYLES) ================== -->
<style>
    /* VARIABLES DE COULEURS */
    :root {
        --sidebar-bg: rgba(15, 23, 42, 0.7);
        --sidebar-width: 260px;
        --main-bg: #f0f2f5;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --primary-color: #3498db;
        --primary-glow: rgba(52, 152, 219, 0.5);
    }
    
    body {
        background-color: var(--main-bg);
        margin: 0;
        font-family: 'Poppins', sans-serif; /* Assurez-vous d'importer Poppins */
    }
    
    /* NOUVELLE STRUCTURE DE PAGE AVEC SIDEBAR */
    .page-wrapper {
        display: flex;
    }
    
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: var(--sidebar-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        color: #e2e8f0;
        display: flex;
        flex-direction: column;
        padding: 20px 0;
        z-index: 1000;
        transition: transform 0.3s ease;
        animation: slideIn 0.5s ease-out;
    }

    @keyframes slideIn {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }
    
    .sidebar-header {
        padding: 0 25px 20px 25px;
        font-size: 1.5rem;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sidebar-header i {
        margin-right: 10px;
        color: var(--primary-color);
    }
    
    .sidebar-nav {
        flex-grow: 1;
        margin-top: 20px;
    }
    .sidebar-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .nav-link {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .nav-link i {
        width: 20px;
        margin-right: 15px;
        font-size: 1.1rem;
    }
    
    .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: var(--primary-color);
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }
    
    .nav-link:hover, .nav-link.active {
        background: linear-gradient(90deg, rgba(52, 152, 219, 0.2), transparent);
        color: #fff;
    }
    .nav-link:hover::before, .nav-link.active::before {
        transform: scaleY(1);
    }

    .sidebar-footer {
        padding: 20px 25px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .user-profile img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid var(--primary-color);
    }
    .user-info {
        display: flex;
        flex-direction: column;
    }
    .user-info span { font-weight: 600; }
    .user-info small { color: #94a3b8; }
    .logout-link {
        color: #cbd5e1;
        font-size: 1.2rem;
        text-decoration: none;
        transition: color 0.3s;
    }
    .logout-link:hover { color: #e74c3c; }
    
    /* AJUSTEMENT DU CONTENU PRINCIPAL */
    .main-content {
        margin-left: var(--sidebar-width);
        flex-grow: 1;
        padding: 20px;
    }
    
    /* VOS STYLES ORIGINAUX (légèrement adaptés) */
    .student-dashboard { padding: 10px 0; }
    
    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
    }
    
    .welcome-title { font-size: 2.2rem; }
    .welcome-title span { color: #f1c40f; font-weight: 700; }
    .welcome-text { font-size: 1.1rem; opacity: 0.9; }
    
    .welcome-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 5px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
        animation: float 6s ease-in-out infinite;
    }
    .welcome-image img { width: 100%; height: 100%; object-fit: cover; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        z-index: 1;
        border: 1px solid #eef2f7;
    }
    .stat-card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1); }
    .card-icon { font-size: 2.5rem; margin-bottom: 15px; }
    .card-content h3 { font-size: 2.2rem; margin-bottom: 5px; color: var(--text-dark); }
    .card-content p { color: var(--text-light); font-size: 1rem; }
    .card-light {
        position: absolute; width: 100px; height: 100px;
        border-radius: 50%; filter: blur(60px); opacity: 0.5;
        z-index: -1; transition: all 0.5s ease; pointer-events: none;
    }
    
    .card-1 { border-left: 5px solid #3498db; }
    .card-1 .card-icon { color: #3498db; }
    .card-1 .card-light { background: #3498db; top: -30px; right: -30px; }
    
    .card-2 { border-left: 5px solid #e74c3c; }
    .card-2 .card-icon { color: #e74c3c; }
    .card-2 .card-light { background: #e74c3c; bottom: -30px; left: -30px; }
    
    .card-3 { border-left: 5px solid #2ecc71; }
    .card-3 .card-icon { color: #2ecc71; }
    .card-3 .card-light { background: #2ecc71; top: -30px; left: -30px; }
    
    .card-4 { border-left: 5px solid #f39c12; }
    .card-4 .card-icon { color: #f39c12; }
    .card-4 .card-light { background: #f39c12; bottom: -30px; right: -30px; }

    .dashboard-bottom-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 40px;
    }
    
    .section-title {
        font-size: 1.6rem; color: var(--text-dark);
        margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }

    .courses-section, .latest-courses-section {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
        border: 1px solid #eef2f7;
    }
    
    .course-card {
        background: #f8f9fa; border-radius: 10px; padding: 15px;
        display: flex; align-items: center; gap: 15px;
        margin-bottom: 10px; position: relative; overflow: hidden;
    }
    .course-time { background: var(--primary-color); color: white; padding: 8px 12px; border-radius: 5px; font-weight: bold; }
    .course-content h3 { font-size: 1.1rem; margin: 0; color: var(--text-dark); }
    .course-icon { font-size: 1.5rem; color: var(--primary-color); margin-left: auto; }

    .courses-list .course-item {
        display: flex; align-items: center; padding: 15px 0;
        border-bottom: 1px solid #eee; text-decoration: none;
        transition: background-color 0.3s ease;
    }
    .courses-list .course-item:last-child { border-bottom: none; }
    .courses-list .course-item:hover { background-color: #f8f9fa; border-radius: 5px; }

    .course-file { font-size: 1.8rem; color: #e74c3c; margin-right: 20px; width: 40px; text-align: center; }
    .course-details { flex: 1; }
    .course-details h3 { font-size: 1.1rem; margin-bottom: 5px; color: var(--text-dark); }
    .course-details p { color: var(--text-light); font-size: 0.9rem; margin: 0; }
    .course-actions { color: #bdc3c7; transition: color 0.3s; padding: 0 10px; }
    .course-item:hover .course-actions { color: var(--primary-color); }

    .no-data-card {
        background: #f8f9fa; border-radius: 10px; padding: 40px;
        text-align: center; border: 1px dashed #e0e0e0;
    }
    .no-data-card i { font-size: 3rem; color: #bdc3c7; margin-bottom: 15px; }
    .no-data-card p { color: var(--text-light); font-size: 1.1rem; }
    
    .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s; border: none; cursor: pointer; }
    .btn-outline-primary { background: transparent; border: 1px solid var(--primary-color); color: var(--primary-color); }
    .btn-outline-primary:hover { background: var(--primary-color); color: white; }
    .text-center { text-align: center; }
    .mt-4 { margin-top: 20px; }
    
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    .animated-avatar { animation: float 6s ease-in-out infinite; }
    
    @media (max-width: 1200px) {
        .dashboard-bottom-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); }
        .main-content { margin-left: 0; }
        /* Ajouter un bouton pour ouvrir/fermer la sidebar sur mobile si besoin */
    }
    @media (max-width: 768px) {
        .welcome-section { flex-direction: column; text-align: center; }
        .welcome-image { margin-top: 20px; }
    }
</style>

<!-- ================== SCRIPTS JAVASCRIPT (VOTRE SCRIPT) ================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    const speed = 100;
    
    const animateCounter = (counter) => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            if (isNaN(target) || target === 0) {
                counter.innerText = target;
                return;
            }
            const inc = Math.max(target / speed, 1);
            
            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 15);
            } else {
                counter.innerText = target;
            }
        };
        updateCount();
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
});
</script>

