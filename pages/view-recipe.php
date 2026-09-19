<?php

require_once('../includes/config.php');

// Security: must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Please login first');
    exit();
}

$viewerID   = $_SESSION['user_id'];
$viewerType = $_SESSION['user_type'];

// Get recipe ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) {
    header('Location: userpage.php');
    exit();
}

// Fetch recipe + creator
$stmt = $pdo->prepare("
    SELECT r.*, u.firstName, u.lastName, u.photoFileName AS creatorPhoto, rc.categoryName
    FROM Recipe r
    JOIN `User` u ON r.userID = u.id
    JOIN RecipeCategory rc ON r.categoryID = rc.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    die("Recipe not found.");
}

// Fetch ingredients
$stmtIng = $pdo->prepare("SELECT * FROM ingredients WHERE recipeID = ? ORDER BY id ASC");
$stmtIng->execute([$id]);
$ingredients = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

// Fetch instructions
$stmtIns = $pdo->prepare("SELECT * FROM instructions WHERE recipeID = ? ORDER BY stepOrder ASC");
$stmtIns->execute([$id]);
$instructions = $stmtIns->fetchAll(PDO::FETCH_ASSOC);

// Fetch comments
$stmtCom = $pdo->prepare("
    SELECT c.*, u.firstName, u.lastName, u.photoFileName
    FROM Comment c
    JOIN `User` u ON c.userID = u.id
    WHERE c.recipeID = ?
    ORDER BY c.date DESC
");
$stmtCom->execute([$id]);
$comments = $stmtCom->fetchAll(PDO::FETCH_ASSOC);

// Total likes
$stmtLikes = $pdo->prepare("SELECT COUNT(*) AS cnt FROM likes WHERE recipeID = ?");
$stmtLikes->execute([$id]);
$totalLikes = $stmtLikes->fetch()['cnt'];

// Check if viewer is the creator or admin
$isCreator = ($viewerID == $recipe['userID']);
$isAdmin   = ($viewerType === 'admin');

// Check viewer's interaction status (only for regular non-creator users)
$alreadyLiked     = false;
$alreadyFavourite = false;
$alreadyReported  = false;

if (!$isCreator && !$isAdmin) {
    $s = $pdo->prepare("SELECT 1 FROM likes WHERE userID=? AND recipeID=?");
    $s->execute([$viewerID, $id]);
    $alreadyLiked = (bool)$s->fetch();

    $s = $pdo->prepare("SELECT 1 FROM favourites WHERE userID=? AND recipeID=?");
    $s->execute([$viewerID, $id]);
    $alreadyFavourite = (bool)$s->fetch();

    $s = $pdo->prepare("SELECT 1 FROM report WHERE userID=? AND recipeID=?");
    $s->execute([$viewerID, $id]);
    $alreadyReported = (bool)$s->fetch();
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $commentText = trim($_POST['comment']);
    $commentRecipeID = (int)($_POST['recipeID'] ?? 0);

    if (!empty($commentText) && $commentRecipeID === $id) {
        $stmt = $pdo->prepare("INSERT INTO Comment (recipeID, userID, comment) VALUES (?, ?, ?)");
        $stmt->execute([$id, $viewerID, $commentText]);
    }
    header("Location: view-recipe.php?id=$id");
    exit();
}

function recipeImagePath($fileName) {
    if (empty($fileName)) return '../assets/images/default-recipe.jpg';
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($recipe['name']); ?> | HealBite</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .vr-container { max-width: 860px; margin: 40px auto; padding: 0 20px; }
    .vr-header { margin-bottom: 20px; }
    .vr-title { font-size: 2rem; font-weight: 700; color: #1b4332; margin-bottom: 8px; }
    .vr-meta { display: flex; align-items: center; gap: 14px; color: #555; font-size: 0.95rem; margin-bottom: 16px; }
    .vr-meta img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
    .vr-tag { background: #e8f5e9; color: #2d6a4f; padding: 3px 12px; border-radius: 20px; font-size: 0.85rem; }
    .vr-likes { background: #fff3e0; color: #e65100; padding: 3px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
    .vr-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .vr-btn { padding: 9px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600; text-decoration: none; display: inline-block; transition: opacity 0.2s; }
    .vr-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .vr-btn-fav  { background: #fff3e0; color: #e65100; }
    .vr-btn-like { background: #fce4ec; color: #c62828; }
    .vr-btn-rpt  { background: #fafafa; color: #555; border: 1px solid #ddd; }
    .vr-photo { width: 100%; max-height: 420px; object-fit: cover; border-radius: 16px; margin-bottom: 28px; }
    .vr-section { margin-bottom: 28px; }
    .vr-section h3 { font-size: 1.2rem; font-weight: 700; color: #1b4332; margin-bottom: 12px; border-bottom: 2px solid #e8f5e9; padding-bottom: 6px; }
    .vr-section p, .vr-section li { color: #444; line-height: 1.7; }
    .vr-section ul, .vr-section ol { padding-left: 22px; }
    .vr-section li { margin-bottom: 6px; }
    .comment-box { background: #f9f9f9; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
    .comment-box .c-user { font-weight: 600; color: #2d6a4f; margin-bottom: 4px; font-size: 0.95rem; }
    .comment-box .c-date { font-size: 0.8rem; color: #999; margin-bottom: 8px; }
    .comment-box .c-text { color: #444; }
    .comment-form textarea { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 12px; font-size: 0.95rem; resize: vertical; min-height: 90px; box-sizing: border-box; }
    .comment-form button { margin-top: 10px; background: #2d6a4f; color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
  </style>
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
      <?php if ($viewerType === 'admin'): ?>
        <a class="ADDEDIT-link" href="admin.php">Admin Page</a>
      <?php else: ?>
        <a class="ADDEDIT-link" href="userpage.php">My Page</a>
      <?php endif; ?>
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="logout.php">Log out</a>
    </nav>
  </header>

  <main>
    <div class="vr-container">

      <!-- Header -->
      <div class="vr-header">
        <h1 class="vr-title"><?php echo htmlspecialchars($recipe['name']); ?></h1>
        <div class="vr-meta">
          <img src="<?php echo userImagePath($recipe['creatorPhoto']); ?>" alt="Creator">
          <span>By <strong><?php echo htmlspecialchars($recipe['firstName'] . ' ' . $recipe['lastName']); ?></strong></span>
          <span class="vr-tag"><?php echo htmlspecialchars($recipe['categoryName']); ?></span>
          <span class="vr-likes">❤ <?php echo (int)$totalLikes; ?> Likes</span>
        </div>
      </div>

      <!-- Action buttons (only for non-creator, non-admin users) -->
      <?php if (!$isCreator && !$isAdmin): ?>
      <div class="vr-actions">
        <!-- Favourite -->
        <?php if ($alreadyFavourite): ?>
          <button class="vr-btn vr-btn-fav" disabled>⭐ Already in Favourites</button>
        <?php else: ?>
          <a class="vr-btn vr-btn-fav" href="add-favourite.php?recipeID=<?php echo $id; ?>">⭐ Add to Favourites</a>
        <?php endif; ?>

        <!-- Like -->
        <?php if ($alreadyLiked): ?>
          <button class="vr-btn vr-btn-like" disabled>❤ Already Liked</button>
        <?php else: ?>
          <a class="vr-btn vr-btn-like" href="add-like.php?recipeID=<?php echo $id; ?>">❤ Like</a>
        <?php endif; ?>

        <!-- Report -->
        <?php if ($alreadyReported): ?>
          <button class="vr-btn vr-btn-rpt" disabled>🚩 Already Reported</button>
        <?php else: ?>
          <a class="vr-btn vr-btn-rpt" href="add-report.php?recipeID=<?php echo $id; ?>"
             onclick="return confirm('Report this recipe as inappropriate?')">🚩 Report</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Photo -->
      <img src="<?php echo recipeImagePath($recipe['photoFileName']); ?>"
           alt="<?php echo htmlspecialchars($recipe['name']); ?>"
           class="vr-photo">

      <!-- Description -->
      <div class="vr-section">
        <h3>About this Recipe</h3>
        <p><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
      </div>

      <!-- Ingredients -->
      <div class="vr-section">
        <h3>Ingredients</h3>
        <?php if (!empty($ingredients)): ?>
          <ul>
            <?php foreach ($ingredients as $ing): ?>
              <li><?php echo htmlspecialchars($ing['ingredientName']); ?> — <?php echo htmlspecialchars($ing['ingredientQuantity']); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>No ingredients listed.</p>
        <?php endif; ?>
      </div>

      <!-- Instructions -->
      <div class="vr-section">
        <h3>Instructions</h3>
        <?php if (!empty($instructions)): ?>
          <ol>
            <?php foreach ($instructions as $step): ?>
              <li><?php echo htmlspecialchars($step['step']); ?></li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p>No instructions listed.</p>
        <?php endif; ?>
      </div>

      <!-- Video -->
      <?php if (!empty($recipe['videoFileName'])): ?>
      <div class="vr-section">
        <h3>Recipe Video</h3>
        <video controls style="width:100%; border-radius:12px;">
          <source src="../assets/videos/<?php echo htmlspecialchars($recipe['videoFileName']); ?>">
          Your browser does not support the video tag.
        </video>
      </div>
      <?php endif; ?>

      <!-- Comments -->
      <div class="vr-section">
        <h3>Comments (<?php echo count($comments); ?>)</h3>

        <?php if (!empty($comments)): ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment-box">
              <div class="c-user"><?php echo htmlspecialchars($c['firstName'] . ' ' . $c['lastName']); ?></div>
              <div class="c-date"><?php echo htmlspecialchars($c['date']); ?></div>
              <div class="c-text"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No comments yet. Be the first to comment!</p>
        <?php endif; ?>

        <!-- Comment form -->
        <div class="vr-section comment-form">
          <h3>Leave a Comment</h3>
          <form method="POST" action="view-recipe.php?id=<?php echo $id; ?>">
            <input type="hidden" name="recipeID" value="<?php echo $id; ?>">
            <textarea name="comment" placeholder="Write your comment here..." required></textarea>
            <button type="submit">Post Comment</button>
          </form>
        </div>
      </div>

    </div>
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