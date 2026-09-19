<?php
require_once '../includes/config.php';

// Security: must be logged in as a regular user
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php?error=Please login first');
    exit();
}
if ($_SESSION['user_type'] !== 'user') {
    header('Location: login.php?error=Unauthorized access');
    exit();
}

$userID = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM `User` WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: login.php');
    exit();
}

// Get all recipes for this user
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.name,
        r.photoFileName,
        rc.categoryName,
        r.description,
        (SELECT COUNT(*) FROM Likes WHERE recipeID = r.id) AS likesCount
    FROM Recipe r
    INNER JOIN RecipeCategory rc ON r.categoryID = rc.id
    WHERE r.userID = ?
    ORDER BY r.id DESC
");
$stmt->execute([$userID]);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function recipeImagePath($fileName) {
    if (empty($fileName)) return '../assets/images/default-recipe.jpg';
    // Check if it's an uploaded file or a static asset
    if (file_exists('../assets/images/uploads/' . $fileName)) {
        return '../assets/images/uploads/' . htmlspecialchars($fileName);
    }
    return '../assets/images/' . htmlspecialchars($fileName);
}

function userImagePath($fileName) {
    return '../assets/images/' . htmlspecialchars($fileName ?: 'default-avatar.png');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealBite - My Recipes</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

  <header class="ADDEDIT-navbar">
    <div class="ADDEDIT-nav-left">
      <a class="ADDEDIT-brand" href="../pages/home.html" aria-label="HealBite Home">
        <img src="../assets/images/logo.PNG" alt="HealBite logo" class="ADDEDIT-logo" />
        <span class="ADDEDIT-brand-text">HEAL<span class="ADDEDIT-brand-accent">BITE</span></span>
      </a>
    </div>
    <nav class="ADDEDIT-nav-right">
      <a class="ADDEDIT-link" href="userpage.php">User Page</a>
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="add-recipe.php">+ Add Recipe</a>
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="logout.php">Log out</a>
      <a class="ADDEDIT-avatar" href="userpage.php" title="User">
        <img src="<?php echo userImagePath($user['photoFileName']); ?>" alt="User" />
      </a>
    </nav>
  </header>

  <main class="page">
    <section class="section">
      <div class="section__top">
        <h2 class="section__title">My Recipes</h2>
        <a class="ADDEDIT-btn ADDEDIT-btn-primary" href="add-recipe.php">+ Add New Recipe</a>
      </div>

      <?php if (empty($recipes)): ?>
        <p style="text-align:center; color:#888; margin-top:40px;">
          You haven't added any recipes yet. <a href="add-recipe.php">Add your first recipe!</a>
        </p>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; margin-top:20px;">
            <thead>
              <tr style="background:#f0f4f0;">
                <th style="padding:12px 10px; text-align:left; border-bottom:2px solid #ddd;">Photo</th>
                <th style="padding:12px 10px; text-align:left; border-bottom:2px solid #ddd;">Name</th>
                <th style="padding:12px 10px; text-align:left; border-bottom:2px solid #ddd;">Category</th>
                <th style="padding:12px 10px; text-align:left; border-bottom:2px solid #ddd;">Description</th>
                <th style="padding:12px 10px; text-align:center; border-bottom:2px solid #ddd;">Likes</th>
                <th style="padding:12px 10px; text-align:center; border-bottom:2px solid #ddd;">Edit</th>
                <th style="padding:12px 10px; text-align:center; border-bottom:2px solid #ddd;">Delete</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recipes as $recipe): ?>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px;">
                  <a href="view-recipe.php?id=<?php echo $recipe['id']; ?>">
                    <img src="<?php echo recipeImagePath($recipe['photoFileName']); ?>"
                         alt="<?php echo htmlspecialchars($recipe['name']); ?>"
                         style="width:70px; height:55px; object-fit:cover; border-radius:8px;">
                  </a>
                </td>
                <td style="padding:10px;">
                  <a href="view-recipe.php?id=<?php echo $recipe['id']; ?>"
                     style="font-weight:600; color:#2d6a4f; text-decoration:none;">
                    <?php echo htmlspecialchars($recipe['name']); ?>
                  </a>
                </td>
                <td style="padding:10px;">
                  <span style="background:#e8f5e9; color:#2d6a4f; padding:3px 10px; border-radius:20px; font-size:0.85rem;">
                    <?php echo htmlspecialchars($recipe['categoryName']); ?>
                  </span>
                </td>
                <td style="padding:10px; max-width:200px; color:#555; font-size:0.9rem;">
                  <?php echo htmlspecialchars(substr($recipe['description'], 0, 80)) . (strlen($recipe['description']) > 80 ? '...' : ''); ?>
                </td>
                <td style="padding:10px; text-align:center;">
                  <span style="background:#fff3e0; color:#e65100; padding:4px 12px; border-radius:20px; font-weight:600;">
                    ❤ <?php echo (int)$recipe['likesCount']; ?>
                  </span>
                </td>
                <td style="padding:10px; text-align:center;">
                  <a href="edit-recipe.php?recipeID=<?php echo $recipe['id']; ?>"
                     style="background:#2d6a4f; color:white; padding:6px 14px; border-radius:6px; text-decoration:none; font-size:0.9rem;">
                    Edit
                  </a>
                </td>
                <td style="padding:10px; text-align:center;">
                  <a href="delete-recipe.php?id=<?php echo $recipe['id']; ?>"
                     onclick="return confirm('Are you sure you want to delete this recipe? This cannot be undone.')"
                     style="background:#d32f2f; color:white; padding:6px 14px; border-radius:6px; text-decoration:none; font-size:0.9rem;">
                    Delete
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
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
