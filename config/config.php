<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constantes de configuration
define('APP_NAME', 'Gestion Scolaire Sénégal');
define('APP_VERSION', '1.0.0');
define('APP_YEAR', date('Y'));

// Définir BASE_URL une seule fois
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/etablissement');
}

// Chemins supplémentaires
define('CSS_PATH', BASE_URL . '/assets/css/');
define('JS_PATH', BASE_URL . '/assets/js/');
define('IMG_PATH', BASE_URL . '/assets/images/');

// Définir le logo (ajouté)
define('APP_LOGO', BASE_URL . '/image/image.png');

?>