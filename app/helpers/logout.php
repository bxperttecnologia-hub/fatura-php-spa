<?php
ob_start(); // Inicia o buffer de saída

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

ob_end_flush(); // Libera o buffer antes de redirecionar
header('Location: ../../public/login.php');
exit;
