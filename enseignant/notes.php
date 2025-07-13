<?php
// DÉBOGAGE : AFFICHER TOUTES LES ERREURS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialisation
if (!isset($_SESSION)) { session_start(); }
// Simuler la connexion si elle n'existe pas (pour le test direct)
if (!isset($_SESSION['user'])) { $_SESSION['user'] = ['id' => 2, 'role' => 'enseignant']; }

require_once '../config/db.php';

// --- AUTHENTIFICATION ET IDENTIFICATION DE L'ENSEIGNANT ---
if ($_SESSION['user']['role'] !== 'enseignant') { die("Accès non autorisé."); }
$user_id = $_SESSION['user']['id'];

$stmt_enseignant = $pdo->prepare("SELECT id, (SELECT prenom FROM utilisateurs WHERE id=utilisateur_id) as prenom, (SELECT nom FROM utilisateurs WHERE id=utilisateur_id) as nom, specialite FROM enseignants WHERE utilisateur_id = ?");
$stmt_enseignant->execute([$user_id]);
$enseignant = $stmt_enseignant->fetch();
if (!$enseignant) { die("Profil enseignant non trouvé."); }
$enseignant_id = $enseignant['id'];
$page_title = "Gestion des Notes";

// --- LOGIQUE CRUD (INCHANGÉE) ---
$notification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_notes') {
    // ... (votre logique de sauvegarde)
    $classe_id_form = $_POST['classe_id'];
    $matiere_id_form = $_POST['matiere_id'];
    $trimestre_form = $_POST['trimestre'];
    foreach ($_POST['notes'] as $etudiant_id => $note_value) {
        if (trim($note_value) === '') {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE etudiant_id = ? AND matiere_id = ? AND trimestre = ?");
            $stmt->execute([$etudiant_id, $matiere_id_form, $trimestre_form]);
        } else {
            $note_value = (float) str_replace(',', '.', $note_value);
            if ($note_value >= 0 && $note_value <= 20) {
                $sql = "INSERT INTO notes (etudiant_id, matiere_id, trimestre, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$etudiant_id, $matiere_id_form, $trimestre_form, $note_value]);
            }
        }
    }
    $notification = ['type' => 'success', 'message' => 'Notes enregistrées avec succès !'];
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['note_id'])) {
    // ... (votre logique de suppression)
    $note_id_to_delete = $_GET['note_id'];
    $stmt_delete_check = $pdo->prepare("DELETE n FROM notes n JOIN etudiants e ON n.etudiant_id = e.id WHERE n.id = ? AND e.classe_id IN (SELECT DISTINCT classe_id FROM emplois_du_temps WHERE enseignant_id = ?)");
    $stmt_delete_check->execute([$note_id_to_delete, $enseignant_id]);
    $notification = ['type' => $stmt_delete_check->rowCount() > 0 ? 'success' : 'error', 'message' => $stmt_delete_check->rowCount() > 0 ? 'Note supprimée.' : 'Erreur de suppression.'];
}

// --- RÉCUPÉRATION DES DONNÉES (INCHANGÉE) ---
$stmt_classes = $pdo->prepare("SELECT DISTINCT c.id, c.nom, c.niveau FROM classes c JOIN emplois_du_temps edt ON c.id = edt.classe_id WHERE edt.enseignant_id = ? ORDER BY c.niveau, c.nom");
$stmt_classes->execute([$enseignant_id]);
$teacher_classes = $stmt_classes->fetchAll();
$selected_classe_id = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($teacher_classes[0]['id'] ?? null);
$selected_matiere_id = isset($_GET['matiere_id']) ? (int)$_GET['matiere_id'] : null;
$selected_trimestre = isset($_GET['trimestre']) ? $_GET['trimestre'] : 'T1';
$teacher_matieres = [];
if ($selected_classe_id) {
    $stmt_matieres = $pdo->prepare("SELECT DISTINCT m.id, m.nom FROM matieres m JOIN emplois_du_temps edt ON m.id = edt.matiere_id WHERE edt.enseignant_id = ? AND edt.classe_id = ? ORDER BY m.nom");
    $stmt_matieres->execute([$enseignant_id, $selected_classe_id]);
    $teacher_matieres = $stmt_matieres->fetchAll();
    if (!$selected_matiere_id && !empty($teacher_matieres)) { $selected_matiere_id = $teacher_matieres[0]['id']; }
}
$students_with_notes = [];
if ($selected_classe_id && $selected_matiere_id && $selected_trimestre) {
    $stmt_students = $pdo->prepare("SELECT e.id as etudiant_id, u.nom, u.prenom, n.id as note_id, n.note FROM etudiants e JOIN utilisateurs u ON e.utilisateur_id = u.id LEFT JOIN notes n ON e.id = n.etudiant_id AND n.matiere_id = ? AND n.trimestre = ? WHERE e.classe_id = ? ORDER BY u.nom, u.prenom");
    $stmt_students->execute([$selected_matiere_id, $selected_trimestre, $selected_classe_id]);
    $students_with_notes = $stmt_students->fetchAll();
}


?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ================== STYLES CSS DESIGN ================== -->
<style>
    /* Intégration du thème du Dashboard */
    :root {
        --sidebar-bg: rgba(22, 22, 35, 0.8);
        --sidebar-width: 260px;
        --main-bg: #f4f7fc;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --primary-color: #8e44ad;
        --primary-glow: rgba(142, 68, 173, 0.5);
        --success-color: #2ecc71;
        --danger-color: #e74c3c;
    }
    body { background-color: var(--main-bg); margin: 0; font-family: 'Poppins', sans-serif; }
    .page-wrapper { display: flex; }
    .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 30px; }
    
    /* Panneaux de contenu */
    .content-panel {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.07);
        margin-bottom: 30px;
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    /* En-tête de page */
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; border-bottom: 1px solid #eef2f7; padding-bottom: 20px; }
    .page-header h1 { font-size: 2.2rem; color: var(--text-dark); margin: 0; }
    .page-header i { font-size: 2rem; color: var(--primary-color); }

    /* Formulaire de filtres */
    .filter-form .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: flex-end; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 0.9rem; }
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 1rem;
        background-color: #f8fafc;
        transition: all .3s;
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right .7em top 50%;
        background-size: .85em auto;
    }
    .form-control:focus { outline: none; border-color: var(--primary-color); background-color: #fff; box-shadow: 0 0 0 3px var(--primary-glow); }
    
    /* Boutons */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; border: none; cursor: pointer; }
    .btn-primary { background: var(--primary-color); color: white; box-shadow: 0 4px 15px var(--primary-glow); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px var(--primary-glow); }
    .btn-danger { background: var(--danger-color); color: white; }
    .btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 6px; }

    /* Tableau des notes */
    .notes-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .notes-table th { padding: 15px 20px; text-align: left; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
    .notes-table td { background: #f8fafc; padding: 15px 20px; vertical-align: middle; }
    .notes-table tbody tr { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .notes-table tbody tr:hover { transform: scale(1.01); box-shadow: 0 8px 25px rgba(0,0,0,0.08); z-index: 2; position: relative; }
    .notes-table td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
    .notes-table td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; text-align: center; }
    .student-name { display: flex; align-items: center; gap: 15px; }
    .student-name .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: grid; place-items: center; font-weight: 600; }
    
    .note-input { width: 70px; text-align: center; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 1.1rem; font-weight: 600; transition: all 0.2s ease; }
    .note-input:focus { border-color: var(--primary-color); outline: none; transform: scale(1.05); }
    .note-input::placeholder { color: #cbd5e1; }

    /* Notifications */
    .notification { padding: 15px 20px; border-radius: 10px; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 600; animation: fadeIn 0.3s; }
    .notification.success { background: linear-gradient(45deg, var(--success-color), #28b463); }
    .notification.error { background: linear-gradient(45deg, var(--danger-color), #c0392b); }
    
    /* Messages d'attente */
    .prompt-message { text-align: center; padding: 50px; }
    .prompt-message i { font-size: 3.5rem; color: var(--primary-color); opacity: 0.3; margin-bottom: 20px; display: inline-block; }
    .prompt-message p { font-size: 1.2rem; color: var(--text-light); }
    
    /* Styles de la Sidebar (copiés du dashboard pour la cohérence) */
    .sidebar{width:var(--sidebar-width);height:100vh;position:fixed;background:var(--sidebar-bg);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-right:1px solid rgba(255,255,255,.1);color:#e2e8f0;display:flex;flex-direction:column;z-index:1000}.sidebar-header{padding:20px 25px;font-size:1.4rem;font-weight:600;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:12px;color:#fff}.sidebar-header i{color:var(--primary-color);text-shadow:0 0 10px var(--primary-glow)}.sidebar-nav{flex-grow:1;margin-top:20px}.sidebar-nav ul{list-style:none;padding:0;margin:0}.nav-link{display:flex;align-items:center;padding:15px 25px;color:#cbd5e1;text-decoration:none;font-size:1rem;transition:all .3s ease;position:relative}.nav-link i{width:20px;margin-right:15px;font-size:1.1rem}.nav-link::before{content:'';position:absolute;left:0;top:0;height:100%;width:4px;background:var(--primary-color);transform:scaleY(0);transition:transform .3s ease,box-shadow .3s ease;border-radius:0 5px 5px 0}.nav-link.active,.nav-link:hover{background:linear-gradient(90deg,rgba(142,68,173,.25),transparent);color:#fff}.nav-link.active::before,.nav-link:hover::before{transform:scaleY(1);box-shadow:0 0 15px var(--primary-glow)}.sidebar-footer{padding:20px 25px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:space-between}.user-profile{display:flex;align-items:center;gap:10px}.user-profile img{width:40px;height:40px;border-radius:50%;border:2px solid var(--primary-color)}.user-info span{font-weight:600}.user-info small{color:#94a3b8}.logout-link{color:#cbd5e1;font-size:1.2rem;transition:color .3s}.logout-link:hover{color:#e74c3c}
</style>

<div class="page-wrapper">
    <!-- Sidebar (partie HTML) -->
    <aside class="sidebar teacher-sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-chalkboard-teacher"></i> Espace Enseignant</h3></div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw"></i> <span>Tableau de Bord</span></a></li>
                <li><a href="notes.php" class="nav-link active"><i class="fas fa-edit fa-fw"></i> <span>Gérer les Notes</span></a></li>
                <li><a href="cours.php" class="nav-link"><i class="fas fa-upload fa-fw"></i> <span>Gérer les Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-alt fa-fw"></i> <span>Mon Emploi du Temps</span></a></li>
                <li><a href="absences.php" class="nav-link"><i class="fas fa-user-check fa-fw"></i> <span>Gérer les Absences</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
             <div class="user-profile"><img src="/etablissement/assets/images/teacher-avatar.png" alt="Avatar"><div class="user-info"><span>Prof. <?= htmlspecialchars($enseignant['nom']) ?></span><small><?= htmlspecialchars($enseignant['specialite']) ?></small></div></div><a href="/etablissement/auth/logout.php" class="logout-link" title="Déconnexion"><i class="fas fa-sign-out-alt fa-fw"></i></a>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Gestion des Notes</h1>
        </div>
        
        <?php if ($notification): ?>
            <div class="notification <?= $notification['type'] ?>"><i class="fas fa-<?= $notification['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($notification['message']) ?></div>
        <?php endif; ?>

        <div class="content-panel filter-form">
            <form action="notes.php" method="GET" id="filterForm">
                <div class="form-row">
                    <div class="form-group"><label for="classe_id">1. Choisir la Classe</label><select name="classe_id" id="classe_id" class="form-control" onchange="document.getElementById('filterForm').submit()"><option value="">-- Sélectionner --</option><?php foreach ($teacher_classes as $class): ?><option value="<?= $class['id'] ?>" <?= $selected_classe_id == $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars($class['niveau'] . ' - ' . $class['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="matiere_id">2. Choisir la Matière</label><select name="matiere_id" id="matiere_id" class="form-control" onchange="document.getElementById('filterForm').submit()"><option value="">-- Sélectionner --</option><?php foreach ($teacher_matieres as $matiere): ?><option value="<?= $matiere['id'] ?>" <?= $selected_matiere_id == $matiere['id'] ? 'selected' : '' ?>><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="trimestre">3. Choisir le Trimestre</label><select name="trimestre" id="trimestre" class="form-control" onchange="document.getElementById('filterForm').submit()"><option value="T1" <?= $selected_trimestre == 'T1' ? 'selected' : '' ?>>Trimestre 1</option><option value="T2" <?= $selected_trimestre == 'T2' ? 'selected' : '' ?>>Trimestre 2</option><option value="T3" <?= $selected_trimestre == 'T3' ? 'selected' : '' ?>>Trimestre 3</option></select></div>
                </div>
            </form>
        </div>

        <?php if (!empty($students_with_notes)): ?>
            <div class="content-panel">
                <form action="notes.php?<?= http_build_query(['classe_id' => $selected_classe_id, 'matiere_id' => $selected_matiere_id, 'trimestre' => $selected_trimestre]) ?>" method="POST">
                    <input type="hidden" name="action" value="save_notes"><input type="hidden" name="classe_id" value="<?= $selected_classe_id ?>"><input type="hidden" name="matiere_id" value="<?= $selected_matiere_id ?>"><input type="hidden" name="trimestre" value="<?= $selected_trimestre ?>">
                    <table class="notes-table">
                        <thead><tr><th>Étudiant</th><th style="width: 150px;">Note / 20</th><th style="width: 100px;">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students_with_notes as $student): ?>
                                <tr>
                                    <td><div class="student-name"><div class="avatar"><?= strtoupper(substr($student['prenom'], 0, 1) . substr($student['nom'], 0, 1)) ?></div><span><?= htmlspecialchars($student['prenom'] . ' ' . $student['nom']) ?></span></div></td>
                                    <td><input type="text" name="notes[<?= $student['etudiant_id'] ?>]" class="note-input" value="<?= htmlspecialchars($student['note'] ?? '') ?>" placeholder="--"></td>
                                    <td><?php if ($student['note_id']): ?><a href="notes.php?action=delete¬e_id=<?= $student['note_id'] ?>&<?= http_build_query($_GET) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette note ?');"><i class="fas fa-trash"></i></a><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 20px;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button></div>
                </form>
            </div>
        <?php elseif ($selected_classe_id && $selected_matiere_id && $selected_trimestre): ?>
             <div class="content-panel prompt-message"><i class="fas fa-users"></i><p>Cette classe ne contient aucun étudiant pour le moment.</p></div>
        <?php else: ?>
             <div class="content-panel prompt-message"><i class="fas fa-filter"></i><p>Veuillez utiliser les filtres ci-dessus pour afficher la grille de notes.</p></div>
        <?php endif; ?>
    </main>
</div>

