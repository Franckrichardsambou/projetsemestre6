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
$page_title = "Mon Emploi du Temps";


// --- RÉCUPÉRATION ET STRUCTURATION DES DONNÉES DE L'EMPLOI DU TEMPS ---
$stmt_schedule = $pdo->prepare("
    SELECT
        edt.jour_semaine,
        edt.heure_debut,
        edt.heure_fin,
        m.nom AS matiere_nom,
        u.prenom AS enseignant_prenom,
        u.nom AS enseignant_nom
    FROM emplois_du_temps edt
    JOIN matieres m ON edt.matiere_id = m.id
    JOIN enseignants ens ON edt.enseignant_id = ens.id
    JOIN utilisateurs u ON ens.utilisateur_id = u.id
    WHERE edt.classe_id = ?
    ORDER BY edt.heure_debut
");
$stmt_schedule->execute([$classe_id]);
$schedule_raw = $stmt_schedule->fetchAll();

// On restructure les données pour un affichage facile
$schedule_by_day = [];
foreach ($schedule_raw as $entry) {
    $day = $entry['jour_semaine'];
    $start_time = date('H:i', strtotime($entry['heure_debut']));
    $end_time = date('H:i', strtotime($entry['heure_fin']));
    
    // Calcule la durée pour le 'row-span'
    $duration = (strtotime($end_time) - strtotime($start_time)) / 3600; // Durée en heures

    $schedule_by_day[$day][$start_time] = [
        'end_time' => $end_time,
        'matiere' => $entry['matiere_nom'],
        'enseignant' => 'Prof. ' . $entry['enseignant_prenom'] . ' ' . $entry['enseignant_nom'],
        'duration' => $duration
    ];
}

// Définir les jours et les créneaux horaires
$days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$time_slots = [];
for ($h = 8; $h <= 17; $h++) {
    $time_slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
}
$current_day_english = date('l'); // 'Monday', 'Tuesday'...
$jours_fr_en = ['Lundi' => 'Monday', 'Mardi' => 'Tuesday', 'Mercredi' => 'Wednesday', 'Jeudi' => 'Thursday', 'Vendredi' => 'Friday', 'Samedi' => 'Saturday'];
$current_day_french = array_search($current_day_english, $jours_fr_en);



?>

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
    }
    body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;}
    .page-wrapper{display:flex;}
    .main-content{margin-left:var(--sidebar-width);flex-grow:1;padding:30px;}
    .content-panel{background:var(--card-bg);border-radius:15px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,.07);margin-bottom:30px;animation:fadeIn .5s ease-out;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;border-bottom:1px solid #eef2f7;padding-bottom:20px;}
    .page-header h1{font-size:2.2rem;color:var(--text-dark);margin:0;}
    .prompt-message{text-align:center;padding:50px;}.prompt-message i{font-size:3.5rem;color:var(--primary-color);opacity:.3;margin-bottom:20px;display:inline-block;}.prompt-message p{font-size:1.2rem;color:var(--text-light);}
    
    /* Styles de l'emploi du temps */
    .timetable-container { overflow-x: auto; }
    .timetable {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
    }
    .timetable th, .timetable td {
        padding: 10px;
        text-align: center;
        border: 1px solid #eef2f7;
        vertical-align: top;
    }
    .timetable thead th {
        background-color: #f8fafc;
        color: var(--text-dark);
        font-weight: 600;
        padding: 15px 10px;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .timetable .time-slot-header {
        background-color: #f1f5f9;
        font-weight: 600;
        color: var(--text-light);
        width: 120px;
    }
    .timetable td {
        height: 80px;
    }
    .timetable tbody tr:nth-child(odd) td { background-color: var(--card-bg); }
    .timetable tbody tr:nth-child(even) td { background-color: #fdfdff; }

    /* Mise en évidence du jour actuel */
    .timetable .current-day {
        background-color: rgba(52, 152, 219, 0.1);
        color: var(--primary-color);
        border-bottom: 3px solid var(--primary-color);
    }

    /* Bloc de cours */
    .course-block {
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
        padding: 10px;
        border-radius: 8px;
        color: #fff;
        background: linear-gradient(135deg, #5dade2, var(--primary-color));
        box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        font-size: 0.9rem;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: default;
    }
    .course-block:hover {
        transform: scale(1.03);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
    }
    .course-block strong {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }
    .course-block span {
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .course-block i {
        margin-right: 5px;
    }

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
                <li><a href="cours.php" class="nav-link"><i class="fas fa-book fa-fw"></i> <span>Mes Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link active"><i class="fas fa-calendar-week fa-fw"></i> <span>Emploi du temps</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile"><img src="/etablissement/assets/images/student-avatar.png" alt="Avatar"><div class="user-info"><span><?= htmlspecialchars($etudiant['prenom']) ?></span><small><?= htmlspecialchars($etudiant['classe_nom']) ?></small></div></div><a href="/etablissement/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt fa-fw"></i></a>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar-week"></i> Mon Emploi du Temps</h1>
        </div>

        <?php if (empty($schedule_raw)): ?>
            <div class="content-panel prompt-message">
                <i class="fas fa-calendar-times"></i>
                <p>L'emploi du temps de votre classe n'est pas encore disponible.</p>
            </div>
        <?php else: ?>
            <div class="content-panel timetable-container">
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="time-slot-header">Heure</th>
                            <?php foreach ($days_of_week as $day): ?>
                                <th class="<?= ($day == $current_day_french) ? 'current-day' : '' ?>"><?= $day ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rendered_slots = [];
                        foreach ($time_slots as $time): 
                        ?>
                            <tr>
                                <td class="time-slot-header"><?= $time ?></td>
                                <?php foreach ($days_of_week as $day): ?>
                                    <?php
                                    // Si un créneau a déjà été affiché par un rowspan, on le saute
                                    if (isset($rendered_slots[$day][$time])) {
                                        continue;
                                    }

                                    if (isset($schedule_by_day[$day][$time])):
                                        $entry = $schedule_by_day[$day][$time];
                                        $rowspan = $entry['duration']; // Durée en heures
                                        
                                        // Marquer les créneaux futurs comme rendus
                                        for ($i = 0; $i < $rowspan; $i++) {
                                            $next_hour = date('H:i', strtotime($time . " +$i hours"));
                                            $rendered_slots[$day][$next_hour] = true;
                                        }
                                    ?>
                                        <td rowspan="<?= $rowspan ?>">
                                            <div class="course-block">
                                                <strong><?= htmlspecialchars($entry['matiere']) ?></strong>
                                                <span><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($entry['enseignant']) ?></span>
                                                <span><i class="far fa-clock"></i> <?= $time ?> - <?= $entry['end_time'] ?></span>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

