<?php
// includes/auth-check.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !hasVideotecaAccess()) {
    header("Location: /planos.php");
    exit;
}
?>