<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');

$page_title = "Gestion des Classes";
$notification = null;

// --- LOGIQUE CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $nom = trim($_POST['nom']);
    $niveau = trim($_POST['niveau']);
    $annee_scolaire = trim($_POST['annee_scolaire']);
    $classe_id = $_POST['classe_id'] ?? null;

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO classes (nom, niveau, annee_scolaire) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $niveau, $annee_scolaire]);
        $notification = ['type' => 'success', 'message' => 'Classe ajoutée avec succès.'];
    } 
    elseif ($_POST['action'] === 'edit' && $classe_id) {
        $stmt = $pdo->prepare("UPDATE classes SET nom = ?, niveau = ?, annee_scolaire = ? WHERE id = ?");
        $stmt->execute([$nom, $niveau, $annee_scolaire, $classe_id]);
        $notification = ['type' => 'success', 'message' => 'Classe mise à jour avec succès.'];
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $classe_id_to_delete = $_GET['id'];
    // Avant de supprimer, on vérifie si des étudiants sont liés à cette classe
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE classe_id = ?");
    $stmt_check->execute([$classe_id_to_delete]);
    if ($stmt_check->fetchColumn() > 0) {
        $notification = ['type' => 'error', 'message' => 'Impossible de supprimer cette classe, des étudiants y sont inscrits.'];
    } else {
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$classe_id_to_delete]);
        $notification = ['type' => 'success', 'message' => 'Classe supprimée avec succès.'];
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
// On récupère les classes ET le nombre d'étudiants dans chacune
$stmt = $pdo->query("
    SELECT c.*, COUNT(e.id) AS student_count
    FROM classes c
    LEFT JOIN etudiants e ON c.id = e.classe_id
    GROUP BY c.id
    ORDER BY c.niveau, c.nom
");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- ================== NOUVEAU DESIGN CSS ================== -->
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
        .main-content{margin-left:280px;padding:30px;width:calc(100% - 250px);}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
        .page-header h1{font-size:2.5rem;font-weight:700;color:var(--text-dark);margin:0;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s ease;border:none;cursor:pointer;}
        .btn-primary{background:var(--primary-color);color:var(--white);box-shadow:0 4px 15px rgba(79,70,229,.2);}.btn-primary:hover{transform:translateY(-2px);background:#4338ca;box-shadow:0 6px 20px rgba(79,70,229,.4);}
        /* Grille des classes */
        .classes-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:25px;}
        .class-card{position:relative;background:var(--white);border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.07);transition:transform .3s ease, box-shadow .3s ease;animation:pop-in .5s ease-out backwards;}
        .class-card:hover{transform:translateY(-8px);box-shadow:0 15px 40px rgba(0,0,0,.12);}
        .card-header{padding:20px;color:var(--white);background:linear-gradient(135deg, #6d28d9, #4f46e5);}
        .card-header h3{margin:0;font-size:1.5rem;font-weight:600;}.card-header p{margin:5px 0 0;opacity:.8;}
        .card-body{padding:25px;display:flex;align-items:center;justify-content:space-between;}.card-body .student-count{text-align:center;}
        .card-body .count-number{font-size:2.5rem;font-weight:700;color:var(--primary-color);line-height:1;}
        .card-body .count-label{font-size:.9rem;color:var(--text-light);}
        .card-actions{position:absolute;top:15px;right:15px;display:flex;gap:10px;}.card-actions .btn{background:rgba(255,255,255,.2);color:var(--white);padding:8px;border-radius:50%;width:36px;height:36px;line-height:1;}.card-actions .btn:hover{background:rgba(255,255,255,.3);}
        .card-footer-bar{height:6px;background-color:#eee;}
        .card-footer-bar .progress{height:100%;width:0;background:linear-gradient(90deg, #a78bfa, #6d28d9);transition:width .5s ease-out;}
        @keyframes pop-in { from { opacity:0; transform:scale(.9); } to { opacity:1; transform:scale(1); }}
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
            <li><a href="classes.php" class="active"><i class="fas fa-school"></i> Classes</a></li>
            <li><a href="matieres.php"><i class="fas fa-book"></i> Matières</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Classes</h1>
            <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> Ajouter une classe</button>
        </div>

               <?php if ($notification): ?>
    <div class="notification <?= $notification['type'] ?>">
        <?= htmlspecialchars($notification['message']) ?>
    </div>
<?php endif; ?>

<div class="classes-grid">
    <?php if (empty($classes)): ?>
        <p>Aucune classe n'a été créée pour le moment.</p>
    <?php else: ?>
        <?php $index = 0; ?>
        <?php foreach ($classes as $classe): ?>
            <div class="class-card" style="animation-delay: <?= $index * 100 ?>ms">
                <div class="card-header">
                    <h3><?= htmlspecialchars($classe['nom']) ?></h3>
                    <p><?= htmlspecialchars($classe['niveau']) ?></p>
                </div>
                <div class="card-body">
                    <div class="student-count">
                        <div class="count-number" data-target="<?= $classe['student_count'] ?>">0</div>
                        <div class="count-label">Étudiants inscrits</div>
                    </div>
                    <i class="fas fa-school" style="font-size: 40px; color: var(--primary-light)"></i>
                </div>
                <div class="card-actions">
                    <button class="btn btn-sm editBtn"
                            data-id="<?= $classe['id'] ?>"
                            data-nom="<?= htmlspecialchars($classe['nom']) ?>"
                            data-niveau="<?= htmlspecialchars($classe['niveau']) ?>"
                            data-annee="<?= htmlspecialchars($classe['annee_scolaire']) ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="classes.php?action=delete&id=<?= $classe['id'] ?>"
                       class="btn btn-sm"
                       onclick="return confirm('Voulez-vous vraiment supprimer cette classe ?');">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
                <div class="card-footer-bar">
                    <div class="progress" style="width:<?= min(100, ($classe['student_count'] / 30) * 100) ?>%;"></div>
                </div>
            </div>
            <?php $index++; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</main>
</div>


<!-- Modal pour Ajouter/Modifier -->
<div id="formModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2 id="modalTitle">Ajouter une classe</h2>
        <form action="classes.php" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="classe_id" id="classe_id">
            <div class="form-group">
                <label for="nom">Nom de la classe (ex: Terminale A, L1 Info)</label>
                <input type="text" name="nom" id="nom" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="niveau">Niveau (ex: Terminale, Licence 1)</label>
                <input type="text" name="niveau" id="niveau" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="annee_scolaire">Année Scolaire (ex: 2024-2025)</label>
                <input type="text" name="annee_scolaire" id="annee_scolaire" class="form-control" required>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="submit" class="btn btn-primary" id="saveBtn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Logic
    const modal = document.getElementById('formModal');
    const addBtn = document.getElementById('addBtn');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.editBtn');
    
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('form');
    const formAction = document.getElementById('formAction');
    const classeIdInput = document.getElementById('classe_id');
    const nomInput = document.getElementById('nom');
    const niveauInput = document.getElementById('niveau');
    const anneeInput = document.getElementById('annee_scolaire');
    const saveBtn = document.getElementById('saveBtn');

    function openModal() { modal.style.display = 'block'; }
    function closeModal() { modal.style.display = 'none'; }

    addBtn.onclick = function() {
        modalTitle.innerText = 'Ajouter une nouvelle classe';
        form.reset();
        formAction.value = 'add';
        classeIdInput.value = '';
        saveBtn.innerText = 'Ajouter';
        openModal();
    }

    editBtns.forEach(btn => {
        btn.onclick = function() {
            modalTitle.innerText = 'Modifier la classe';
            form.reset();
            formAction.value = 'edit';
            classeIdInput.value = this.dataset.id;
            nomInput.value = this.dataset.nom;
            niveauInput.value = this.dataset.niveau;
            anneeInput.value = this.dataset.annee;
            saveBtn.innerText = 'Enregistrer les modifications';
            openModal();
        }
    });

    closeBtn.onclick = closeModal;
    window.onclick = function(event) { if (event.target == modal) { closeModal(); } }

    // Counter Animation
    const counters = document.querySelectorAll('.count-number');
    const speed = 200;
    counters.forEach(counter => {
        const animate = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const inc = Math.ceil(target / speed);
            if (count < target) {
                counter.innerText = count + inc;
                setTimeout(animate, 10);
            } else {
                counter.innerText = target;
            }
        }
        setTimeout(animate, 500); // Delay start
    });
});
</script>

</body>
</html>