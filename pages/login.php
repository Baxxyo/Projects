<?php
session_start();
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM `User` WHERE emailAddress = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if email is blocked FIRST 
            $blockCheck = $pdo->prepare("SELECT * FROM BlockedUser WHERE emailAddress = ?");
            $blockCheck->execute([$email]);
            if ($blockCheck->fetch()) {
                $error = 'This account has been blocked. Please contact support.';
            } elseif ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['userType'];
                $_SESSION['user_name'] = $user['firstName'] . ' ' . $user['lastName'];

                // Redirect based on user type
                if ($user['userType'] == 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: userpage.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            // Log error for debugging (optional)
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | HealBite</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="ADDEDIT-navbar">
        <div class="ADDEDIT-nav-left">
            <a class="ADDEDIT-brand" href="../pages/home.html">
                <img src="../assets/images/logo.PNG" alt="HealBite logo" class="ADDEDIT-logo">
                <span class="ADDEDIT-brand-text">HEAL<span class="ADDEDIT-brand-accent">BITE</span></span>
            </a>
        </div>
        <nav class="ADDEDIT-nav-right">
            <a class="ADDEDIT-link" href="../pages/home.html">Home</a>
        </nav>
    </header>

    <main>
        <section class="login-section">
            <h2>Log In to HealBite</h2>
            
            <?php if ($error): ?>
                <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">

                <button type="submit" class="btn-login">Log In</button>
                
                <p class="sign-up-prompt">New User? <a href="signup.php">Sign-up</a></p>
            </form>
        </section>
    </main>

    <footer>
        <div class="footer-logo">
            <img src="../assets/images/logo.png" alt="Healbite Logo" class="footer-logo-img">
        </div>
        <div class="footer-info">
            <span>Twitter | Instagram</span>
            <span>+966 50#######</span>
            <span><a href="mailto:info@healbite.com">info@healbite.com</a></span>
            <span>&copy; 2026 Healbite - All rights reserved.</span>
        </div>
    </footer>
</body>
</html>