<?php

require_once '../includes/config.php';

// Security check - only admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php?error=Please login first');
    exit();
}

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: login.php?error=Unauthorized access');
    exit();
}

$userID = isset($_GET['userID']) ? (int)$_GET['userID'] : 0;
$reportID = isset($_GET['reportID']) ? (int)$_GET['reportID'] : 0;

if ($userID === 0) {
    header('Location: admin.php?error=Invalid user');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM `User` WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: admin.php?error=User not found');
    exit();
}

// Check if already blocked
$check = $pdo->prepare("SELECT * FROM BlockedUser WHERE emailAddress = ?");
$check->execute([$user['emailAddress']]);

if ($check->rowCount() == 0) {
    // Add to blocked users
    $stmt = $pdo->prepare("
        INSERT INTO BlockedUser (firstName, lastName, emailAddress)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $user['firstName'],
        $user['lastName'],
        $user['emailAddress']
    ]);

    // Delete all recipes of this user (and related data)
    $stmt = $pdo->prepare("SELECT id FROM Recipe WHERE userID = ?");
    $stmt->execute([$userID]);
    $userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($userRecipes as $recipe) {
        $recipeID = $recipe['id'];
        
        // Get recipe files to delete
        $stmt = $pdo->prepare("SELECT photoFileName, videoFileName FROM Recipe WHERE id = ?");
        $stmt->execute([$recipeID]);
        $recipeData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recipeData) {
            if (!empty($recipeData['photoFileName'])) {
                $photoPath = '../assets/images/uploads/' . $recipeData['photoFileName'];
                if (file_exists($photoPath)) unlink($photoPath);
            }
            if (!empty($recipeData['videoFileName'])) {
                $videoPath = '../assets/videos/' . $recipeData['videoFileName'];
                if (file_exists($videoPath)) unlink($videoPath);
            }
        }
        
        // Delete related data
        $pdo->prepare("DELETE FROM Favourites WHERE recipeID = ?")->execute([$recipeID]);
        $pdo->prepare("DELETE FROM Likes WHERE recipeID = ?")->execute([$recipeID]);
        $pdo->prepare("DELETE FROM Comment WHERE recipeID = ?")->execute([$recipeID]);
        $pdo->prepare("DELETE FROM Ingredients WHERE recipeID = ?")->execute([$recipeID]);
        $pdo->prepare("DELETE FROM Instructions WHERE recipeID = ?")->execute([$recipeID]);
        $pdo->prepare("DELETE FROM Recipe WHERE id = ?")->execute([$recipeID]);
    }
    
    // Delete user's comments on other recipes
    $pdo->prepare("DELETE FROM Comment WHERE userID = ?")->execute([$userID]);
    
    // Delete user's likes
    $pdo->prepare("DELETE FROM Likes WHERE userID = ?")->execute([$userID]);
    
    // Delete user's favourites
    $pdo->prepare("DELETE FROM Favourites WHERE userID = ?")->execute([$userID]);
    
    // Delete user's reports
    $pdo->prepare("DELETE FROM Report WHERE userID = ?")->execute([$userID]);
    
    // Delete the user
    $pdo->prepare("DELETE FROM `User` WHERE id = ?")->execute([$userID]);
}

// Delete the report
if ($reportID > 0) {
    $pdo->prepare("DELETE FROM Report WHERE id = ?")->execute([$reportID]);
}

header("Location: admin.php");
exit();
?>