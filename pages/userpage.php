<?php
require_once '../includes/config.php';

// Security check - must be logged in as regular user
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

// Get total recipes count for this user
$stmt = $pdo->prepare("SELECT COUNT(*) AS totalRecipes FROM Recipe WHERE userID = ?");
$stmt->execute([$userID]);
$totalRecipes = $stmt->fetch(PDO::FETCH_ASSOC)['totalRecipes'];

// Get total likes for all user's recipes
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS totalLikes FROM Likes
    INNER JOIN Recipe ON Likes.recipeID = Recipe.id
    WHERE Recipe.userID = ?
");
$stmt->execute([$userID]);
$totalLikes = $stmt->fetch(PDO::FETCH_ASSOC)['totalLikes'];

// Get all categories for filter dropdown
$stmt = $pdo->query("SELECT * FROM RecipeCategory ORDER BY categoryName ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle category filtering
$selectedCategoryID = isset($_POST['categoryID']) ? (int)$_POST['categoryID'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedCategoryID > 0) {
    // Filter by selected category
    $stmt = $pdo->prepare("
        SELECT Recipe.id, Recipe.name, Recipe.photoFileName, RecipeCategory.categoryName,
               `User`.firstName, `User`.photoFileName AS creatorPhoto,
               (SELECT COUNT(*) FROM Likes WHERE Likes.recipeID = Recipe.id) AS likesCount
        FROM Recipe
        INNER JOIN RecipeCategory ON Recipe.categoryID = RecipeCategory.id
        INNER JOIN `User` ON Recipe.userID = `User`.id
        WHERE Recipe.categoryID = ? 
        ORDER BY Recipe.id DESC
    ");
    $stmt->execute([$selectedCategoryID]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get all recipes
    $stmt = $pdo->query("
        SELECT Recipe.id, Recipe.name, Recipe.photoFileName, RecipeCategory.categoryName,
               `User`.firstName, `User`.photoFileName AS creatorPhoto,
               (SELECT COUNT(*) FROM Likes WHERE Likes.recipeID = Recipe.id) AS likesCount
        FROM Recipe
        INNER JOIN RecipeCategory ON Recipe.categoryID = RecipeCategory.id
        INNER JOIN `User` ON Recipe.userID = `User`.id
        ORDER BY Recipe.id DESC
    ");
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's favourite recipes
$stmt = $pdo->prepare("
    SELECT Recipe.id, Recipe.name, Recipe.photoFileName, RecipeCategory.categoryName
    FROM Favourites
    INNER JOIN Recipe ON Favourites.recipeID = Recipe.id
    INNER JOIN RecipeCategory ON Recipe.categoryID = RecipeCategory.id
    WHERE Favourites.userID = ? 
    ORDER BY Recipe.id DESC
");
$stmt->execute([$userID]);
$favourites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions for image paths
function userImagePath($fileName) {
    if (empty($fileName)) {
        return '../assets/images/default-avatar.jpg';
    }
    // Check in uploads folder first (user uploaded)
    if (file_exists('../assets/images/uploads/' . $fileName)) {
        return '../assets/images/uploads/' . htmlspecialchars($fileName);
    }
    // Check in main images folder (default/seed)
    if (file_exists('../assets/images/' . $fileName)) {
        return '../assets/images/' . htmlspecialchars($fileName);
    }
    return '../assets/images/default-avatar.png';
}

function recipeImagePath($fileName) {
    if (empty($fileName)) {
        return '../assets/images/default-recipe.jpg';
    }
    // Check in uploads folder first
    if (file_exists('../assets/images/uploads/' . $fileName)) {
        return '../assets/images/uploads/' . htmlspecialchars($fileName);
    }
    // Check in main images folder
    if (file_exists('../assets/images/' . $fileName)) {
        return '../assets/images/' . htmlspecialchars($fileName);
    }
    return '../assets/images/default-recipe.jpg';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealBite - User Page</title>
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
      <a class="ADDEDIT-link" href="../pages/home.html">Home</a>
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="logout.php">Log out</a>
      <a class="ADDEDIT-avatar" href="#" title="User">
        <img src="<?php echo userImagePath($user['photoFileName']); ?>" alt="User" />
      </a>
    </nav>
  </header>

  <main class="page">
    <!-- Welcome Section -->
    <section class="welcome">
      <div class="welcome__left">
        <div class="avatarWrap">
          <img class="avatarImg" src="<?php echo userImagePath($user['photoFileName']); ?>" alt="User photo">
        </div>
        <div class="welcomeCard">
          <div class="welcomeTitle">Welcome, <?php echo htmlspecialchars($user['firstName']); ?>!</div>
          <div class="userBlock">
            <div class="userName"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></div>
            <div class="userEmail"><?php echo htmlspecialchars($user['emailAddress']); ?></div>
          </div>
        </div>
      </div>
      <div class="welcome__right">
        <div class="stats">
          <div class="stat">
            <div class="stat__label">Total Likes</div>
            <div class="stat__value"><span><?php echo (int)$totalLikes; ?></span></div>
          </div>
          <div class="stat">
            <div class="stat__label">Total Recipes</div>
            <div class="stat__value"><span><?php echo (int)$totalRecipes; ?></span></div>
          </div>
        </div>
        <a class="pillBtn" href="my_recipes.php">My Recipes</a>
      </div>
    </section>

    <!-- All Recipes Section with Filter -->
    <section class="section">
      <div class="section__top">
        <h2 class="section__title">All Available Recipes</h2>
        <form method="POST" class="filter">
          <select class="filterSelect" name="categoryID">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>" <?php echo ($selectedCategoryID == $cat['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['categoryName']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="filterBtn" type="submit">Filter</button>
        </form>
      </div>
      
      <div class="cardsWrap">
        <?php if (!empty($recipes)): ?>
          <?php foreach ($recipes as $recipe): ?>
            <article class="recipeCard">
              <a class="recipeCard__name" href="view-recipe.php?id=<?php echo $recipe['id']; ?>">
                <?php echo htmlspecialchars($recipe['name']); ?>
              </a>
              <img class="thumb" src="<?php echo recipeImagePath($recipe['photoFileName']); ?>" alt="Recipe photo">
              <div class="recipeCard__row">
                <div class="creator">
                  <img class="creator__img" src="<?php echo userImagePath($recipe['creatorPhoto']); ?>" alt="">
                  <span class="creator__name"><?php echo htmlspecialchars($recipe['firstName']); ?></span>
                </div>
                <div class="pill">
                  <span class="pill__label">Likes</span>
                  <span class="pill__value"><?php echo (int)$recipe['likesCount']; ?></span>
                </div>
              </div>
              <div class="tag"><?php echo htmlspecialchars($recipe['categoryName']); ?></div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="text-align:center; color:#888; padding:40px;">No recipes found in this category.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Favourites Section -->
    <section class="section">
      <h2 class="section__title">My Favourite Recipes</h2>
      <div class="cardsWrap cardsWrap--small">
        <?php if (!empty($favourites)): ?>
          <?php foreach ($favourites as $fav): ?>
            <article class="recipeCard">
              <a class="recipeCard__name" href="view-recipe.php?id=<?php echo $fav['id']; ?>">
                <?php echo htmlspecialchars($fav['name']); ?>
              </a>
              <img class="thumb" src="<?php echo recipeImagePath($fav['photoFileName']); ?>" alt="Recipe photo">
              <div class="recipeCard__row">
                <div class="tag"><?php echo htmlspecialchars($fav['categoryName']); ?></div>
                <a class="removeLink" href="remove-favourite.php?recipeID=<?php echo $fav['id']; ?>" 
                   onclick="return confirm('Remove this recipe from your favourites?')">Remove</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="text-align:center; color:#888; padding:20px;">You don't have any favourite recipes yet.</p>
        <?php endif; ?>
      </div>
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