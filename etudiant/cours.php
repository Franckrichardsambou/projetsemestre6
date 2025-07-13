<?php
// DÉBOGAGE ET INITIALISATION
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!isset($_SESSION)) { session_start(); }
if (!isset($_SESSION['user'])) { $_SESSION['user'] = ['id' => 1, 'role' => 'etudiant']; } // Simulation

require_once '../config/db.php';

// AUTHENTIFICATION ET IDENTIFICATION
if ($_SESSION['user']['role'] !== 'etudiant') { die("Accès non autorisé."); }
$user_id = $_SESSION['user']['id'];

// Récupérer les informations de l'étudiant (surtout classe_id)
$stmt_etudiant = $pdo->prepare("SELECT e.*, u.nom, u.prenom, c.nom AS classe_nom FROM etudiants e JOIN utilisateurs u ON e.utilisateur_id = u.id JOIN classes c ON e.classe_id = c.id WHERE e.utilisateur_id = ?");
$stmt_etudiant->execute([$user_id]);
$etudiant = $stmt_etudiant->fetch();
if (!$etudiant) { die("Profil étudiant non trouvé."); }
$etudiant_id = $etudiant['id'];
$classe_id = $etudiant['classe_id'];
$page_title = "Mes Cours";

// --- RÉCUPÉRATION DES DONNÉES ---

// Récupérer le filtre de matière (s'il existe)
$filter_matiere_id = isset($_GET['matiere_id']) ? (int)$_GET['matiere_id'] : null;

// Requête de base pour les cours
$sql = "
    SELECT c.*, m.nom as matiere_nom, u.nom as enseignant_nom, u.prenom as enseignant_prenom
    FROM cours c
    JOIN matieres m ON c.matiere_id = m.id
    JOIN enseignants ens ON c.enseignant_id = ens.id
    JOIN utilisateurs u ON ens.utilisateur_id = u.id
    WHERE c.classe_id = ?
";
$params = [$classe_id];

// Ajouter le filtre si une matière est sélectionnée
if ($filter_matiere_id) {
    $sql .= " AND c.matiere_id = ?";
    $params[] = $filter_matiere_id;
}

$sql .= " ORDER BY c.date_ajout DESC";

$stmt_cours = $pdo->prepare($sql);
$stmt_cours->execute($params);
$student_courses = $stmt_cours->fetchAll();

// Récupérer la liste des matières disponibles pour le filtre
$stmt_matieres = $pdo->prepare("SELECT DISTINCT m.id, m.nom FROM matieres m JOIN cours c ON m.id = c.matiere_id WHERE c.classe_id = ? ORDER BY m.nom");
$stmt_matieres->execute([$classe_id]);
$available_matieres = $stmt_matieres->fetchAll();



?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ================== STYLES CSS DESIGN ================== -->
<style>
    /* Copie des variables de couleur du dashboard étudiant */
    :root {
        --sidebar-bg: rgba(15, 23, 42, 0.7);
        --sidebar-width: 260px;
        --main-bg: #f0f2f5;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --primary-color: #3498db;
        --primary-glow: rgba(52, 152, 219, 0.5);
        --pdf-color:#e74c3c; --word-color:#2980b9; --other-color:#7f8c8d;
    }
    body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;}
    .page-wrapper{display:flex;}
    .main-content{margin-left:var(--sidebar-width);flex-grow:1;padding:30px;}
    .content-panel{background:var(--card-bg);border-radius:15px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,.07);margin-bottom:30px;animation:fadeIn .5s ease-out;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;border-bottom:1px solid #eef2f7;padding-bottom:20px;}
    .page-header h1{font-size:2.2rem;color:var(--text-dark);margin:0;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;border:none;cursor:pointer;}
    .btn-primary{background:var(--primary-color);color:white;box-shadow:0 4px 15px var(--primary-glow);}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px var(--primary-glow);}
    .btn-outline-primary{background:transparent;border:2px solid var(--primary-color);color:var(--primary-color);}.btn-outline-primary:hover{background:var(--primary-color);color:#fff;}
    .prompt-message{text-align:center;padding:50px;}.prompt-message i{font-size:3.5rem;color:var(--primary-color);opacity:.3;margin-bottom:20px;display:inline-block;}.prompt-message p{font-size:1.2rem;color:var(--text-light);}
    
    /* Filtres */
    .filter-bar{display:flex;gap:10px;margin-bottom:25px;flex-wrap:wrap;}
    .filter-btn{padding:8px 15px;border-radius:20px;text-decoration:none;font-weight:600;transition:all .3s ease;background:var(--card-bg);color:var(--text-light);border:1px solid #eef2f7;}
    .filter-btn.active,.filter-btn:hover{background:var(--primary-color);color:#fff;border-color:var(--primary-color);transform:translateY(-2px);box-shadow:0 4px 10px var(--primary-glow);}
    .filter-btn i{margin-right:5px;}

    /* Grille des cours */
    .courses-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:25px;}
    .course-card{display:flex;flex-direction:column;background:var(--card-bg);border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,.05);overflow:hidden;transition:transform .3s,box-shadow .3s;}.course-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(52,152,219,.2);}
    .card-header{display:flex;align-items:center;gap:15px;padding:20px;border-bottom:1px solid #eef2f7;}
    .file-icon{font-size:2rem;width:50px;text-align:center;}.file-icon.pdf{color:var(--pdf-color);}.file-icon.word{color:var(--word-color);}.file-icon.autre{color:var(--other-color);}
    .card-title h3{margin:0;font-size:1.2rem;color:var(--text-dark);}.card-title p{margin:5px 0 0;font-size:.9rem;color:var(--text-light);}
    .card-body{padding:20px;flex-grow:1;color:var(--text-light);}.card-body p{margin-top:0;}
    .card-footer{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;background:#f8fafc;}
    .card-date{font-size:.85rem;color:var(--text-light);}
    
    /* Sidebar */
    .sidebar{width:var(--sidebar-width);height:100vh;position:fixed;background:var(--sidebar-bg);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-right:1px solid rgba(255,255,255,.1);color:#e2e8f0;display:flex;flex-direction:column;padding:20px 0;z-index:1000}.sidebar-header{padding:0 25px 20px;font-size:1.5rem;font-weight:600;border-bottom:1px solid rgba(255,255,255,.1)}.sidebar-header i{margin-right:10px;color:var(--primary-color)}.sidebar-nav{flex-grow:1;margin-top:20px}.sidebar-nav ul{list-style:none;padding:0;margin:0}.nav-link{display:flex;align-items:center;padding:15px 25px;color:#cbd5e1;text-decoration:none;font-size:1rem;transition:all .3s ease;position:relative;overflow:hidden}.nav-link i{width:20px;margin-right:15px;font-size:1.1rem}.nav-link::before{content:'';position:absolute;left:0;top:0;height:100%;width:4px;background:var(--primary-color);transform:scaleY(0);transition:transform .3s ease}.nav-link.active,.nav-link:hover{background:linear-gradient(90deg,rgba(52,152,219,.2),transparent);color:#fff}.nav-link.active::before,.nav-link:hover::before{transform:scaleY(1)}.sidebar-footer{padding:20px 25px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:space-between}.user-profile{display:flex;align-items:center;gap:10px}.user-profile img{width:40px;height:40px;border-radius:50%;border:2px solid var(--primary-color)}.user-info{display:flex;flex-direction:column}.user-info span{font-weight:600}.user-info small{color:#94a3b8}.logout-link{color:#cbd5e1;font-size:1.2rem;text-decoration:none;transition:color .3s}.logout-link:hover{color:#e74c3c}
</style>

<div class="page-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-school-flag"></i> Mon Établissement</h3></div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw"></i> <span>Tableau de Bord</span></a></li>
                <li><a href="bulletin.php" class="nav-link"><i class="fas fa-award fa-fw"></i> <span>Mon Bulletin</span></a></li>
                <li><a href="cours.php" class="nav-link active"><i class="fas fa-book fa-fw"></i> <span>Mes Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-week fa-fw"></i> <span>Emploi du temps</span></a></li>
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
            <a href="/etablissement/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt fa-fw"></i></a>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-book-open"></i> Les Cours de ma Classe</h1>
        </div>

        <!-- Barre de filtres par matière -->
        <div class="filter-bar">
            <a href="cours.php" class="filter-btn <?= !$filter_matiere_id ? 'active' : '' ?>"><i class="fas fa-list"></i> Tous les cours</a>
            <?php foreach ($available_matieres as $matiere): ?>
                <a href="cours.php?matiere_id=<?= $matiere['id'] ?>" class="filter-btn <?= ($filter_matiere_id == $matiere['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($matiere['nom']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($student_courses)): ?>
            <div class="content-panel prompt-message">
                <i class="fas fa-folder-open"></i>
                <p>Aucun cours n'a été déposé dans cette matière pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($student_courses as $course): ?>
                    <div class="course-card">
                        <div class="card-header">
                            <div class="file-icon <?= $course['type_fichier'] ?>">
                                <i class="fas fa-file-<?= $course['type_fichier'] === 'pdf' ? 'pdf' : ($course['type_fichier'] === 'word' ? 'word' : 'alt') ?>"></i>
                            </div>
                            <div class="card-title">
                                <h3><?= htmlspecialchars($course['titre']) ?></h3>
                                <p><?= htmlspecialchars($course['matiere_nom']) ?> - par Prof. <?= htmlspecialchars($course['enseignant_prenom'] . ' ' . $course['enseignant_nom']) ?></p>
                            </div>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                        </div>
                        <div class="card-footer">
                            <span class="card-date"><i class="fas fa-calendar-alt"></i> Ajouté le <?= date('d/m/Y', strtotime($course['date_ajout'])) ?></span>
                            <a href="/etablissement/uploads/cours/<?= htmlspecialchars($course['fichier']) ?>" class="btn btn-outline-primary" download>
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

