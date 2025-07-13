<?php
// --- CONFIGURATION ET SÉCURITÉ ---
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/db.php';
check_role('admin');

$page_title = "Consultation des Notes";

// --- RÉCUPÉRATION DES DONNÉES POUR LES LISTES DÉROULANTES ---
$classes = $pdo->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
$matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);


// ==========================================================
// --- GESTION DES FILTRES (LOGIQUE CORRIGÉE) ---
// ==========================================================

// On récupère les valeurs brutes de l'URL
$filter_classe_id = $_GET['classe_id'] ?? null;
$filter_matiere_id = $_GET['matiere_id'] ?? null;
$filter_trimestre = $_GET['trimestre'] ?? null;

$sql = "
    SELECT n.id, n.note, n.trimestre,
           u.prenom, u.nom,
           c.nom as classe_nom,
           m.nom as matiere_nom
    FROM notes n
    JOIN etudiants e ON n.etudiant_id = e.id
    JOIN utilisateurs u ON e.utilisateur_id = u.id
    JOIN classes c ON e.classe_id = c.id
    JOIN matieres m ON n.matiere_id = m.id
";
$conditions = [];
$params = [];

// On ajoute la condition SEULEMENT si la valeur n'est pas vide
if (!empty($filter_classe_id)) {
    $conditions[] = "c.id = ?";
    $params[] = $filter_classe_id;
}
if (!empty($filter_matiere_id)) {
    $conditions[] = "m.id = ?";
    $params[] = $filter_matiere_id;
}
if (!empty($filter_trimestre)) {
    $conditions[] = "n.trimestre = ?";
    $params[] = $filter_trimestre;
}

// On construit la clause WHERE
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY c.nom, m.nom, u.nom, u.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
        .page-header h1{font-size:2.5rem;font-weight:700;color:var(--text-dark);margin:0;}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s ease;border:none;cursor:pointer;}
        .btn-primary{background:var(--primary-color);color:var(--white);}.btn-primary:hover{background:#4338ca;}
        .filter-bar{background:var(--white);padding:20px;border-radius:12px;margin-bottom:25px;display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;align-items:flex-end;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        .filter-bar .form-group{display:flex;flex-direction:column;}
        .filter-bar label{font-weight:600;font-size:.9rem;margin-bottom:5px;}
        .filter-bar .form-control{padding:10px 15px;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
        .table-container{background:var(--white);padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);}
        table{width:100%;border-collapse:collapse;}table th,table td{padding:12px 15px;text-align:left;border-bottom:1px solid #dee2e6;}
        table thead th{background-color:#f8f9fa;font-weight:600;color:var(--text-light);}tbody tr:hover{background-color:#f1f5f9;}
        .note-badge{font-size:1.1rem;font-weight:700;padding:5px 10px;border-radius:6px;color:#fff;}
        .note-good{background-color:var(--success-color);}.note-medium{background-color:var(--warning-color);}.note-bad{background-color:var(--danger-color);}
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
            <li><a href="absences.php"><i class="fas fa-calendar-times"></i> Absences</a></li>
            <li><a href="notes.php" class="active"><i class="fas fa-clipboard-list"></i> Notes</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>Consultation des Notes</h1>
        </div>
        
        <div class="filter-bar">
            <form action="notes.php" method="GET" style="display:contents;">
                <div class="form-group"><label for="filter_classe">Classe</label><select name="classe_id" id="filter_classe" class="form-control"><option value="">Toutes</option><?php foreach ($classes as $classe): ?><option value="<?= $classe['id'] ?>" <?= ($filter_classe_id == $classe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($classe['niveau'].' - '.$classe['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="filter_matiere">Matière</label><select name="matiere_id" id="filter_matiere" class="form-control"><option value="">Toutes</option><?php foreach ($matieres as $matiere): ?><option value="<?= $matiere['id'] ?>" <?= ($filter_matiere_id == $matiere['id']) ? 'selected' : '' ?>><?= htmlspecialchars($matiere['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="filter_trimestre">Trimestre</label><select name="trimestre" id="filter_trimestre" class="form-control"><option value="">Tous</option><option value="T1" <?= ($filter_trimestre == 'T1') ? 'selected' : '' ?>>T1</option><option value="T2" <?= ($filter_trimestre == 'T2') ? 'selected' : '' ?>>T2</option><option value="T3" <?= ($filter_trimestre == 'T3') ? 'selected' : '' ?>>T3</option></select></div>
                <button type="submit" class="btn btn-primary" style="align-self: flex-end;"><i class="fas fa-filter"></i> Filtrer</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Classe</th>
                        <th>Matière</th>
                        <th>Trimestre</th>
                        <th style="text-align:center;">Note / 20</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notes)): ?>
                        <tr><td colspan="5" style="text-align:center;">Aucune note trouvée pour les filtres sélectionnés.</td></tr>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td><?= htmlspecialchars($note['prenom'] . ' ' . $note['nom']) ?></td>
                                <td><?= htmlspecialchars($note['classe_nom']) ?></td>
                                <td><?= htmlspecialchars($note['matiere_nom']) ?></td>
                                <td><?= htmlspecialchars($note['trimestre']) ?></td>
                                <td style="text-align:center;">
                                    <?php
                                        $val = floatval($note['note']);
                                        $class = 'note-medium';
                                        if ($val >= 12) $class = 'note-good';
                                        if ($val < 8) $class = 'note-bad';
                                    ?>
                                    <span class="note-badge <?= $class ?>"><?= number_format($val, 2) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>