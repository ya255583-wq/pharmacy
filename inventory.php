<?php require_once 'includes/auth.php'; ?>
<link rel="stylesheet" href="includes/style.css">
<?php require_once 'includes/sidebar.php'; ?>
<?php
    require_once 'connect.php';
    session_start();

    if (!isset($_SESSION['userName'])&& !isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
    else{
        $userName = $_SESSION['userName'];
        $role = $_SESSION['role'];
    }


?>