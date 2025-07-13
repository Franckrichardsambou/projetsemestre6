<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin'); // Vérifie que seul l'admin peut accéder

$page_title = "Gestion des Étudiants";
$notification = null;

// --- LOGIQUE CRUD ---

// AJOUTER ou MODIFIER un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $classe_id = $_POST['classe_id'];
    $matricule = trim($_POST['matricule']);
    $statut = $_POST['statut'];
    $etudiant_id = $_POST['etudiant_id'] ?? null;
    $utilisateur_id = $_POST['utilisateur_id'] ?? null;

    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'add') {
            // 1. Créer l'utilisateur
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, statut) VALUES (?, ?, ?, ?, 'etudiant', ?)");
            $stmt_user->execute([$nom, $prenom, $email, $hashed_password, $statut]);
            $new_user_id = $pdo->lastInsertId();
            
            // 2. Créer l'étudiant
            $stmt_etudiant = $pdo->prepare("INSERT INTO etudiants (utilisateur_id, classe_id, matricule) VALUES (?, ?, ?)");
            $stmt_etudiant->execute([$new_user_id, $classe_id, $matricule]);
            $notification = ['type' => 'success', 'message' => 'Étudiant ajouté avec succès.'];
        } 
        elseif ($_POST['action'] === 'edit' && $etudiant_id && $utilisateur_id) {
            // 1. Mettre à jour l'utilisateur
            $sql_user = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, statut = ?";
            $params_user = [$nom, $prenom, $email, $statut];
            if (!empty($mot_de_passe)) {
                $sql_user .= ", mot_de_passe = ?";
                $params_user[] = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            }
            $sql_user .= " WHERE id = ?";
            $params_user[] = $utilisateur_id;
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute($params_user);

            // 2. Mettre à jour l'étudiant
            $stmt_etudiant = $pdo->prepare("UPDATE etudiants SET classe_id = ?, matricule = ? WHERE id = ?");
            $stmt_etudiant->execute([$classe_id, $matricule, $etudiant_id]);
            $notification = ['type' => 'success', 'message' => 'Informations de l\'étudiant mises à jour.'];
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) { // Code pour violation de contrainte d'unicité
            $notification = ['type' => 'error', 'message' => 'Erreur : Cet email ou ce matricule est déjà utilisé.'];
        } else {
            $notification = ['type' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()];
        }
    }
}

// SUPPRIMER un étudiant
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $etudiant_id_to_delete = $_GET['id'];
    
    // On doit supprimer l'étudiant et son compte utilisateur.
    // Il faut d'abord récupérer l'ID de l'utilisateur.
    $stmt_user_id = $pdo->prepare("SELECT utilisateur_id FROM etudiants WHERE id = ?");
    $stmt_user_id->execute([$etudiant_id_to_delete]);
    $user_id_to_delete = $stmt_user_id->fetchColumn();

    if ($user_id_to_delete) {
        try {
            $pdo->beginTransaction();
            // Supprimer les dépendances (notes, absences, etc.)
            $pdo->prepare("DELETE FROM notes WHERE etudiant_id = ?")->execute([$etudiant_id_to_delete]);
            $pdo->prepare("DELETE FROM absences WHERE etudiant_id = ?")->execute([$etudiant_id_to_delete]);
            // ... autres dépendances (délibérations, etc.)
            
            // Supprimer l'entrée étudiant
            $pdo->prepare("DELETE FROM etudiants WHERE id = ?")->execute([$etudiant_id_to_delete]);
            // Supprimer l'entrée utilisateur
            $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$user_id_to_delete]);
            
            $pdo->commit();
            $notification = ['type' => 'success', 'message' => 'Étudiant et son compte supprimés.'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $notification = ['type' => 'error', 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
        }
    }
}


// --- RÉCUPÉRATION DES DONNÉES POUR AFFICHAGE ---
// Filtre par classe
$filter_classe_id = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : null;

$sql = "
    SELECT e.id, e.matricule, u.nom, u.prenom, u.email, u.statut, c.nom AS classe_nom, e.utilisateur_id
    FROM etudiants e
    JOIN utilisateurs u ON e.utilisateur_id = u.id
    JOIN classes c ON e.classe_id = c.id
";
if ($filter_classe_id) {
    $sql .= " WHERE e.classe_id = ?";
}
$sql .= " ORDER BY u.nom, u.prenom";
$stmt = $pdo->prepare($sql);
if ($filter_classe_id) {
    $stmt->execute([$filter_classe_id]);
} else {
    $stmt->execute();
}
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des classes pour les filtres et formulaires
$classes = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Le style est directement intégré, basé sur celui du dashboard -->
    <style>
        :root {--header-bg:linear-gradient(90deg, #0d6efd, #6610f2);--sidebar-bg:#fff;--main-bg:#f0f4f8;--card-bg:#fff;--text-dark:#343a40;--text-light:#6c757d;--primary-color:#0d6efd;}
        body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;color:var(--text-dark);}
        .dashboard-container{display:flex;}
        .sidebar{width:250px;background:var(--sidebar-bg);color:#333;min-height:100vh;padding:20px;box-shadow:2px 0 15px rgba(0,0,0,.05);position:fixed;top:0;left:0;}
        .sidebar .admin-header{text-align:center;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid #eee;}
        .sidebar .admin-header i{font-size:30px;color:var(--primary-color);margin-bottom:10px;}
        .sidebar .admin-header h2{margin:0;font-size:22px;color:var(--text-dark);}
        .sidebar ul{list-style:none;padding:0;}.sidebar ul li{margin:10px 0;}.sidebar ul li a{color:var(--text-light);text-decoration:none;font-weight:500;display:flex;align-items:center;padding:10px 15px;border-radius:8px;transition:all .3s ease;}
        .sidebar ul li a i{margin-right:10px;width:20px;text-align:center;}.sidebar ul li a.active,.sidebar ul li a:hover{background:var(--primary-color);color:#fff;}
        .main-content{margin-left:280px;padding:30px;width:calc(100% - 250px);}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}.page-header h1{font-size:2rem;color:var(--text-dark);margin:0;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s ease;border:none;cursor:pointer;}
        .btn-primary{background:var(--primary-color);color:#fff;}.btn-primary:hover{background:#0b5ed7;}
        .btn-danger{background:#dc3545;color:#fff;}.btn-danger:hover{background:#c82333;}
        .btn-info{background:#0dcaf0;color:#fff;}.btn-info:hover{background:#0aa9c4;}
        .btn-sm{padding:6px 12px;font-size:.85rem;border-radius:6px;}
        .table-container{background:var(--card-bg);padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        table{width:100%;border-collapse:collapse;}table th,table td{padding:12px 15px;text-align:left;border-bottom:1px solid #dee2e6;}
        table thead th{background-color:#f8f9fa;font-weight:600;color:var(--text-light);}tbody tr:hover{background-color:#f1f5f9;}
        .status-badge{padding:4px 8px;border-radius:20px;font-size:.8rem;font-weight:600;}.status-badge.actif{background:#d1e7dd;color:#0f5132;}.status-badge.inactif{background:#f8d7da;color:#842029;}
        .action-btns{display:flex;gap:10px;}
        .filter-bar{background:var(--card-bg);padding:20px;border-radius:12px;margin-bottom:25px;display:flex;gap:20px;align-items:center;}
        .filter-bar label{font-weight:600;}.filter-bar select{padding:8px 12px;border-radius:8px;border:1px solid #ccc;}
        .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);backdrop-filter:blur(5px);animation:fadeIn .3s;}
        .modal-content{position:relative;background:var(--card-bg);margin:5% auto;padding:35px;border-radius:15px;width:90%;max-width:600px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:slideIn .4s ease-out;}
        @keyframes slideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}
        .close-btn{color:#aaa;float:right;font-size:28px;font-weight:700;cursor:pointer;line-height:1;}.close-btn:hover,.close-btn:focus{color:var(--text-dark);text-decoration:none;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}.form-group.full-width{grid-column:1 / -1;}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;}
        .form-control{width:100%;padding:12px 15px;border:1px solid #ccc;border-radius:8px;font-size:1rem;}
        .notification{padding:15px;border-radius:8px;color:#fff;margin-bottom:20px;}.notification.success{background:#198754;}.notification.error{background:#dc3545;}
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="admin-header">
            <i class="fas fa-user-shield"></i>
            <h2>Admin Panel</h2>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="etudiants.php" class="active"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="enseignants.php"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a></li>
            <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="matieres.php"><i class="fas fa-book"></i> Matières</a></li>
            <!-- ... autres liens ... -->
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Étudiants</h1>
            <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> Ajouter un étudiant</button>
        </div>

        <?php if ($notification): ?>
            <div class="notification <?= $notification['type'] ?>">
                <?= htmlspecialchars($notification['message']) ?>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <label for="filter_classe">Filtrer par classe :</label>
            <select id="filter_classe" onchange="window.location.href = this.value;">
                <option value="etudiants.php">Toutes les classes</option>
                <?php foreach ($classes as $classe): ?>
                    <option value="etudiants.php?classe_id=<?= $classe['id'] ?>" <?= ($filter_classe_id == $classe['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom Complet</th>
                        <th>Email</th>
                        <th>Matricule</th>
                        <th>Classe</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($etudiants)): ?>
                        <tr><td colspan="6" style="text-align:center;">Aucun étudiant trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($etudiants as $etudiant): ?>
                            <tr>
                                <td><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></td>
                                <td><?= htmlspecialchars($etudiant['email']) ?></td>
                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                <td><?= htmlspecialchars($etudiant['classe_nom']) ?></td>
                                <td><span class="status-badge <?= $etudiant['statut'] ?>"><?= ucfirst($etudiant['statut']) ?></span></td>
                                <td class="action-btns">
                                    <button class="btn btn-sm btn-info editBtn"
                                            data-id="<?= $etudiant['id'] ?>"
                                            data-utilisateur_id="<?= $etudiant['utilisateur_id'] ?>"
                                            data-nom="<?= htmlspecialchars($etudiant['nom']) ?>"
                                            data-prenom="<?= htmlspecialchars($etudiant['prenom']) ?>"
                                            data-email="<?= htmlspecialchars($etudiant['email']) ?>"
                                            data-matricule="<?= htmlspecialchars($etudiant['matricule']) ?>"
                                            data-classe_id="<?= array_search($etudiant['classe_nom'], array_column($classes, 'nom', 'id')) ?>"
                                            data-statut="<?= $etudiant['statut'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="etudiants.php?action=delete&id=<?= $etudiant['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Voulez-vous vraiment supprimer cet étudiant et son compte ? Cette action est irréversible.');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal pour Ajouter/Modifier -->
<div id="formModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2 id="modalTitle">Ajouter un étudiant</h2>
        <form action="etudiants.php" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="etudiant_id" id="etudiant_id">
            <input type="hidden" name="utilisateur_id" id="utilisateur_id">

            <div class="form-grid">
                <div class="form-group"><label for="prenom">Prénom</label><input type="text" name="prenom" id="prenom" class="form-control" required></div>
                <div class="form-group"><label for="nom">Nom</label><input type="text" name="nom" id="nom" class="form-control" required></div>
                <div class="form-group"><label for="email">Email</label><input type="email" name="email" id="email" class="form-control" required></div>
                <div class="form-group"><label for="mot_de_passe">Mot de passe</label><input type="password" name="mot_de_passe" id="mot_de_passe" class="form-control"><small>Laissez vide pour ne pas changer</small></div>
                <div class="form-group"><label for="matricule">Matricule</label><input type="text" name="matricule" id="matricule" class="form-control" required></div>
                <div class="form-group"><label for="classe_id">Classe</label><select name="classe_id" id="classe_id" class="form-control" required><option value="">-- Choisir une classe --</option><?php foreach ($classes as $classe): ?><option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group full-width"><label for="statut">Statut</label><select name="statut" id="statut" class="form-control" required><option value="actif">Actif</option><option value="inactif">Inactif</option></select></div>
            </div>
            <div style="text-align:right; margin-top:20px;"><button type="submit" class="btn btn-primary" id="saveBtn">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('formModal');
    const addBtn = document.getElementById('addBtn');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.editBtn');

    // Form elements
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('formAction');
    const etudiantIdInput = document.getElementById('etudiant_id');
    const utilisateurIdInput = document.getElementById('utilisateur_id');
    const nomInput = document.getElementById('nom');
    const prenomInput = document.getElementById('prenom');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('mot_de_passe');
    const matriculeInput = document.getElementById('matricule');
    const classeSelect = document.getElementById('classe_id');
    const statutSelect = document.getElementById('statut');
    const saveBtn = document.getElementById('saveBtn');

    function openModal() { modal.style.display = 'block'; }
    function closeModal() { modal.style.display = 'none'; }

    addBtn.onclick = function() {
        modalTitle.innerText = 'Ajouter un étudiant';
        form.reset();
        formAction.value = 'add';
        etudiantIdInput.value = '';
        utilisateurIdInput.value = '';
        passwordInput.required = true;
        saveBtn.innerText = 'Ajouter';
        openModal();
    }

    editBtns.forEach(btn => {
        btn.onclick = function() {
            modalTitle.innerText = 'Modifier l\'étudiant';
            form.reset();
            formAction.value = 'edit';
            etudiantIdInput.value = this.dataset.id;
            utilisateurIdInput.value = this.dataset.utilisateur_id;
            nomInput.value = this.dataset.nom;
            prenomInput.value = this.dataset.prenom;
            emailInput.value = this.dataset.email;
            matriculeInput.value = this.dataset.matricule;
            classeSelect.value = this.dataset.classe_id;
            statutSelect.value = this.dataset.statut;
            passwordInput.required = false;
            saveBtn.innerText = 'Enregistrer les modifications';
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

</body>
</html>