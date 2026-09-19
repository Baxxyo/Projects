


<?php
require '../includes/config.php';

$userID = $_GET['id'];

// نجيب بيانات المستخدم
$stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

// 🔍 تحقق هل موجود مسبقًا
$check = $pdo->prepare("SELECT * FROM blockeduser WHERE emailAddress = ?");
$check->execute([$user['emailAddress']]);

if ($check->rowCount() == 0) {

    // ➕ أضف إذا مو موجود
    $stmt = $pdo->prepare("
    INSERT INTO blockeduser (firstName, lastName, emailAddress)
    VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $user['firstName'],
        $user['lastName'],
        $user['emailAddress']
    ]);
}

// رجوع
header("Location: admin.php");
exit;
?>