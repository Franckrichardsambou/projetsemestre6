<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$page_title = "Inscription";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize($_POST['nom']);
    $prenom = sanitize($_POST['prenom']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = isset($_POST['role']) ? sanitize($_POST['role']) : 'etudiant';

    // Champs spécifiques
    $matricule = isset($_POST['matricule']) ? sanitize($_POST['matricule']) : null;
    $classe_id = isset($_POST['classe_id']) ? intval($_POST['classe_id']) : null;
    $specialite = isset($_POST['specialite']) ? sanitize($_POST['specialite']) : null;

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Les mots de passe ne correspondent pas";
    } else {
        try {
            require_once '../config/db.php';

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Cet email est déjà utilisé";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insérer dans utilisateurs
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);

                $utilisateur_id = $pdo->lastInsertId();

                // Selon le rôle, insertion dans les tables associées
                if ($role === 'etudiant') {
                    if (empty($matricule) || empty($classe_id)) {
                        $_SESSION['error'] = "Le matricule et la classe sont requis pour les étudiants.";
                        redirect(BASE_URL . '/auth/register.php');
                        exit();
                    }
                    $stmt = $pdo->prepare("INSERT INTO etudiants (utilisateur_id, classe_id, matricule) VALUES (?, ?, ?)");
                    $stmt->execute([$utilisateur_id, $classe_id, $matricule]);
                } elseif ($role === 'enseignant') {
                    if (empty($specialite)) {
                        $_SESSION['error'] = "La spécialité est requise pour les enseignants.";
                        redirect(BASE_URL . '/auth/register.php');
                        exit();
                    }
                    $stmt = $pdo->prepare("INSERT INTO enseignants (utilisateur_id, specialite) VALUES (?, ?)");
                    $stmt->execute([$utilisateur_id, $specialite]);
                }

                $_SESSION['success'] = "Inscription réussie! Vous pouvez maintenant vous connecter.";
                redirect(BASE_URL . '/auth/login.php');
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur d'inscription: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/style.css">

<div class="register-container animated-form">
    <div class="register-box">
        <div class="register-header">
            <h2>Inscription</h2>
            <p>Créez votre compte</p>
        </div>

        <?php flash(); ?>

        <form action="<?= BASE_URL ?>/auth/register.php" method="POST" class="register-form">
            <div class="form-group">
                <label for="nom"><i class="fas fa-user"></i> Nom</label>
                <input type="text" id="nom" name="nom" required>
            </div>

            <div class="form-group">
                <label for="prenom"><i class="fas fa-user"></i> Prénom</label>
                <input type="text" id="prenom" name="prenom" required>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Rôle</label>
                <select id="role" name="role" required>
                    <option value="">-- Sélectionnez un rôle --</option>
                    <option value="etudiant">Étudiant</option>
                    <option value="enseignant">Enseignant</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>

            <!-- Champs supplémentaires selon rôle -->
            <div id="etudiant-fields" style="display:none;">
                <div class="form-group">
                    <label for="matricule"><i class="fas fa-id-badge"></i> Matricule</label>
                    <input type="text" id="matricule" name="matricule">
                </div>

                <div class="form-group">
                    <label for="classe_id"><i class="fas fa-school"></i> Classe</label>
                    <select id="classe_id" name="classe_id">
                        <option value="">-- Sélectionnez une classe --</option>
                        <?php
                        require_once '../config/db.php';
                        $classes = $pdo->query("SELECT id, nom FROM classes")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($classes as $classe) {
                            echo "<option value=\"{$classe['id']}\">{$classe['nom']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div id="enseignant-fields" style="display:none;">
                <div class="form-group">
                    <label for="specialite"><i class="fas fa-briefcase"></i> Spécialité</label>
                    <input type="text" id="specialite" name="specialite">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-user-plus"></i> S'inscrire
            </button>
        </form>

        <div class="register-footer">
            <p>Déjà inscrit ? <a href="<?= BASE_URL ?>/auth/login.php">Se connecter</a></p>
        </div>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function () {
    const etudiantFields = document.getElementById('etudiant-fields');
    const enseignantFields = document.getElementById('enseignant-fields');
    if (this.value === 'etudiant') {
        etudiantFields.style.display = 'block';
        enseignantFields.style.display = 'none';
    } else if (this.value === 'enseignant') {
        etudiantFields.style.display = 'none';
        enseignantFields.style.display = 'block';
    } else {
        etudiantFields.style.display = 'none';
        enseignantFields.style.display = 'none';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
