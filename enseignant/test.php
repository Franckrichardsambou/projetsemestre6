<?php

// Affiche toutes les erreurs possibles, sans exception.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de débogage</h1>";
echo "<p>Le fichier test.php s'exécute correctement.</p>";
echo "<hr>";
echo "<p>Tentative d'inclusion du fichier de configuration...</p>";

// On utilise le chemin relatif depuis le dossier /enseignant/
$chemin_db = '../config/db.php';

echo "<p>Chemin utilisé : <code>" . $chemin_db . "</code></p>";

if (file_exists($chemin_db)) {
    echo "<p style='color:green;'>SUCCÈS : Le fichier <strong>db.php</strong> a été trouvé !</p>";
    
    // On essaie maintenant de l'inclure pour voir s'il contient une erreur de syntaxe.
    require_once $chemin_db;
    
    echo "<p style='color:green;'>SUCCÈS : Le fichier <strong>db.php</strong> a été inclus sans erreur de syntaxe.</p>";
    
    // On vérifie si la variable $pdo a bien été créée dans db.php
    if (isset($pdo)) {
        echo "<p style='color:green;'>SUCCÈS : La connexion à la base de données (variable \$pdo) est établie !</p>";
    } else {
        echo "<p style='color:red;'>ÉCHEC : Le fichier db.php a été inclus, mais la variable \$pdo n'existe pas. Vérifiez le contenu de db.php.</p>";
    }

} else {
    echo "<p style='color:red;'><strong>ERREUR FATALE : Le fichier db.php est INTROUVABLE au chemin spécifié.</strong></p>";
    echo "<p>Vérifiez que votre structure de dossier est bien :</p>";
    echo "<ul><li>etablissement/</li><ul><li>config/</li><ul><li>db.php</li></ul><li>enseignant/</li><ul><li>test.php</li></ul></ul></ul>";
}

?>