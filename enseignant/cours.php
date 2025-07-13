<?php
// DÉBOGAGE ET INITIALISATION
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!isset($_SESSION)) { session_start(); }
if (!isset($_SESSION['user'])) { $_SESSION['user'] = ['id' => 2, 'role' => 'enseignant']; } // Simulation

require_once '../config/db.php';

// AUTHENTIFICATION ET IDENTIFICATION
if ($_SESSION['user']['role'] !== 'enseignant') { die("Accès non autorisé."); }
$user_id = $_SESSION['user']['id'];
$stmt_enseignant = $pdo->prepare("SELECT e.id, u.nom, u.prenom, e.specialite FROM enseignants e JOIN utilisateurs u ON e.utilisateur_id = u.id WHERE u.id = ?");
$stmt_enseignant->execute([$user_id]);
$enseignant = $stmt_enseignant->fetch();
if (!$enseignant) { die("Profil enseignant non trouvé."); }
$enseignant_id = $enseignant['id'];
$page_title = "Gestion des Cours";

// --- LOGIQUE CRUD ---
$notification = null;
$upload_dir = '../uploads/cours/';

// AJOUTER ou MODIFIER un cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $titre = $_POST['titre'];
    $description = $_POST['description'];
    $classe_id = $_POST['classe_id'];
    $matiere_id = $_POST['matiere_id'];
    $cours_id = $_POST['cours_id'] ?? null;
    $current_fichier = $_POST['current_fichier'] ?? '';

    $fichier_nom_db = $current_fichier;
    $type_fichier = $_POST['type_fichier'] ?? 'autre';

    // Gestion du fichier uploadé
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        // Supprimer l'ancien fichier s'il existe et si un nouveau est uploadé
        if (!empty($current_fichier) && file_exists($upload_dir . $current_fichier)) {
            unlink($upload_dir . $current_fichier);
        }
        
        $file_name = uniqid() . '-' . basename($_FILES['fichier']['name']);
        $file_path = $upload_dir . $file_name;
        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $file_path)) {
            $fichier_nom_db = $file_name;
            if (in_array($file_ext, ['pdf'])) $type_fichier = 'pdf';
            elseif (in_array($file_ext, ['doc', 'docx'])) $type_fichier = 'word';
            else $type_fichier = 'autre';
        } else {
            $notification = ['type' => 'error', 'message' => 'Erreur lors du téléversement du fichier.'];
        }
    }

    if (!$notification) {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO cours (titre, description, fichier, type_fichier, enseignant_id, classe_id, matiere_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $description, $fichier_nom_db, $type_fichier, $enseignant_id, $classe_id, $matiere_id]);
            $notification = ['type' => 'success', 'message' => 'Cours ajouté avec succès !'];
        } elseif ($_POST['action'] === 'edit' && $cours_id) {
            $sql = "UPDATE cours SET titre = ?, description = ?, fichier = ?, type_fichier = ?, classe_id = ?, matiere_id = ? WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $description, $fichier_nom_db, $type_fichier, $classe_id, $matiere_id, $cours_id, $enseignant_id]);
            $notification = ['type' => 'success', 'message' => 'Cours mis à jour avec succès !'];
        }
    }
}

// SUPPRIMER un cours
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $cours_id_to_delete = $_GET['id'];
    
    // D'abord, récupérer le nom du fichier pour le supprimer du serveur
    $stmt_file = $pdo->prepare("SELECT fichier FROM cours WHERE id = ? AND enseignant_id = ?");
    $stmt_file->execute([$cours_id_to_delete, $enseignant_id]);
    $file_to_delete = $stmt_file->fetchColumn();

    // Ensuite, supprimer de la BDD
    $stmt_delete = $pdo->prepare("DELETE FROM cours WHERE id = ? AND enseignant_id = ?");
    $stmt_delete->execute([$cours_id_to_delete, $enseignant_id]);

    if ($stmt_delete->rowCount() > 0) {
        if ($file_to_delete && file_exists($upload_dir . $file_to_delete)) {
            unlink($upload_dir . $file_to_delete);
        }
        $notification = ['type' => 'success', 'message' => 'Cours supprimé avec succès.'];
    } else {
        $notification = ['type' => 'error', 'message' => 'Erreur lors de la suppression.'];
    }
}


// --- RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE ---
// Tous les cours de l'enseignant
$stmt_cours = $pdo->prepare("
    SELECT c.*, cl.nom as classe_nom, m.nom as matiere_nom
    FROM cours c
    JOIN classes cl ON c.classe_id = cl.id
    JOIN matieres m ON c.matiere_id = m.id
    WHERE c.enseignant_id = ?
    ORDER BY c.date_ajout DESC
");
$stmt_cours->execute([$enseignant_id]);
$teacher_courses = $stmt_cours->fetchAll();

// Classes et matières de l'enseignant pour les formulaires
$stmt_classes = $pdo->prepare("SELECT DISTINCT c.id, c.nom, c.niveau FROM classes c JOIN emplois_du_temps edt ON c.id = edt.classe_id WHERE edt.enseignant_id = ? ORDER BY c.niveau, c.nom");
$stmt_classes->execute([$enseignant_id]);
$teacher_classes = $stmt_classes->fetchAll();

$stmt_matieres = $pdo->prepare("SELECT DISTINCT m.id, m.nom FROM matieres m JOIN emplois_du_temps edt ON m.id = edt.matiere_id WHERE edt.enseignant_id = ? ORDER BY m.nom");
$stmt_matieres->execute([$enseignant_id]);
$teacher_matieres = $stmt_matieres->fetchAll();


?>

<!-- ================== STYLES CSS DESIGN ================== -->
<style>
    /* Intégration du thème du Dashboard */
    :root{--sidebar-bg:rgba(22,22,35,.8);--sidebar-width:260px;--main-bg:#f4f7fc;--card-bg:#fff;--text-dark:#1e293b;--text-light:#64748b;--primary-color:#8e44ad;--primary-glow:rgba(142,68,173,.5);--success-color:#2ecc71;--danger-color:#e74c3c;--pdf-color:#e74c3c;--word-color:#2980b9;--other-color:#7f8c8d;}
    body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;}
    .page-wrapper{display:flex;}
    .main-content{margin-left:var(--sidebar-width);flex-grow:1;padding:30px;}
    .content-panel{background:var(--card-bg);border-radius:15px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,.07);margin-bottom:30px;animation:fadeIn .5s ease-out;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;border-bottom:1px solid #eef2f7;padding-bottom:20px;}
    .page-header h1{font-size:2.2rem;color:var(--text-dark);margin:0;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;border:none;cursor:pointer;}
    .btn-primary{background:var(--primary-color);color:#fff;box-shadow:0 4px 15px var(--primary-glow);}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px var(--primary-glow);}
    .btn-danger{background:var(--danger-color);color:#fff;}
    .btn-sm{padding:6px 12px;font-size:.85rem;border-radius:6px;}
    .notification{padding:15px 20px;border-radius:10px;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;animation:fadeIn .3s;}
    .notification.success{background:linear-gradient(45deg,var(--success-color),#28b463);}
    .notification.error{background:linear-gradient(45deg,var(--danger-color),#c0392b);}
    .prompt-message{text-align:center;padding:50px;}.prompt-message i{font-size:3.5rem;color:var(--primary-color);opacity:.3;margin-bottom:20px;display:inline-block;}.prompt-message p{font-size:1.2rem;color:var(--text-light);}
    /* Grille des cours */
    .courses-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:25px;}
    .course-card{display:flex;flex-direction:column;background:var(--card-bg);border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,.05);overflow:hidden;transition:transform .3s,box-shadow .3s;}.course-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,.1);}
    .card-header{display:flex;align-items:center;gap:15px;padding:20px;border-bottom:1px solid #eef2f7;}
    .file-icon{font-size:2rem;width:50px;text-align:center;}.file-icon.pdf{color:var(--pdf-color);}.file-icon.word{color:var(--word-color);}.file-icon.autre{color:var(--other-color);}
    .card-title h3{margin:0;font-size:1.2rem;color:var(--text-dark);}.card-title p{margin:5px 0 0;font-size:.9rem;color:var(--text-light);}
    .card-body{padding:20px;flex-grow:1;color:var(--text-light);}.card-body p{margin-top:0;}
    .card-footer{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;background:#f8fafc;}
    .card-date{font-size:.85rem;color:var(--text-light);}
    .card-actions{display:flex;gap:10px;}
    /* Modal */
    .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);backdrop-filter:blur(5px);animation:fadeIn .3s;}
    .modal-content{position:relative;background:var(--card-bg);margin:10% auto;padding:35px;border-radius:15px;width:90%;max-width:600px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:slideIn .4s ease-out;}
    @keyframes slideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}
    .close-btn{color:#aaa;float:right;font-size:28px;font-weight:700;cursor:pointer;line-height:1;}.close-btn:hover,.close-btn:focus{color:var(--text-dark);text-decoration:none;}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}.form-group.full-width{grid-column:1 / -1;}
    .form-group label{display:block;margin-bottom:8px;font-weight:600;color:#475569;font-size:.9rem;}
    .form-control{width:100%;padding:12px 15px;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;background-color:#f8fafc;transition:all .3s;}
    textarea.form-control{min-height:120px;resize:vertical;}
    .form-control:focus{outline:none;border-color:var(--primary-color);background-color:#fff;box-shadow:0 0 0 3px var(--primary-glow);}
    /* Sidebar */
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
                <li><a href="cours.php" class="nav-link active"><i class="fas fa-upload fa-fw"></i> <span>Gérer les Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-alt fa-fw"></i> <span>Mon Emploi du Temps</span></a></li>
                <li><a href="absences.php" class="nav-link"><i class="fas fa-user-check fa-fw"></i> <span>Gérer les Absences</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer"><div class="user-profile"><img src="/etablissement/assets/images/teacher-avatar.png" alt="Avatar"><div class="user-info"><span>Prof. <?= htmlspecialchars($enseignant['nom']) ?></span><small><?= htmlspecialchars($enseignant['specialite']) ?></small></div></div><a href="/etablissement/auth/logout.php" class="logout-link" title="Déconnexion"><i class="fas fa-sign-out-alt fa-fw"></i></a></div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-book-open"></i> Mes Cours Déposés</h1>
            <button class="btn btn-primary" id="addCourseBtn"><i class="fas fa-plus"></i> Ajouter un cours</button>
        </div>
        
        <?php if ($notification): ?>
            <div class="notification <?= $notification['type'] ?>"><i class="fas fa-<?= $notification['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($notification['message']) ?></div>
        <?php endif; ?>

        <?php if (empty($teacher_courses)): ?>
            <div class="content-panel prompt-message">
                <i class="fas fa-folder-open"></i>
                <p>Vous n'avez déposé aucun cours pour le moment. <br>Cliquez sur "Ajouter un cours" pour commencer.</p>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($teacher_courses as $course): ?>
                    <div class="course-card">
                        <div class="card-header">
                            <div class="file-icon <?= $course['type_fichier'] ?>">
                                <i class="fas fa-file-<?= $course['type_fichier'] === 'pdf' ? 'pdf' : ($course['type_fichier'] === 'word' ? 'word' : 'alt') ?>"></i>
                            </div>
                            <div class="card-title">
                                <h3><?= htmlspecialchars($course['titre']) ?></h3>
                                <p><?= htmlspecialchars($course['classe_nom']) ?> - <?= htmlspecialchars($course['matiere_nom']) ?></p>
                            </div>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                        </div>
                        <div class="card-footer">
                            <span class="card-date"><i class="fas fa-calendar-alt"></i> Ajouté le <?= date('d/m/Y', strtotime($course['date_ajout'])) ?></span>
                            <div class="card-actions">
                                <button class="btn btn-sm editCourseBtn" 
                                        data-id="<?= $course['id'] ?>" 
                                        data-titre="<?= htmlspecialchars($course['titre']) ?>"
                                        data-description="<?= htmlspecialchars($course['description']) ?>"
                                        data-classe_id="<?= $course['classe_id'] ?>"
                                        data-matiere_id="<?= $course['matiere_id'] ?>"
                                        data-fichier="<?= htmlspecialchars($course['fichier']) ?>"
                                        style="background: #3498db;"><i class="fas fa-pencil-alt"></i></button>
                                <a href="cours.php?action=delete&id=<?= $course['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Voulez-vous vraiment supprimer ce cours ?\nLe fichier associé sera également effacé.');"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal pour Ajouter/Modifier un cours -->
<div id="courseModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2 id="modalTitle">Ajouter un nouveau cours</h2>
        <form action="cours.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="cours_id" id="cours_id" value="">
            <input type="hidden" name="current_fichier" id="current_fichier" value="">

            <div class="form-group full-width">
                <label for="titre">Titre du cours</label>
                <input type="text" name="titre" id="titre" class="form-control" required>
            </div>
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control"></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="classe_id">Classe</label>
                    <select name="classe_id" id="classe_id" class="form-control" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach($teacher_classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['niveau'] . ' - ' . $class['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="matiere_id">Matière</label>
                    <select name="matiere_id" id="matiere_id" class="form-control" required>
                         <option value="">-- Choisir --</option>
                        <?php foreach($teacher_matieres as $matiere): ?>
                            <option value="<?= $matiere['id'] ?>"><?= htmlspecialchars($matiere['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group full-width">
                <label for="fichier">Fichier du cours (PDF, Word, etc.)</label>
                <input type="file" name="fichier" id="fichier" class="form-control">
                <small id="fileInfo" style="color:var(--text-light); margin-top:5px; display:block;"></small>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="submit" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('courseModal');
    const addBtn = document.getElementById('addCourseBtn');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.editCourseBtn');

    // Form elements
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const coursIdInput = document.getElementById('cours_id');
    const currentFichierInput = document.getElementById('current_fichier');
    const titreInput = document.getElementById('titre');
    const descriptionInput = document.getElementById('description');
    const classeSelect = document.getElementById('classe_id');
    const matiereSelect = document.getElementById('matiere_id');
    const fileInput = document.getElementById('fichier');
    const fileInfo = document.getElementById('fileInfo');
    const saveBtn = document.getElementById('saveBtn');

    function openModal() { modal.style.display = 'block'; }
    function closeModal() { modal.style.display = 'none'; }

    addBtn.onclick = function() {
        modalTitle.innerText = 'Ajouter un nouveau cours';
        formAction.value = 'add';
        coursIdInput.value = '';
        currentFichierInput.value = '';
        document.querySelector('#courseModal form').reset();
        fileInfo.innerText = "L'ajout d'un fichier est requis pour un nouveau cours.";
        fileInput.required = true;
        saveBtn.innerHTML = '<i class="fas fa-plus"></i> Ajouter le cours';
        openModal();
    }

    editBtns.forEach(btn => {
        btn.onclick = function() {
            modalTitle.innerText = 'Modifier le cours';
            formAction.value = 'edit';
            
            coursIdInput.value = this.dataset.id;
            titreInput.value = this.dataset.titre;
            descriptionInput.value = this.dataset.description;
            classeSelect.value = this.dataset.classe_id;
            matiereSelect.value = this.dataset.matiere_id;
            currentFichierInput.value = this.dataset.fichier;
            
            fileInfo.innerText = `Fichier actuel : ${this.dataset.fichier}. Laissez vide pour ne pas changer.`;
            fileInput.required = false;

            saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer les modifications';
            openModal();
        }
    });

    closeBtn.onclick = closeModal;
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
});
</script>

