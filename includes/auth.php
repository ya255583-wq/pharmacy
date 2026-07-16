<?php
/**
 * PIMS - Auth Guard
 * ---------------------------------------------------------------
 * Include this as the FIRST line of every protected page, before
 * any HTML, any <link> tag, or any other output:
 *
 *      <?php require_once 'includes/auth.php'; ?>
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userName'])) {
    header("Location: login.php");
    exit();
}