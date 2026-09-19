<?php
require_once("../includes/config.php");

//  Check that the user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php?error=Please login first");
    exit();
}
if ($_SESSION['user_type'] !== 'admin') {
    header("Location: login.php?error=Unauthorized access");
    exit();
}

$adminID = $_SESSION['user_id'];

//  Retrieve admin's information
$stmt = $pdo->prepare("SELECT * FROM `User` WHERE id = ?");
$stmt->execute([$adminID]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all users
$users = $pdo->query("SELECT * FROM `User` ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get all recipes
$recipes = $pdo->query("
    SELECT r.*, rc.categoryName, u.firstName
    FROM recipe r
    JOIN recipecategory rc ON r.categoryID = rc.id
    JOIN user u ON r.userID = u.id
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// REQ 11c: Get all reports with recipe and user info
$reports = $pdo->query("
    SELECT rep.id AS reportID,
           rep.recipeID,
           rep.userID AS reporterUserID,
           r.userID AS reportedUserID,
           r.name AS recipe_name,
           owner.firstName AS reported_user_name,
           owner.lastName  AS reported_user_last,
           reporter.firstName AS reporter_first_name
    FROM report rep
    JOIN recipe r ON rep.recipeID = r.id
    JOIN user owner ON r.userID = owner.id
    JOIN user reporter ON rep.userID = reporter.id
    ORDER BY rep.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

//Get all blocked users
$blocked = $pdo->query("SELECT * FROM blockeduser ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Page | HealBite</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .admin-wrap { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
    .admin-welcome { background: linear-gradient(135deg, #1b4332, #2d6a4f); color: white; padding: 24px 28px; border-radius: 16px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
    .admin-welcome h2 { margin: 0; font-size: 1.6rem; }
    .admin-section { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); padding: 24px; margin-bottom: 28px; }
    .admin-section h3 { color: #1b4332; font-size: 1.2rem; border-bottom: 2px solid #e8f5e9; padding-bottom: 8px; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f0f4f0; color: #1b4332; padding: 10px 12px; text-align: left; font-weight: 600; }
    td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; color: #444; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .badge { padding: 3px 10px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; }
    .badge-admin { background: #e3f2fd; color: #1565c0; }
    .badge-user  { background: #e8f5e9; color: #2d6a4f; }
    .recipe-link { color: #2d6a4f; font-weight: 600; text-decoration: none; }
    .recipe-link:hover { text-decoration: underline; }
    .no-data { color: #888; font-style: italic; padding: 10px 0; }
    .action-form select { padding: 6px 10px; border-radius: 6px; border: 1px solid #ddd; margin-right: 8px; }
    .action-form button { background: #2d6a4f; color: white; padding: 6px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.88rem; }
    .action-form button:hover { opacity: 0.85; }
  </style>
</head>
<body>

  <header class="ADDEDIT-navbar">
    <div class="ADDEDIT-nav-left">
      <a class="ADDEDIT-brand" href="home.html" aria-label="HealBite Home">
        <img src="../assets/images/logo.PNG" alt="HealBite logo" class="ADDEDIT-logo" />
        <span class="ADDEDIT-brand-text">HEAL<span class="ADDEDIT-brand-accent">BITE</span></span>
      </a>
    </div>
    <nav class="ADDEDIT-nav-right">
      <!-- REQ 12: Sign-out link -->
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="logout.php">Log out</a>
    </nav>
  </header>

  <main>
    <div class="admin-wrap">

      <!--  Welcome note admin info -->
      <div class="admin-welcome">
        <div>
          <h2>Welcome, <?php echo htmlspecialchars($admin['firstName']); ?> 👋</h2>
          <p style="margin:6px 0 0; opacity:0.85;"><?php echo htmlspecialchars($admin['emailAddress']); ?></p>
        </div>
        <a href="logout.php" style="color:#b7e4c7; text-decoration:none; font-weight:600;">Sign out</a>
      </div>

      <!-- All Users -->
      <div class="admin-section">
        <h3>All Users (<?php echo count($users); ?>)</h3>
        <?php if (empty($users)): ?>
          <p class="no-data">No users found.</p>
        <?php else: ?>
        <table>
          <tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th></tr>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?php echo $u['id']; ?></td>
            <td><?php echo htmlspecialchars($u['firstName'] . ' ' . $u['lastName']); ?></td>
            <td><?php echo htmlspecialchars($u['emailAddress']); ?></td>
            <td><span class="badge badge-<?php echo $u['userType']; ?>"><?php echo $u['userType']; ?></span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>

      <!-- All Recipes -->
      <div class="admin-section">
        <h3>All Recipes (<?php echo count($recipes); ?>)</h3>
        <?php if (empty($recipes)): ?>
          <p class="no-data">No recipes found.</p>
        <?php else: ?>
        <table>
          <tr><th>ID</th><th>Name</th><th>Category</th><th>Created By</th></tr>
          <?php foreach ($recipes as $r): ?>
          <tr>
            <td><?php echo $r['id']; ?></td>
            <td>
              <a class="recipe-link" href="view-recipe.php?id=<?php echo $r['id']; ?>">
                <?php echo htmlspecialchars($r['name']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars($r['categoryName']); ?></td>
            <td><?php echo htmlspecialchars($r['firstName']); ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>

      <!-- Reports Table with action form -->
      <div class="admin-section">
        <h3>Recipe Reports (<?php echo count($reports); ?>)</h3>
        <?php if (empty($reports)): ?>
          <p class="no-data">No reports at this time.</p>
        <?php else: ?>
        <table>
          <tr><th>Recipe</th><th>Reported By</th><th>Action</th></tr>
          <?php foreach ($reports as $rep): ?>
          <tr>
            <td>
             
              <a class="recipe-link" href="view-recipe.php?id=<?php echo $rep['recipeID']; ?>">
                <?php echo htmlspecialchars($rep['recipe_name']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars($rep['reporter_first_name']); ?></td>
            <td>
              <!-- REQ 11c: Action form with hidden recipeID -->
              <form class="action-form" action="handle-report.php" method="GET">
                <input type="hidden" name="recipeID"  value="<?php echo $rep['recipeID']; ?>">
                <input type="hidden" name="reportID"  value="<?php echo $rep['reportID']; ?>">
                <input type="hidden" name="userID"    value="<?php echo $rep['reportedUserID']; ?>">
                <select name="action">
                  <option value="block">🚫 Block User</option>
                  <option value="dismiss">✓ Dismiss Report</option>
                </select>
                <button type="submit">Submit</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>

      <!--  Blocked Users Table -->
      <div class="admin-section">
        <h3>Blocked Users (<?php echo count($blocked); ?>)</h3>
        <?php if (empty($blocked)): ?>
          <p class="no-data">No blocked users.</p>
        <?php else: ?>
        <table>
          <tr><th>Name</th><th>Email</th></tr>
          <?php foreach ($blocked as $b): ?>
          <tr>
            <td><?php echo htmlspecialchars($b['firstName'] . ' ' . $b['lastName']); ?></td>
            <td><?php echo htmlspecialchars($b['emailAddress']); ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
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