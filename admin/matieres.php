<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');

$page_title = "Gestion des Matières";
$notification = null;

// --- LOGIQUE CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $nom = trim($_POST['nom']);
    $coefficient = filter_var($_POST['coefficient'], FILTER_VALIDATE_INT);
    $matiere_id = $_POST['matiere_id'] ?? null;

    if ($coefficient === false || $coefficient < 1) {
        $notification = ['type' => 'error', 'message' => 'Le coefficient doit être un nombre entier positif.'];
    } else {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO matieres (nom, coefficient) VALUES (?, ?)");
            $stmt->execute([$nom, $coefficient]);
            $notification = ['type' => 'success', 'message' => 'Matière ajoutée avec succès.'];
        } 
        elseif ($_POST['action'] === 'edit' && $matiere_id) {
            $stmt = $pdo->prepare("UPDATE matieres SET nom = ?, coefficient = ? WHERE id = ?");
            $stmt->execute([$nom, $coefficient, $matiere_id]);
            $notification = ['type' => 'success', 'message' => 'Matière mise à jour avec succès.'];
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $matiere_id_to_delete = $_GET['id'];
    
    // Vérifier si la matière est utilisée dans l'emploi du temps ou les notes
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM emplois_du_temps WHERE matiere_id = ?");
    $stmt_check->execute([$matiere_id_to_delete]);
    $in_emploi = $stmt_check->fetchColumn() > 0;

    $stmt_check_notes = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE matiere_id = ?");
    $stmt_check_notes->execute([$matiere_id_to_delete]);
    $in_notes = $stmt_check_notes->fetchColumn() > 0;

    if ($in_emploi || $in_notes) {
        $notification = ['type' => 'error', 'message' => 'Impossible de supprimer cette matière, elle est déjà utilisée dans les notes ou les emplois du temps.'];
    } else {
        $stmt = $pdo->prepare("DELETE FROM matieres WHERE id = ?");
        $stmt->execute([$matiere_id_to_delete]);
        $notification = ['type' => 'success', 'message' => 'Matière supprimée avec succès.'];
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$stmt = $pdo->query("SELECT * FROM matieres ORDER BY nom");
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .btn-primary{background:var(--primary-color);color:var(--white);box-shadow:0 4px 15px rgba(79,70,229,.2);}.btn-primary:hover{transform:translateY(-2px);background:#4338ca;box-shadow:0 6px 20px rgba(79,70,229,.4);}
        /* Grille des matières */
        .matieres-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:25px;}
        .matiere-card{position:relative;background:var(--white);border-radius:15px;padding:25px;box-shadow:0 10px 30px rgba(0,0,0,.07);transition:transform .3s ease, box-shadow .3s ease;animation:pop-in .5s ease-out backwards;display:flex;align-items:center;gap:20px;}
        .matiere-card:hover{transform:translateY(-8px);box-shadow:0 15px 40px rgba(0,0,0,.12);}
        @keyframes pop-in { from { opacity:0; transform:scale(.9); } to { opacity:1; transform:scale(1); }}
        .card-icon{font-size:2rem;color:var(--white);width:60px;height:60px;border-radius:12px;display:grid;place-items:center;flex-shrink:0;}
        .card-details h3{margin:0 0 5px;font-size:1.3rem;font-weight:600;color:var(--text-dark);}.card-details p{margin:0;color:var(--text-light);}
        .card-actions{display:flex;gap:8px;margin-left:auto;}
        .card-actions .btn{background:transparent;color:var(--text-light);padding:8px;border-radius:50%;width:36px;height:36px;}.card-actions .btn:hover{background:var(--primary-light);color:var(--primary-color);}
        /* Modal & Form */
        .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);backdrop-filter:blur(5px);animation:fadeIn .3s;}
        .modal-content{position:relative;background:var(--white);margin:10% auto;padding:35px;border-radius:15px;width:90%;max-width:500px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:slideIn .4s ease-out;}
        @keyframes slideIn{from{transform:translateY(-50px);opacity:0}to{transform:translateY(0);opacity:1}}
        .close-btn{color:#aaa;float:right;font-size:28px;font-weight:700;cursor:pointer;line-height:1;}.close-btn:hover,.close-btn:focus{color:var(--text-dark);text-decoration:none;}
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;}.form-control{width:100%;padding:12px 15px;border:1px solid #ccc;border-radius:8px;font-size:1rem;}.form-control:focus{border-color:var(--primary-color);outline:none;box-shadow:0 0 0 3px var(--primary-light);}
        .notification{padding:15px;border-radius:8px;color:#fff;margin-bottom:20px;}.notification.success{background:var(--success-color);}.notification.error{background:var(--danger-color);}
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="admin-header"><i class="fas fa-user-shield"></i><h2>Admin Panel</h2></div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="etudiants.php"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
            <li><a href="enseignants.php"><i class="fas fa-chalkboard-teacher"></i> Enseignants</a></li>
            <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="matieres.php" class="active"><i class="fas fa-book"></i> Matières</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Matières</h1>
            <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> Ajouter une matière</button>
        </div>

        <?php if ($notification): ?>
            <div class="notification <?= $notification['type'] ?>"><?= htmlspecialchars($notification['message']) ?></div>
        <?php endif; ?>

        <div class="matieres-grid">
            <?php if (empty($matieres)): ?>
                <p>Aucune matière n'a été créée pour le moment.</p>
            <?php else: ?>
                <?php 
                $colors = ['#4f46e5', '#db2777', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6'];
                $icons = ['fa-calculator', 'fa-flask', 'fa-globe-europe', 'fa-history', 'fa-paint-brush', 'fa-music'];
                $i = 0;
                foreach ($matieres as $matiere): 
                    $color = $colors[$i % count($colors)];
                    $icon = $icons[$i % count($icons)];
                    $i++;
                ?>
                    <div class="matiere-card" style="animation-delay: <?= $i * 80 ?>ms">
                        <div class="card-icon" style="background-color: <?= $color ?>;">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="card-details">
                            <h3><?= htmlspecialchars($matiere['nom']) ?></h3>
                            <p>Coefficient : <strong><?= htmlspecialchars($matiere['coefficient']) ?></strong></p>
                        </div>
                        <div class="card-actions">
                             <button class="btn btn-sm editBtn"
                                    data-id="<?= $matiere['id'] ?>"
                                    data-nom="<?= htmlspecialchars($matiere['nom']) ?>"
                                    data-coefficient="<?= htmlspecialchars($matiere['coefficient']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="matieres.php?action=delete&id=<?= $matiere['id'] ?>" class="btn btn-sm" onclick="return confirm('Voulez-vous vraiment supprimer cette matière ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal pour Ajouter/Modifier -->
<div id="formModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2 id="modalTitle">Ajouter une matière</h2>
        <form action="matieres.php" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="matiere_id" id="matiere_id">
            <div class="form-group">
                <label for="nom">Nom de la matière (ex: Mathématiques)</label>
                <input type="text" name="nom" id="nom" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="coefficient">Coefficient</label>
                <input type="number" name="coefficient" id="coefficient" class="form-control" required min="1">
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="submit" class="btn btn-primary" id="saveBtn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('formModal');
    const addBtn = document.getElementById('addBtn');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.editBtn');
    
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('formAction');
    const matiereIdInput = document.getElementById('matiere_id');
    const nomInput = document.getElementById('nom');
    const coefficientInput = document.getElementById('coefficient');
    const saveBtn = document.getElementById('saveBtn');

    function openModal() { modal.style.display = 'block'; }
    function closeModal() { modal.style.display = 'none'; }

    addBtn.onclick = function() {
        modalTitle.innerText = 'Ajouter une nouvelle matière';
        form.reset();
        formAction.value = 'add';
        matiereIdInput.value = '';
        saveBtn.innerText = 'Ajouter';
        openModal();
    }

    editBtns.forEach(btn => {
        btn.onclick = function() {
            modalTitle.innerText = 'Modifier la matière';
            form.reset();
            formAction.value = 'edit';
            matiereIdInput.value = this.dataset.id;
            nomInput.value = this.dataset.nom;
            coefficientInput.value = this.dataset.coefficient;
            saveBtn.innerText = 'Enregistrer les modifications';
            openModal();
        }
    });

    closeBtn.onclick = closeModal;
    window.onclick = function(event) { if (event.target == modal) { closeModal(); } }
});
</script>

</body>
</html>