<?php

require_once '../includes/config.php';

// Security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php?error=Please login first');
    exit();
}

if ($_SESSION['user_type'] !== 'user') {
    header('Location: login.php?error=Unauthorized access');
    exit();
}

$userID = $_SESSION['user_id'];
$recipeID = isset($_GET['recipeID']) ? (int)$_GET['recipeID'] : 0;

if ($recipeID === 0) {
    header('Location: userpage.php?error=Invalid recipe');
    exit();
}

// Check if already in favourites
$stmt = $pdo->prepare("SELECT * FROM Favourites WHERE userID = ? AND recipeID = ?");
$stmt->execute([$userID, $recipeID]);

if ($stmt->rowCount() === 0) {
    // Add to favourites
    $stmt = $pdo->prepare("INSERT INTO Favourites (userID, recipeID) VALUES (?, ?)");
    $stmt->execute([$userID, $recipeID]);
}

// Redirect back to view recipe page
header("Location: view-recipe.php?id=$recipeID");
exit();
?>