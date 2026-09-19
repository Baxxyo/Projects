<?php
session_start();

$host = 'localhost';
$dbname = 'healbite_db';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=8889;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/* ===== AUTH FUNCTIONS ===== */

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=Please login first');
        exit();
    }
}

function requireUserType($type) {
    requireLogin();
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $type) {
        header('Location: login.php?error=Unauthorized access');
        exit();
    }
}
?>