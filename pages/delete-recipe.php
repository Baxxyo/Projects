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
$recipeID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recipeID === 0) {
    header('Location: my_recipes.php?error=Invalid recipe');
    exit();
}

// Verify recipe belongs to this user
$stmt = $pdo->prepare("SELECT * FROM Recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    header('Location: my_recipes.php?error=Recipe not found or access denied');
    exit();
}

// Delete photo file if exists
if (!empty($recipe['photoFileName'])) {
    $photoPath = '../assets/images/uploads/' . $recipe['photoFileName'];
    if (file_exists($photoPath)) {
        unlink($photoPath);
    }
}

// Delete video file if exists
if (!empty($recipe['videoFileName'])) {
    $videoPath = '../assets/videos/' . $recipe['videoFileName'];
    if (file_exists($videoPath)) {
        unlink($videoPath);
    }
}

// Delete all related data (order matters due to foreign keys)

// 1. Delete from Favourites
$stmt = $pdo->prepare("DELETE FROM Favourites WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 2. Delete from Likes
$stmt = $pdo->prepare("DELETE FROM Likes WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 3. Delete from Report
$stmt = $pdo->prepare("DELETE FROM Report WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 4. Delete from Comment
$stmt = $pdo->prepare("DELETE FROM Comment WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 5. Delete from Ingredients
$stmt = $pdo->prepare("DELETE FROM Ingredients WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 6. Delete from Instructions
$stmt = $pdo->prepare("DELETE FROM Instructions WHERE recipeID = ?");
$stmt->execute([$recipeID]);

// 7. Delete the recipe itself
$stmt = $pdo->prepare("DELETE FROM Recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);

header("Location: my_recipes.php?success=Recipe deleted successfully");
exit();
?>