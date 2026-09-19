<?php
require_once('../includes/config.php');

//  only admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php?error=Unauthorized access');
    exit();
}

$action   = $_GET['action']   ?? '';
$reportID = (int)($_GET['reportID'] ?? 0);
$userID   = (int)($_GET['userID']   ?? 0);
$recipeID = (int)($_GET['recipeID'] ?? 0);

if ($reportID === 0) {
    header('Location: admin.php');
    exit();
}

//  If action is block
if ($action === 'block' && $userID > 0) {

    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM `User` WHERE id = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Add to BlockedUser if not already there
        $check = $pdo->prepare("SELECT id FROM blockeduser WHERE emailAddress = ?");
        $check->execute([$user['emailAddress']]);
        if ($check->rowCount() === 0) {
            $pdo->prepare("INSERT INTO blockeduser (firstName, lastName, emailAddress) VALUES (?, ?, ?)")
                ->execute([$user['firstName'], $user['lastName'], $user['emailAddress']]);
        }

        // Delete all user's recipes + associated data + files
        $stmt = $pdo->prepare("SELECT id, photoFileName, videoFileName FROM recipe WHERE userID = ?");
        $stmt->execute([$userID]);
        $userRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($userRecipes as $recipe) {
            $rid = $recipe['id'];

            // Delete files from server
            if (!empty($recipe['photoFileName'])) {
                $p = '../assets/images/uploads/' . $recipe['photoFileName'];
                if (file_exists($p)) unlink($p);
            }
            if (!empty($recipe['videoFileName'])) {
                $v = '../assets/videos/' . $recipe['videoFileName'];
                if (file_exists($v)) unlink($v);
            }

            // Delete all associated DB rows
            $pdo->prepare("DELETE FROM ingredients  WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM instructions WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM comment      WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM likes        WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM favourites   WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM report       WHERE recipeID = ?")->execute([$rid]);
            $pdo->prepare("DELETE FROM recipe       WHERE id = ?")->execute([$rid]);
        }

        // Delete user's other activity
        $pdo->prepare("DELETE FROM comment    WHERE userID = ?")->execute([$userID]);
        $pdo->prepare("DELETE FROM likes      WHERE userID = ?")->execute([$userID]);
        $pdo->prepare("DELETE FROM favourites WHERE userID = ?")->execute([$userID]);
        $pdo->prepare("DELETE FROM report     WHERE userID = ?")->execute([$userID]);

        // Delete the user
        $pdo->prepare("DELETE FROM `User` WHERE id = ?")->execute([$userID]);
    }
}

// Delete the report (both block and dismiss)
if ($reportID > 0) {
    $pdo->prepare("DELETE FROM report WHERE id = ?")->execute([$reportID]);
}

// Redirect to admin page
header('Location: admin.php');
exit();
?>