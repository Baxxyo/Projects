<?php
require_once '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists in User table
        $stmt = $pdo->prepare("SELECT * FROM `User` WHERE emailAddress = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Check if email is blocked
            $stmt = $pdo->prepare("SELECT * FROM BlockedUser WHERE emailAddress = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email has been blocked';
            } else {
                // Handle profile photo upload
                $photoFileName = 'default-avatar.jpg';
                
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../assets/images/uploads/';
                    
                    // Create directory if not exists
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($fileExt, $allowedExts)) {
                        $photoFileName = uniqid() . '.' . $fileExt;
                        $uploadPath = $uploadDir . $photoFileName;
                        
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                            $photoFileName = 'default-avatar.png';
                        }
                    } else {
                        $photoFileName = 'default-avatar.png';
                    }
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO `User` (userType, firstName, lastName, emailAddress, password, photoFileName) 
                    VALUES ('user', ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$firstName, $lastName, $email, $hashedPassword, $photoFileName])) {
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['user_type'] = 'user';
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    
                    header('Location: userpage.php');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | HealBite</title>
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
            <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="../pages/login.php">Log in</a>
        </nav>
    </header>

    <main>
        <section class="signup-section">
            <h2>Create an Account</h2>
            
            <?php if ($error): ?>
                <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="signup-form" enctype="multipart/form-data">
                <div class="form-avatar">
                    <img src="../assets/images/default-avatar.jpg" alt="User Icon" id="avatarPreview">
                </div>
                
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required placeholder="Enter your first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">

                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required placeholder="Enter your last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label for="photo">Profile Photo (Optional)</label>
                <input type="file" id="photo" name="photo" accept="image/*">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password (min. 6 characters)">

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">

                <button type="submit" class="btn-signup">Sign Up</button>
                
                <p class="login-prompt">Already have an account? <a href="login.php">Log in</a></p>
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

    <script>
        // Preview image before upload
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>