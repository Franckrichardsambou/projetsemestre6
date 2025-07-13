<?php
// Fonctions utilitaires

/**
 * Redirige vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user']);
}

/**
 * Vérifie le rôle de l'utilisateur
 */
function check_role($role) {
    if (!is_logged_in() || $_SESSION['user']['role'] !== $role) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

/**
 * Affiche un message flash
 */
function flash($name = '', $message = '', $class = 'alert alert-success') {
    if (!empty($name)) {
        if (!empty($message) && empty($_SESSION[$name])) {
            $_SESSION[$name] = $message;
            $_SESSION[$name . '_class'] = $class;
        } elseif (empty($message) && !empty($_SESSION[$name])) {
            $class = !empty($_SESSION[$name . '_class']) ? $_SESSION[$name . '_class'] : '';
            echo '<div class="' . $class . '" id="msg-flash">' . $_SESSION[$name] . '</div>';
            unset($_SESSION[$name]);
            unset($_SESSION[$name . '_class']);
        }
    }
}

/**
 * Formate une date
 */
function format_date($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Protège contre les injections XSS
 */
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>