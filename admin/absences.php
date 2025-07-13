<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');

$page_title = "Gestion des Absences";
$notification = null;

// --- LOGIQUE CRUD ---

// MODIFIER une absence (justification/commentaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_absence') {
    $absence_id = $_POST['absence_id'];
    $justifie = isset($_POST['justifie']) ? 1 : 0;
    $commentaire = trim($_POST['commentaire']);

    $stmt = $pdo->prepare("UPDATE absences SET justifie = ?, commentaire = ? WHERE id = ?");
    $stmt->execute([$justifie, $commentaire, $absence_id]);
    $notification = ['type' => 'success', 'message' => 'Absence mise à jour avec succès.'];
}

// SUPPRIMER une absence
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $absence_id_to_delete = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM absences WHERE id = ?");
    $stmt->execute([$absence_id_to_delete]);
    $notification = ['type' => 'success', 'message' => 'Absence supprimée avec succès.'];
}

// --- RÉCUPÉRATION DES DONNÉES ---
$filter_classe_id = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : null;
$filter_date = isset($_GET['date']) ? $_GET['date'] : null;

$sql = "
    SELECT a.id, a.date, a.justifie, a.commentaire,
           u.nom, u.prenom,
           c.nom as classe_nom
    FROM absences a
    JOIN etudiants e ON a.etudiant_id = e.id
    JOIN utilisateurs u ON e.utilisateur_id = u.id
    JOIN classes c ON e.classe_id = c.id
";
$conditions = [];
$params = [];
if ($filter_classe_id) {
    $conditions[] = "c.id = ?";
    $params[] = $filter_classe_id;
}
if ($filter_date) {
    $conditions[] = "a.date = ?";
    $params[] = $filter_date;
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY a.date DESC, c.nom, u.nom, u.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$absences = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <style>
        :root {--sidebar-bg:#fff;--main-bg:#f0f4f8;--text-dark:#1e293b;--text-light:#64748b;--primary-color:#4f46e5;--primary-light:rgba(79,70,229,.1);--success-color:#10b981;--danger-color:#ef4444;--warning-color:#f59e0b;--white:#fff;}
        body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;color:var(--text-dark);}
        .dashboard-container{display:flex;}
        .sidebar{width:250px;background:var(--sidebar-bg);color:#333;min-height:100vh;padding:20px;box-shadow:2px 0 15px rgba(0,0,0,.05);position:fixed;top:0;left:0;}
        .sidebar .admin-header{text-align:center;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid #eee;}
        .sidebar .admin-header i{font-size:30px;color:var(--primary-color);margin-bottom:10px;}
        .sidebar .admin-header h2{margin:0;font-size:22px;color:var(--text-dark);}
        .sidebar ul{list-style:none;padding:0;}.sidebar ul li{margin:10px 0;}.sidebar ul li a{color:var(--text-light);text-decoration:none;font-weight:500;display:flex;align-items:center;padding:12px 15px;border-radius:8px;transition:all .3s ease;}
        .sidebar ul li a i{margin-right:10px;width:20px;text-align:center;}.sidebar ul li a.active,.sidebar ul li a:hover{background:var(--primary-color);color:var(--white);box-shadow:0 4px 10px rgba(79,70,229,.3);}
        .main-content{margin-left:290px;padding:30px;width:calc(100% - 250px);}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}.page-header h1{font-size:2.5rem;font-weight:700;color:var(--text-dark);margin:0;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s ease;border:none;cursor:pointer;}
        .btn-sm{padding:6px 12px;font-size:.85rem;border-radius:6px;}
        .filter-bar{background:var(--white);padding:20px;border-radius:12px;margin-bottom:25px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        .filter-bar .form-group{display:flex;flex-direction:column;flex-grow:1;}
        .filter-bar label{font-weight:600;font-size:.9rem;margin-bottom:5px;}
        .filter-bar .form-control{padding:10px 15px;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
        .table-container{background:var(--white);padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        table{width:100%;border-collapse:collapse;}table th,table td{padding:12px 15px;text-align:left;border-bottom:1px solid #dee2e6;}
        table thead th{background-color:#f8f9fa;font-weight:600;color:var(--text-light);}tbody tr:hover{background-color:#f1f5f9;}
        .status-badge{padding:5px 12px;border-radius:20px;font-size:.8rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;}
        .status-badge.justifie{background:#d1e7dd;color:#0f5132;}.status-badge.non-justifie{background:#fff3cd;color:#664d03;}
        .action-btns{display:flex;gap:10px;}
        .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);backdrop-filter:blur(5px);animation:fadeIn .3s;}
        .modal-content{position:relative;background:var(--white);margin:10% auto;padding:35px;border-radius:15px;width:90%;max-width:500px;box-shadow:0 10px 40px rgba(0,0,0,.2);animation:slideIn .4s ease-out;}
        @keyframes fadeIn{from{opacity:0}}@keyframes slideIn{from{transform:translateY(-50px);opacity:0}}
        .close-btn{color:#aaa;float:right;font-size:28px;font-weight:700;cursor:pointer;line-height:1;}
        .form-group{margin-bottom:20px;}.form-group label{display:block;margin-bottom:8px;font-weight:600;}
        .form-control{width:100%;padding:12px 15px;border:1px solid #ccc;border-radius:8px;font-size:1rem;}
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
            <li><a href="matieres.php"><i class="fas fa-book"></i> Matières</a></li>
            <li><a href="emploi.php"><i class="fas fa-calendar-alt"></i> Emplois du temps</a></li>
            <li><a href="absences.php" class="active"><i class="fas fa-calendar-times"></i> Absences</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Gestion des Absences</h1>
        </div>
        <?php if ($notification): ?><div class="notification <?= $notification['type'] ?>"><?= htmlspecialchars($notification['message']) ?></div><?php endif; ?>

        <div class="filter-bar">
            <form action="absences.php" method="GET" style="display:contents;">
                <div class="form-group"><label for="filter_classe">Filtrer par classe</label><select name="classe_id" id="filter_classe" class="form-control"><option value="">Toutes les classes</option><?php foreach ($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($filter_classe_id == $classe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="filter_date">Filtrer par date</label><input type="date" name="date" id="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date ?? '') ?>"></div>
                <button type="submit" class="btn btn-primary" style="align-self: flex-end;"><i class="fas fa-filter"></i> Filtrer</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Étudiant</th>
                        <th>Classe</th>
                        <th>Statut</th>
                        <th>Commentaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absences)): ?>
                        <tr><td colspan="6" style="text-align:center;">Aucune absence trouvée pour les filtres sélectionnés.</td></tr>
                    <?php else: ?>
                        <?php foreach ($absences as $absence): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($absence['date'])) ?></td>
                                <td><?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']) ?></td>
                                <td><?= htmlspecialchars($absence['classe_nom']) ?></td>
                                <td>
                                    <?php if ($absence['justifie']): ?>
                                        <span class="status-badge justifie"><i class="fas fa-check-circle"></i> Justifiée</span>
                                    <?php else: ?>
                                        <span class="status-badge non-justifie"><i class="fas fa-exclamation-triangle"></i> Non justifiée</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($absence['commentaire'] ?: 'Aucun') ?></td>
                                <td class="action-btns">
                                    <button class="btn btn-sm editBtn" style="background:#f59e0b; color:white;"
                                            data-id="<?= $absence['id'] ?>"
                                            data-justifie="<?= $absence['justifie'] ?>"
                                            data-commentaire="<?= htmlspecialchars($absence['commentaire']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="absences.php?action=delete&id=<?= $absence['id'] ?>" class="btn btn-sm" style="background:var(--danger-color); color:white;" onclick="return confirm('Voulez-vous vraiment supprimer cette absence ?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal pour Modifier -->
<div id="formModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h2>Modifier une absence</h2>
        <form action="absences.php?<?= http_build_query($_GET) ?>" method="POST">
            <input type="hidden" name="action" value="update_absence">
            <input type="hidden" name="absence_id" id="absence_id">
            <div class="form-group">
                <label for="commentaire">Commentaire / Motif</label>
                <textarea name="commentaire" id="commentaire" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="justifie" id="justifie" value="1" style="width:20px; height:20px;">
                    Marquer comme justifiée
                </label>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('formModal');
    const closeBtn = document.querySelector('.close-btn');
    const editBtns = document.querySelectorAll('.editBtn');
    
    const absenceIdInput = document.getElementById('absence_id');
    const commentaireInput = document.getElementById('commentaire');
    const justifieCheckbox = document.getElementById('justifie');

    function openModal() { modal.style.display = 'block'; }
    function closeModal() { modal.style.display = 'none'; }

    editBtns.forEach(btn => {
        btn.onclick = function() {
            absenceIdInput.value = this.dataset.id;
            commentaireInput.value = this.dataset.commentaire;
            justifieCheckbox.checked = this.dataset.justifie == '1';
            openModal();
        }
    });

    closeBtn.onclick = closeModal;
    window.onclick = function(event) { if (event.target == modal) { closeModal(); } }
});
</script>

</body>
</html>