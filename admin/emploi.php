<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');

$page_title = "Gestion des Emplois du Temps";
$notification = null;

// --- LOGIQUE CRUD (INCHANGÉE, ELLE EST CORRECTE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $classe_id = $_POST['classe_id'];
    $jour_semaine = $_POST['jour_semaine'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $matiere_id = $_POST['matiere_id'];
    $enseignant_id = $_POST['enseignant_id'];
    $emploi_id = $_POST['emploi_id'] ?? null;
    if (strtotime($heure_fin) <= strtotime($heure_debut)) {
        $notification = ['type' => 'error', 'message' => 'L\'heure de fin doit être après l\'heure de début.'];
    } else {
        if ($_POST['action'] === 'save') {
            if ($emploi_id) {
                $stmt = $pdo->prepare("UPDATE emplois_du_temps SET classe_id=?, jour_semaine=?, heure_debut=?, heure_fin=?, matiere_id=?, enseignant_id=? WHERE id=?");
                $stmt->execute([$classe_id, $jour_semaine, $heure_debut, $heure_fin, $matiere_id, $enseignant_id, $emploi_id]);
                $notification = ['type' => 'success', 'message' => 'Créneau horaire mis à jour.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO emplois_du_temps (classe_id, jour_semaine, heure_debut, heure_fin, matiere_id, enseignant_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$classe_id, $jour_semaine, $heure_debut, $heure_fin, $matiere_id, $enseignant_id]);
                $notification = ['type' => 'success', 'message' => 'Créneau horaire ajouté.'];
            }
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $emploi_id_to_delete = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM emplois_du_temps WHERE id = ?");
    $stmt->execute([$emploi_id_to_delete]);
    $notification = ['type' => 'success', 'message' => 'Créneau horaire supprimé.'];
}

// ==========================================================
// --- RÉCUPÉRATION DES DONNÉES (LOGIQUE CORRIGÉE) ---
// ==========================================================
$classes = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
$matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$enseignants = $pdo->query("SELECT e.id, u.nom, u.prenom FROM enseignants e JOIN utilisateurs u ON e.utilisateur_id = u.id ORDER BY u.nom, u.prenom")->fetchAll(PDO::FETCH_ASSOC);

// Détermination de la classe sélectionnée de manière plus sûre
$selected_classe_id = null;
if (isset($_GET['classe_id'])) {
    $selected_classe_id = (int)$_GET['classe_id'];
} elseif (!empty($classes)) {
    // Si aucune classe n'est dans l'URL, on prend la première de la liste
    $selected_classe_id = $classes[0]['id'];
}

// Initialisation de la grille
$schedule_by_day = [];
$schedule_raw = [];

// On ne lance la requête que si une classe est effectivement sélectionnée
if ($selected_classe_id) {
    $stmt = $pdo->prepare("
        SELECT edt.*, m.nom as matiere_nom, u.nom as enseignant_nom, u.prenom as enseignant_prenom 
        FROM emplois_du_temps edt 
        JOIN matieres m ON edt.matiere_id = m.id 
        JOIN enseignants ens ON edt.enseignant_id = ens.id 
        JOIN utilisateurs u ON ens.utilisateur_id = u.id 
        WHERE edt.classe_id = ?
    ");
    $stmt->execute([$selected_classe_id]);
    $schedule_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // On restructure les données pour un affichage facile dans la grille
    foreach ($schedule_raw as $entry) {
        $day = $entry['jour_semaine'];
        $start_time = date('H:i', strtotime($entry['heure_debut']));
        $end_time = date('H:i', strtotime($entry['heure_fin']));
        $duration = (strtotime($end_time) - strtotime($start_time)) / 3600;

        $entry['duration'] = $duration > 0 ? $duration : 1;
        $schedule_by_day[$day][$start_time] = $entry;
    }
}

$days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$time_slots = []; for ($h = 8; $h <= 17; $h++) { $time_slots[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; }
$current_day_english = date('l');
$jours_fr_en = ['Lundi' => 'Monday', 'Mardi' => 'Tuesday', 'Mercredi' => 'Wednesday', 'Jeudi' => 'Thursday', 'Vendredi' => 'Friday', 'Samedi' => 'Saturday'];
$current_day_french = array_search($current_day_english, $jours_fr_en);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Le CSS est correct, pas de changement nécessaire -->
    <style>
        :root {--sidebar-bg:#fff;--main-bg:#f0f4f8;--text-dark:#1e293b;--text-light:#64748b;--primary-color:#4f46e5;--primary-light:rgba(79,70,229,.1);--success-color:#10b981;--danger-color:#ef4444;--white:#fff;}
        body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;color:var(--text-dark);}
        .dashboard-container{display:flex;}
        .sidebar{width:250px;background:var(--sidebar-bg);color:#333;min-height:100vh;padding:20px;box-shadow:2px 0 15px rgba(0,0,0,.05);position:fixed;top:0;left:0;}
        .sidebar .admin-header{text-align:center;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid #eee;}
        .sidebar .admin-header i{font-size:30px;color:var(--primary-color);margin-bottom:10px;}
        .sidebar .admin-header h2{margin:0;font-size:22px;color:var(--text-dark);}
        .sidebar ul{list-style:none;padding:0;}.sidebar ul li{margin:10px 0;}.sidebar ul li a{color:var(--text-light);text-decoration:none;font-weight:500;display:flex;align-items:center;padding:12px 15px;border-radius:8px;transition:all .3s ease;}
        .sidebar ul li a i{margin-right:10px;width:20px;text-align:center;}.sidebar ul li a.active,.sidebar ul li a:hover{background:var(--primary-color);color:var(--white);box-shadow:0 4px 10px rgba(79,70,229,.3);}
        .main-content{margin-left:290px;padding:30px;width:calc(100% - 250px);}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
        .page-header h1{font-size:2.5rem;font-weight:700;color:var(--text-dark);margin:0;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s ease;border:none;cursor:pointer;}
        .btn-primary{background:var(--primary-color);color:var(--white);}.btn-primary:hover{background:#4338ca;}
        .filter-bar{background:var(--white);padding:20px;border-radius:12px;margin-bottom:25px;display:flex;gap:20px;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        .filter-bar label{font-weight:600;}.filter-bar select{padding:10px 15px;border-radius:8px;border:1px solid #ccc;font-size:1rem;flex-grow:1;}
        .timetable-container{background:var(--white);padding:20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);overflow-x:auto;}
        .timetable{width:100%;min-width:800px;border-collapse:collapse;}
        .timetable th,.timetable td{border:1px solid #eef2f7;text-align:center;vertical-align:top;padding:5px;}
        .timetable thead th{background-color:#f8fafc;padding:15px 10px;font-weight:600;}
        .timetable .time-col{width:100px;font-weight:600;background:#f8fafc;}
        .timetable .day-col.current-day{background-color:var(--primary-light);}
        .timetable td{height:80px;position:relative;}
        .timetable td.empty-slot{cursor:pointer;transition:background-color .2s;}.timetable td.empty-slot:hover{background:#e0e7ff;}
        .course-block{position:relative;padding:10px;border-radius:8px;color:#fff;font-size:.9rem;text-align:left;box-shadow:0 2px 5px rgba(0,0,0,.15);cursor:pointer;transition:transform .2s,box-shadow .2s; height:100%; display:flex; flex-direction:column; justify-content:center;}
        .course-block:hover{transform:scale(1.02);box-shadow:0 4px 15px rgba(0,0,0,.2);}
        .course-block strong{display:block;font-weight:600;}.course-block span{font-size:.85rem;opacity:.9; display:block; margin-top:4px;}
        .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);backdrop-filter:blur(5px);animation:fadeIn .3s;}
        .modal-content{position:relative;background:var(--white);margin:5% auto;padding:35px;border-radius:15px;width:90%;max-width:600px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:slideIn .4s ease-out;}
        @keyframes fadeIn{from{opacity:0}}@keyframes slideIn{from{transform:translateY(-50px);opacity:0}}
        .close-btn{color:#aaa;float:right;font-size:28px;font-weight:700;cursor:pointer;line-height:1;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}.form-group.full-width{grid-column:1 / -1;}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;}
        .form-control{width:100%;padding:12px 15px;border:1px solid #ccc;border-radius:8px;font-size:1rem;}.form-control:focus{border-color:var(--primary-color);outline:none;box-shadow:0 0 0 3px var(--primary-light);}
        .notification{padding:15px;border-radius:8px;color:#fff;margin-bottom:20px;}.notification.success{background:var(--success-color);}.notification.error{background:var(--danger-color);}
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <!-- Sidebar HTML inchangé -->
        <div class="admin-header"><i class="fas fa-user-shield"></i><h2>Admin Panel</h2></div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="etudiants.php"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="enseignants.php"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a></li>
            <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="matieres.php"><i class="fas fa-book"></i> Matières</a></li>
            <li><a href="emploi.php" class="active"><i class="fas fa-calendar-alt"></i> Emplois du temps</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header"><h1>Gestion des Emplois du Temps</h1></div>
        <?php if ($notification): ?><div class="notification <?= $notification['type'] ?>"><?= htmlspecialchars($notification['message']) ?></div><?php endif; ?>
        
        <div class="filter-bar">
            <label for="filter_classe">Afficher l'emploi du temps de la classe :</label>
            <?php if (empty($classes)): ?>
                <p>Aucune classe n'a été créée. Veuillez d'abord <a href="classes.php">ajouter une classe</a>.</p>
            <?php else: ?>
                <select id="filter_classe" onchange="window.location.href = 'emploi.php?classe_id=' + this.value;">
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= $classe['id'] ?>" <?= ($selected_classe_id == $classe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        
        <?php if ($selected_classe_id): ?>
        <div class="timetable-container">
            <table class="timetable">
                <thead>
                    <tr>
                        <th class="time-col">Heure</th>
                        <?php foreach ($days_of_week as $day): ?><th class="day-col <?= ($day == $current_day_french) ? 'current-day' : '' ?>"><?= $day ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rendered_slots = []; // Pour gérer les rowspan
                    foreach ($time_slots as $time): 
                    ?>
                    <tr>
                        <td class="time-col"><?= $time ?></td>
                        <?php foreach ($days_of_week as $day): ?>
                            <?php if (isset($rendered_slots[$day][$time])) { continue; } ?>
                            <?php if (isset($schedule_by_day[$day][$time])): 
                                $entry = $schedule_by_day[$day][$time];
                                $rowspan = $entry['duration'];
                                $colors = ['#4f46e5','#db2777','#10b981','#f59e0b','#3b82f6','#8b5cf6'];
                                $color = $colors[crc32($entry['matiere_nom']) % count($colors)];
                                
                                for ($i = 0; $i < $rowspan; $i++) {
                                    $next_hour = date('H:i', strtotime($time . " +$i hours"));
                                    $rendered_slots[$day][$next_hour] = true;
                                }
                            ?>
                                <td class="filled-slot" rowspan="<?= $rowspan ?>">
                                    <div class="course-block edit-slot" style="background:<?= $color ?>"
                                        data-id="<?= $entry['id'] ?>"
                                        data-jour="<?= $day ?>"
                                        data-heure_debut="<?= date('H:i', strtotime($entry['heure_debut'])) ?>"
                                        data-heure_fin="<?= date('H:i', strtotime($entry['heure_fin'])) ?>"
                                        data-matiere_id="<?= $entry['matiere_id'] ?>"
                                        data-enseignant_id="<?= $entry['enseignant_id'] ?>">
                                        <strong><?= htmlspecialchars($entry['matiere_nom']) ?></strong>
                                        <span><i class="fas fa-chalkboard-teacher"></i> Prof. <?= htmlspecialchars($entry['enseignant_prenom']) ?></span>
                                    </div>
                                </td>
                            <?php else: ?>
                                <td class="empty-slot" data-jour="<?= $day ?>" data-heure_debut="<?= $time ?>"></td>
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

<!-- Modal pour Ajouter/Modifier -->
<div id="formModal" class="modal">
    <!-- Le HTML de la modal est correct, pas de changement -->
    <div class="modal-content">
        <span class="close-btn">×</span><h2 id="modalTitle">Ajouter un créneau</h2>
        <form action="emploi.php?classe_id=<?= $selected_classe_id ?>" method="POST">
            <input type="hidden" name="action" value="save"><input type="hidden" name="emploi_id" id="emploi_id"><input type="hidden" name="classe_id" value="<?= $selected_classe_id ?>">
            <div class="form-group"><label for="jour_semaine">Jour</label><select name="jour_semaine" id="jour_semaine" class="form-control" required><?php foreach ($days_of_week as $day): ?><option value="<?= $day ?>"><?= $day ?></option><?php endforeach; ?></select></div>
            <div class="form-grid"><div class="form-group"><label for="heure_debut">Heure de début</label><input type="time" name="heure_debut" id="heure_debut" class="form-control" step="1800" required></div><div class="form-group"><label for="heure_fin">Heure de fin</label><input type="time" name="heure_fin" id="heure_fin" class="form-control" step="1800" required></div></div>
            <div class="form-group"><label for="matiere_id">Matière</label><select name="matiere_id" id="matiere_id" class="form-control" required><option value="">-- Choisir --</option><?php foreach ($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>"><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="enseignant_id">Enseignant</label><select name="enseignant_id" id="enseignant_id" class="form-control" required><option value="">-- Choisir --</option><?php foreach ($enseignants as $enseignant): ?><option value="<?= $enseignant['id'] ?>">Prof. <?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></option><?php endforeach; ?></select></div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;"><a href="#" id="deleteLink" class="btn" style="background:#ef4444; color:white; display:none;"><i class="fas fa-trash"></i> Supprimer</a><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
    // Le JS est correct, pas de changement
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('formModal');
        const closeBtn = document.querySelector('.close-btn');
        const modalTitle = document.getElementById('modalTitle');
        const form = modal.querySelector('form');
        const emploiIdInput = document.getElementById('emploi_id');
        const deleteLink = document.getElementById('deleteLink');
        function openModal() { modal.style.display = 'block'; }
        function closeModal() { modal.style.display = 'none'; }
        document.querySelectorAll('.empty-slot').forEach(slot => {
            slot.onclick = function() {
                modalTitle.innerText = 'Ajouter un créneau'; form.reset(); emploiIdInput.value = '';
                deleteLink.style.display = 'none';
                document.getElementById('jour_semaine').value = this.dataset.jour;
                document.getElementById('heure_debut').value = this.dataset.heure_debut;
                openModal();
            }
        });
        document.querySelectorAll('.edit-slot').forEach(slot => {
            slot.onclick = function() {
                modalTitle.innerText = 'Modifier le créneau'; form.reset(); emploiIdInput.value = this.dataset.id;
                document.getElementById('jour_semaine').value = this.dataset.jour;
                document.getElementById('heure_debut').value = this.dataset.heure_debut;
                document.getElementById('heure_fin').value = this.dataset.heure_fin;
                document.getElementById('matiere_id').value = this.dataset.matiere_id;
                document.getElementById('enseignant_id').value = this.dataset.enseignant_id;
                deleteLink.href = `emploi.php?action=delete&id=${this.dataset.id}&classe_id=<?= $selected_classe_id ?>`;
                deleteLink.style.display = 'inline-flex';
                deleteLink.onclick = () => confirm('Voulez-vous vraiment supprimer ce créneau ?');
                openModal();
            }
        });
        closeBtn.onclick = closeModal;
        window.onclick = function(event) { if (event.target == modal) { closeModal(); } }
    });
</script>

</body>
</html>