<?php
// --- DÉBOGAGE, SESSION ET CONFIGURATION ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Simulation pour le test - à retirer en production
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = ['id' => 2, 'role' => 'enseignant'];
}

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// --- AUTHENTIFICATION ET IDENTIFICATION DE L'ENSEIGNANT ---
check_role('enseignant');
$user_id = $_SESSION['user']['id'];

$stmt_enseignant = $pdo->prepare("SELECT e.id, u.nom, u.prenom, e.specialite FROM enseignants e JOIN utilisateurs u ON e.utilisateur_id = u.id WHERE u.id = ?");
$stmt_enseignant->execute([$user_id]);
$enseignant = $stmt_enseignant->fetch(PDO::FETCH_ASSOC);
if (!$enseignant) { die("Erreur critique : Profil enseignant non trouvé."); }
$enseignant_id = $enseignant['id'];
$page_title = "Gestion des Absences";

// --- GESTION DES FILTRES ---
$selected_classe_id = filter_input(INPUT_GET, 'classe_id', FILTER_VALIDATE_INT);
$selected_date = filter_input(INPUT_GET, 'date', FILTER_DEFAULT, ['options' => ['default' => date('Y-m-d')]]);

// --- LOGIQUE CRUD ADAPTÉE À TA TABLE `absences` SANS `enseignant_id` ---
$notification = null;

// Gérer une demande de SUPPRESSION individuelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_absence') {
    $absence_id_to_delete = filter_input(INPUT_POST, 'absence_id', FILTER_VALIDATE_INT);
    if ($absence_id_to_delete) {
        $stmt_delete = $pdo->prepare("DELETE FROM absences WHERE id = ?");
        $deleted = $stmt_delete->execute([$absence_id_to_delete]);
        $_SESSION['notification'] = $deleted 
            ? ['type' => 'success', 'message' => 'Absence supprimée avec succès.']
            : ['type' => 'error', 'message' => 'Erreur lors de la suppression.'];
    }
    header("Location: absences.php?classe_id=$selected_classe_id&date=$selected_date");
    exit;
}

// Gérer la SAUVEGARDE de toute la feuille d'appel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_absences') {
    $classe_id_form = filter_input(INPUT_POST, 'classe_id', FILTER_VALIDATE_INT);
    $date_form = filter_input(INPUT_POST, 'date', FILTER_DEFAULT);

    if ($classe_id_form && $date_form) {
        $stmt_all_students = $pdo->prepare("SELECT id FROM etudiants WHERE classe_id = ?");
        $stmt_all_students->execute([$classe_id_form]);
        $all_student_ids = $stmt_all_students->fetchAll(PDO::FETCH_COLUMN);
        
        $pdo->beginTransaction();
        try {
            foreach ($all_student_ids as $etudiant_id) {
                $is_marked_absent = isset($_POST['absent']) && in_array($etudiant_id, $_POST['absent']);
                
                if ($is_marked_absent) {
                    $justifie = isset($_POST['justifie'][$etudiant_id]) ? 1 : 0;
                    $commentaire = $_POST['commentaire'][$etudiant_id] ?? '';

                    // Pour que cela fonctionne, un index UNIQUE sur (etudiant_id, date) est recommandé
                    // `ALTER TABLE absences ADD UNIQUE KEY `unique_absence` (`etudiant_id`, `date`);`
                    $sql = "INSERT INTO absences (etudiant_id, `date`, justifie, commentaire) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE justifie = VALUES(justifie), commentaire = VALUES(commentaire)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$etudiant_id, $date_form, $justifie, $commentaire]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM absences WHERE etudiant_id = ? AND `date` = ?");
                    $stmt->execute([$etudiant_id, $date_form]);
                }
            }
            $pdo->commit();
            $_SESSION['notification'] = ['type' => 'success', 'message' => 'Feuille d\'appel enregistrée avec succès !'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['notification'] = ['type' => 'error', 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    header("Location: absences.php?classe_id=$classe_id_form&date=$date_form");
    exit;
}

// Récupérer la notification de la session
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
$teacher_classes = [];
$students_with_absences = [];

$stmt_classes = $pdo->prepare("SELECT DISTINCT c.id, c.nom, c.niveau FROM classes c JOIN emplois_du_temps edt ON c.id = edt.classe_id WHERE edt.enseignant_id = ? ORDER BY c.niveau, c.nom");
$stmt_classes->execute([$enseignant_id]);
$teacher_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

if (!$selected_classe_id && !empty($teacher_classes)) {
    $selected_classe_id = $teacher_classes[0]['id'];
}

if ($selected_classe_id) {
    $stmt_students = $pdo->prepare("
        SELECT 
            e.id as etudiant_id, u.nom, u.prenom,
            a.id as absence_id, a.justifie, a.commentaire
        FROM etudiants e
        JOIN utilisateurs u ON e.utilisateur_id = u.id
        LEFT JOIN absences a ON e.id = a.etudiant_id AND a.date = ?
        WHERE e.classe_id = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt_students->execute([$selected_date, $selected_classe_id]);
    $students_with_absences = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}


?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ================== STYLES CSS DESIGN ================== -->
<style>
    :root{--sidebar-bg:rgba(22,22,35,.8);--sidebar-width:260px;--main-bg:#f4f7fc;--card-bg:#fff;--text-dark:#1e293b;--text-light:#64748b;--primary-color:#8e44ad;--primary-glow:rgba(142,68,173,.5);--success-color:#2ecc71;--danger-color:#e74c3c;--error-color:#e74c3c;}
    body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;}
    .page-wrapper{display:flex;}
    .main-content{margin-left:var(--sidebar-width);flex-grow:1;padding:30px;}
    .content-panel{background:var(--card-bg);border-radius:15px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,.07);margin-bottom:30px;animation:fadeIn .5s ease-out;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;border-bottom:1px solid #eef2f7;padding-bottom:20px;}
    .page-header h1{font-size:2.2rem;color:var(--text-dark);margin:0;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;border:none;cursor:pointer;}
    .btn-primary{background:var(--primary-color);color:#fff;box-shadow:0 4px 15px var(--primary-glow);}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px var(--primary-glow);}
    .btn-danger-outline { background: transparent; border: 2px solid var(--danger-color); color: var(--danger-color); padding: 5px 10px; font-size: 0.8rem; }
    .btn-danger-outline:hover { background: var(--danger-color); color: #fff; }
    .notification{padding:15px 20px;border-radius:10px;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;animation:fadeIn .3s;}
    .notification.success{background:linear-gradient(45deg,var(--success-color),#28b463);}
    .notification.error{background:linear-gradient(45deg,var(--error-color),#c0392b);}
    .prompt-message{text-align:center;padding:50px;}.prompt-message i{font-size:3.5rem;color:var(--primary-color);opacity:.3;margin-bottom:20px;display:inline-block;}.prompt-message p{font-size:1.2rem;color:var(--text-light);}
    .filter-form .form-row{display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:flex-end;}
    .form-group label{display:block;margin-bottom:8px;font-weight:600;color:#475569;font-size:.9rem;}
    .form-control{width:100%;padding:12px 15px;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;background-color:#f8fafc;transition:all .3s;}
    .form-control:focus{outline:none;border-color:var(--primary-color);background-color:#fff;box-shadow:0 0 0 3px var(--primary-glow);}
    .absences-table{width:100%;border-collapse:separate;border-spacing:0 10px;}
    .absences-table th{text-align:left;padding:0 15px 10px;color:#475569;font-weight:600;text-transform:uppercase;font-size:.85rem;}
    .absences-table td{background:#f8fafc;padding:15px;vertical-align:middle;}.absences-table tbody tr.is-absent td{background:#fff1f2;}
    .absences-table td:first-child{border-top-left-radius:10px;border-bottom-left-radius:10px;}.absences-table td:last-child{border-top-right-radius:10px;border-bottom-right-radius:10px;}
    .student-name{display:flex;align-items:center;gap:15px;}.student-name .avatar{width:40px;height:40px;border-radius:50%;background:var(--primary-color);color:#fff;display:grid;place-items:center;font-weight:600;}
    .toggle-switch{position:relative;display:inline-block;width:50px;height:28px;}.toggle-switch input{opacity:0;width:0;height:0;}
    .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;transition:.4s;border-radius:28px;}.slider:before{position:absolute;content:"";height:20px;width:20px;left:4px;bottom:4px;background-color:#fff;transition:.4s;border-radius:50%;}
    input:checked + .slider{background-color:var(--primary-color);}input:checked + .slider.justified{background-color:var(--success-color);}
    input:focus + .slider{box-shadow:0 0 1px var(--primary-color);}
    input:checked + .slider:before{transform:translateX(22px);}
    .justification-cell, .action-cell {opacity:0;visibility:hidden;transition:opacity .4s;}
    tr.is-absent .justification-cell, tr.is-absent .action-cell {opacity:1;visibility:visible;}
    /* Sidebar (ton code) */
    .sidebar{width:var(--sidebar-width);height:100vh;position:fixed;background:var(--sidebar-bg);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-right:1px solid rgba(255,255,255,.1);color:#e2e8f0;display:flex;flex-direction:column;z-index:1000}.sidebar-header{padding:20px 25px;font-size:1.4rem;font-weight:600;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:12px;color:#fff}.sidebar-header i{color:var(--primary-color);text-shadow:0 0 10px var(--primary-glow)}.sidebar-nav{flex-grow:1;margin-top:20px}.sidebar-nav ul{list-style:none;padding:0;margin:0}.nav-link{display:flex;align-items:center;padding:15px 25px;color:#cbd5e1;text-decoration:none;font-size:1rem;transition:all .3s ease;position:relative}.nav-link i{width:20px;margin-right:15px;font-size:1.1rem}.nav-link::before{content:'';position:absolute;left:0;top:0;height:100%;width:4px;background:var(--primary-color);transform:scaleY(0);transition:transform .3s ease,box-shadow .3s ease;border-radius:0 5px 5px 0}.nav-link.active,.nav-link:hover{background:linear-gradient(90deg,rgba(142,68,173,.25),transparent);color:#fff}.nav-link.active::before,.nav-link:hover::before{transform:scaleY(1);box-shadow:0 0 15px var(--primary-glow)}.sidebar-footer{padding:20px 25px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:space-between}.user-profile{display:flex;align-items:center;gap:10px}.user-profile img{width:40px;height:40px;border-radius:50%;border:2px solid var(--primary-color)}.user-info span{font-weight:600}.user-info small{color:#94a3b8}.logout-link{color:#cbd5e1;font-size:1.2rem;transition:color .3s}.logout-link:hover{color:#e74c3c}
</style>

<div class="page-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar teacher-sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-chalkboard-teacher"></i> Espace Enseignant</h3></div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw"></i> <span>Tableau de Bord</span></a></li>
                <li><a href="notes.php" class="nav-link"><i class="fas fa-edit fa-fw"></i> <span>Gérer les Notes</span></a></li>
                <li><a href="cours.php" class="nav-link"><i class="fas fa-upload fa-fw"></i> <span>Gérer les Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-alt fa-fw"></i> <span>Mon Emploi du Temps</span></a></li>
                <li><a href="absences.php" class="nav-link active"><i class="fas fa-user-check fa-fw"></i> <span>Gérer les Absences</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <img src="<?= BASE_URL ?>/assets/images/teacher-avatar.png" alt="Avatar">
                <div class="user-info">
                    <span>Prof. <?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></span>
                    <small><?= htmlspecialchars($enseignant['specialite']) ?></small>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-link" title="Déconnexion"><i class="fas fa-sign-out-alt fa-fw"></i></a>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-clock"></i> Feuille d'Appel</h1>
        </div>
        
        <?php if ($notification): ?>
            <div class="notification <?= htmlspecialchars($notification['type']) ?>">
                <i class="fas fa-<?= $notification['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                <?= htmlspecialchars($notification['message']) ?>
            </div>
        <?php endif; ?>

        <div class="content-panel filter-form">
            <form action="absences.php" method="GET" id="filterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="classe_id">Classe</label>
                        <select name="classe_id" id="classe_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Sélectionner une classe --</option>
                            <?php foreach ($teacher_classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_classe_id == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['niveau'] . ' - ' . $class['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars($selected_date) ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($students_with_absences)): ?>
            <div class="content-panel">
                <form id="mainForm" action="absences.php?<?= http_build_query(['classe_id' => $selected_classe_id, 'date' => $selected_date]) ?>" method="POST">
                    <input type="hidden" name="action" value="save_absences">
                    <input type="hidden" name="classe_id" value="<?= $selected_classe_id ?>">
                    <input type="hidden" name="date" value="<?= $selected_date ?>">
                    
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th>Étudiant</th>
                                <th style="width: 100px;">Absent(e)</th>
                                <th style="width: 120px;">Justifié(e)</th>
                                <th>Commentaire / Justificatif</th>
                                <th style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_with_absences as $student): 
                                $is_absent = !is_null($student['absence_id']);
                            ?>
                                <tr class="<?= $is_absent ? 'is-absent' : '' ?>">
                                    <td><div class="student-name"><div class="avatar"><?= strtoupper(substr($student['prenom'], 0, 1) . substr($student['nom'], 0, 1)) ?></div><span><?= htmlspecialchars($student['prenom'] . ' ' . $student['nom']) ?></span></div></td>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="absent[]" value="<?= $student['etudiant_id'] ?>" <?= $is_absent ? 'checked' : '' ?> onchange="toggleJustification(this)">
                                            <span class="slider"></span>
                                        </label>
                                    </td>
                                    <td class="justification-cell">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="justifie[<?= $student['etudiant_id'] ?>]" value="1" <?= $student['justifie'] ? 'checked' : '' ?>>
                                            <span class="slider justified"></span>
                                        </label>
                                    </td>
                                    <td class="justification-cell">
                                        <input type="text" name="commentaire[<?= $student['etudiant_id'] ?>]" class="form-control" value="<?= htmlspecialchars($student['commentaire'] ?? '') ?>" placeholder="Motif de l'absence...">
                                    </td>
                                    <td class="action-cell">
                                        <?php if ($is_absent): ?>
                                            <!-- Formulaire de suppression individuelle -->
                                            <form class="delete-form" action="absences.php?<?= http_build_query(['classe_id' => $selected_classe_id, 'date' => $selected_date]) ?>" method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_absence">
                                                <input type="hidden" name="absence_id" value="<?= $student['absence_id'] ?>">
                                                <button type="submit" class="btn btn-danger-outline" title="Supprimer cette absence">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer la feuille d'appel</button>
                    </div>
                </form>
            </div>
        <?php elseif ($selected_classe_id): ?>
            <div class="content-panel prompt-message"><i class="fas fa-users-slash"></i><p>Aucun étudiant trouvé pour cette classe.</p></div>
        <?php else: ?>
             <div class="content-panel prompt-message"><i class="fas fa-filter"></i><p>Veuillez sélectionner une classe pour commencer.</p></div>
        <?php endif; ?>
    </main>
</div>

<script>
function toggleJustification(checkbox) {
    const row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.add('is-absent');
    } else {
        row.classList.remove('is-absent');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Appliquer l'état initial au chargement de la page pour toutes les lignes
    document.querySelectorAll('.toggle-switch input[name^="absent"]').forEach(cb => {
        toggleJustification(cb);
    });

    // Ajouter une confirmation avant de soumettre le formulaire de suppression
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Voulez-vous vraiment supprimer cette absence ?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

