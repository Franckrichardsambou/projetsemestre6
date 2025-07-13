<?php
// DÉBOGAGE ET INITIALISATION
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!isset($_SESSION)) { session_start(); }
if (!isset($_SESSION['user'])) { $_SESSION['user'] = ['id' => 1, 'role' => 'etudiant']; } // Simulation

require_once '../config/db.php';

// AUTHENTIFICATION ET IDENTIFICATION
if ($_SESSION['user']['role'] !== 'etudiant') { die("Accès non autorisé."); }
$user_id = $_SESSION['user']['id'];

// Récupérer les informations de l'étudiant
$stmt_etudiant = $pdo->prepare("SELECT e.*, u.nom, u.prenom, c.nom AS classe_nom, c.niveau as classe_niveau, c.annee_scolaire FROM etudiants e JOIN utilisateurs u ON e.utilisateur_id = u.id JOIN classes c ON e.classe_id = c.id WHERE e.utilisateur_id = ?");
$stmt_etudiant->execute([$user_id]);
$etudiant = $stmt_etudiant->fetch();
if (!$etudiant) { die("Profil étudiant non trouvé."); }
$etudiant_id = $etudiant['id'];
$page_title = "Mon Bulletin de Notes";

// --- RÉCUPÉRATION DES DONNÉES ---

// Gérer le filtre de trimestre
$selected_trimestre = isset($_GET['trimestre']) ? $_GET['trimestre'] : 'T1';

// Requête pour récupérer les notes, les matières et les coefficients pour le trimestre sélectionné
$sql = "
    SELECT 
        m.nom AS matiere_nom,
        m.coefficient,
        n.note
    FROM matieres m
    LEFT JOIN notes n ON m.id = n.matiere_id 
        AND n.etudiant_id = ? 
        AND n.trimestre = ?
    -- On affiche toutes les matières même si l'étudiant n'a pas de note
    -- Pour cela, on doit s'assurer que la matière est enseignée dans la classe de l'étudiant.
    WHERE m.id IN (
        SELECT DISTINCT matiere_id FROM emplois_du_temps WHERE classe_id = ?
    )
    ORDER BY m.nom
";
$params = [$etudiant_id, $selected_trimestre, $etudiant['classe_id']];

$stmt_notes = $pdo->prepare($sql);
$stmt_notes->execute($params);
$bulletin_data = $stmt_notes->fetchAll();

// Calcul de la moyenne générale du trimestre
$total_points = 0;
$total_coefficients = 0;

foreach ($bulletin_data as $data) {
    if (!is_null($data['note'])) {
        $total_points += $data['note'] * $data['coefficient'];
        $total_coefficients += $data['coefficient'];
    }
}
$moyenne_generale = ($total_coefficients > 0) ? $total_points / $total_coefficients : 0;



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
        --border-color: #e2e8f0;
    }
    body{background-color:var(--main-bg);margin:0;font-family:'Poppins',sans-serif;}
    .page-wrapper{display:flex;}
    .main-content{margin-left:var(--sidebar-width);flex-grow:1;padding:30px;}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;border-bottom:1px solid #eef2f7;padding-bottom:20px;}
    .page-header h1{font-size:2.2rem;color:var(--text-dark);margin:0;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 25px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;border:none;cursor:pointer;}
    .btn-primary{background:var(--primary-color);color:white;box-shadow:0 4px 15px var(--primary-glow);}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px var(--primary-glow);}
    
    /* Onglets de navigation pour les trimestres */
    .trimester-tabs { display: flex; gap: 10px; margin-bottom: 25px; }
    .trimester-tabs a { padding: 10px 20px; text-decoration: none; font-weight: 600; border-radius: 8px; color: var(--text-light); transition: all 0.3s; }
    .trimester-tabs a.active, .trimester-tabs a:hover { color: #fff; background-color: var(--primary-color); box-shadow: 0 4px 15px var(--primary-glow); }
    
    /* Conteneur du bulletin */
    .bulletin-container {
        background: var(--card-bg);
        border-radius: 15px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.08);
        padding: 40px;
        animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

    .bulletin-header { text-align: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 30px; }
    .bulletin-header h2 { font-size: 1.8rem; color: var(--text-dark); margin: 0 0 5px 0; }
    .bulletin-header h3 { font-size: 1.5rem; color: var(--primary-color); margin: 0; }
    .student-info { display: flex; justify-content: space-between; margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 10px; }
    .student-info div { font-size: 1rem; }
    .student-info strong { color: var(--text-dark); }
    
    /* Tableau des notes */
    .notes-table { width: 100%; border-collapse: collapse; }
    .notes-table th, .notes-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
    .notes-table thead th { background-color: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
    .notes-table tbody tr:hover { background-color: #f1f5f9; }
    .notes-table .text-center { text-align: center; }
    .note-value { font-weight: 600; font-size: 1.1rem; }
    .note-good { color: #27ae60; }
    .note-medium { color: #f39c12; }
    .note-bad { color: #e74c3c; }
    .note-na { color: var(--text-light); font-style: italic; }

    /* Total et moyenne */
    .bulletin-footer { margin-top: 30px; border-top: 2px solid var(--border-color); padding-top: 20px; text-align: right; }
    .moyenne-generale { font-size: 1.5rem; font-weight: 700; }
    .moyenne-generale span { color: var(--primary-color); }
    
    /* Styles pour l'impression */
    @media print {
        body { background: #fff; }
        .page-wrapper, .sidebar, .page-header, .trimester-tabs, .btn { display: none; }
        .main-content { margin-left: 0; padding: 0; }
        .bulletin-container { box-shadow: none; border: 1px solid #ccc; border-radius: 0; }
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
                <li><a href="bulletin.php" class="nav-link active"><i class="fas fa-award fa-fw"></i> <span>Mon Bulletin</span></a></li>
                <li><a href="cours.php" class="nav-link"><i class="fas fa-book fa-fw"></i> <span>Mes Cours</span></a></li>
                <li><a href="emploi.php" class="nav-link"><i class="fas fa-calendar-week fa-fw"></i> <span>Emploi du temps</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile"><img src="/etablissement/assets/images/student-avatar.png" alt="Avatar"><div class="user-info"><span><?= htmlspecialchars($etudiant['prenom']) ?></span><small><?= htmlspecialchars($etudiant['classe_nom']) ?></small></div></div><a href="/etablissement/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt fa-fw"></i></a>
        </div>
    </aside>

    <!-- Contenu Principal -->
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-award"></i> Mon Bulletin de Notes</h1>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        </div>
        
        <!-- Onglets de sélection du trimestre -->
        <div class="trimester-tabs">
            <a href="bulletin.php?trimestre=T1" class="<?= $selected_trimestre == 'T1' ? 'active' : '' ?>">Trimestre 1</a>
            <a href="bulletin.php?trimestre=T2" class="<?= $selected_trimestre == 'T2' ? 'active' : '' ?>">Trimestre 2</a>
            <a href="bulletin.php?trimestre=T3" class="<?= $selected_trimestre == 'T3' ? 'active' : '' ?>">Trimestre 3</a>
        </div>

        <div class="bulletin-container">
            <div class="bulletin-header">
                <h2>Relevé de Notes - Année Scolaire <?= htmlspecialchars($etudiant['annee_scolaire']) ?></h2>
                <h3>Trimestre <?= substr($selected_trimestre, 1) ?></h3>
            </div>
            
            <div class="student-info">
                <div><strong>Étudiant(e) :</strong> <?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></div>
                <div><strong>Classe :</strong> <?= htmlspecialchars($etudiant['classe_niveau'] . ' - ' . $etudiant['classe_nom']) ?></div>
                <div><strong>Matricule :</strong> <?= htmlspecialchars($etudiant['matricule']) ?></div>
            </div>

            <table class="notes-table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th class="text-center">Coefficient</th>
                        <th class="text-center">Note / 20</th>
                        <th class="text-center">Note Pondérée</th>
                        <th>Appréciation de l'enseignant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bulletin_data)): ?>
                        <tr><td colspan="5" class="text-center">Aucune matière ou note disponible pour cette classe.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bulletin_data as $data): ?>
                            <tr>
                                <td><?= htmlspecialchars($data['matiere_nom']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($data['coefficient']) ?></td>
                                <td class="text-center">
                                    <?php
                                    if (!is_null($data['note'])) {
                                        $note = floatval($data['note']);
                                        $class = 'note-medium';
                                        if ($note >= 12) $class = 'note-good';
                                        if ($note < 8) $class = 'note-bad';
                                        echo '<span class="note-value ' . $class . '">' . number_format($note, 2) . '</span>';
                                    } else {
                                        echo '<span class="note-na">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?= !is_null($data['note']) ? number_format($data['note'] * $data['coefficient'], 2) : '<span class="note-na">--</span>' ?>
                                </td>
                                <td>
                                    <!-- Espace pour les appréciations futures -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="bulletin-footer">
                <div class="moyenne-generale">
                    Moyenne Générale du Trimestre : <span><?= number_format($moyenne_generale, 2) ?> / 20</span>
                </div>
            </div>
        </div>
    </main>
</div>

