<?php
require_once("../includes/config.php");

// Security check - must be logged in as regular user
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php?error=Please login first");
    exit();
}
if ($_SESSION['user_type'] !== 'user') {
    header("Location: login.php?error=Unauthorized access");
    exit();
}

$errorMessage = "";

// Get user ID from session (NOT hardcoded!)
$userID = $_SESSION['user_id'];

// Get recipe ID from URL or POST
$recipeID = isset($_GET['recipeID']) ? (int)$_GET['recipeID'] : 0;

if (isset($_POST['recipeID'])) {
    $recipeID = (int)$_POST['recipeID'];
}

if ($recipeID === 0) {
    header("Location: my_recipes.php?error=Invalid recipe ID");
    exit();
}

// Get categories from database
$stmt = $pdo->prepare("SELECT id, categoryName FROM recipecategory");
$stmt->execute();
$categoriesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create upload directories if they don't exist
$uploadImageDir = "../assets/images/uploads/";
$uploadVideoDir = "../assets/videos/";

if (!is_dir($uploadImageDir)) {
    mkdir($uploadImageDir, 0777, true);
}
if (!is_dir($uploadVideoDir)) {
    mkdir($uploadVideoDir, 0777, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $recipeName  = trim($_POST['recipeName'] ?? '');
    $categoryID  = (int)($_POST['category'] ?? 0);
    $description  = trim($_POST['description'] ?? '');
    $videoFilePath = trim($_POST['videoUrl'] ?? '');

    if ($recipeName === '' || $categoryID === 0 || $description === '') {
        $errorMessage = "Please fill in all required fields.";
    } else {

        // Get old recipe data
        $stmt = $pdo->prepare("SELECT * FROM recipe WHERE id = ? AND userID = ?");
        $stmt->execute([$recipeID, $userID]);
        $oldRecipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRecipe) {
            die("Recipe not found or you don't have permission to edit it.");
        }

        // Start with old values
        $photoFileName = $oldRecipe['photoFileName'];
        $videoFileName = $oldRecipe['videoFileName'] ?? '';
        
        $videoFilePath = trim($_POST['videoUrl'] ?? ($oldRecipe['videoFilePath'] ?? ''));

        // Handle new photo upload 
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0 && !empty($_FILES['photo']['name'])) {
            $photoOriginalName = $_FILES['photo']['name'];
            $photoTmpName      = $_FILES['photo']['tmp_name'];
            $photoExt          = strtolower(pathinfo($photoOriginalName, PATHINFO_EXTENSION));
            
            $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($photoExt, $allowedImageExts)) {
                // Delete old photo if exists
                if (!empty($oldRecipe['photoFileName']) && file_exists($uploadImageDir . $oldRecipe['photoFileName'])) {
                    unlink($uploadImageDir . $oldRecipe['photoFileName']);
                }
                
                $photoFileName = "recipe_" . time() . "_" . rand(1000, 9999) . "." . $photoExt;
                move_uploaded_file($photoTmpName, $uploadImageDir . $photoFileName);
            }
        }

        // Handle new video upload 
        if (isset($_FILES['videoFile']) && $_FILES['videoFile']['error'] === 0 && !empty($_FILES['videoFile']['name'])) {
            $videoOriginalName = $_FILES['videoFile']['name'];
            $videoTmpName      = $_FILES['videoFile']['tmp_name'];
            $videoExt          = strtolower(pathinfo($videoOriginalName, PATHINFO_EXTENSION));
            
            $allowedVideoExts = ['mp4', 'webm', 'ogg', 'mov'];
            
            if (in_array($videoExt, $allowedVideoExts)) {
                // Delete old video if exists
                if (!empty($oldRecipe['videoFileName']) && file_exists($uploadVideoDir . $oldRecipe['videoFileName'])) {
                    unlink($uploadVideoDir . $oldRecipe['videoFileName']);
                }
                
                $videoFileName = "video_" . time() . "_" . rand(1000, 9999) . "." . $videoExt;
                move_uploaded_file($videoTmpName, $uploadVideoDir . $videoFileName);
            }
        }

        // Update recipe
        $stmt = $pdo->prepare("
            UPDATE recipe
            SET categoryID = ?, name = ?, description = ?, photoFileName = ?, videoFileName = ?, videoFilePath = ?
            WHERE id = ? AND userID = ?
        ");
        $stmt->execute([
            $categoryID,
            $recipeName,
            $description,
            $photoFileName,
            $videoFileName,
            $videoFilePath,
            $recipeID,
            $userID
        ]);

        // Delete old ingredients
        $stmt = $pdo->prepare("DELETE FROM ingredients WHERE recipeID = ?");
        $stmt->execute([$recipeID]);

        // Insert new ingredients
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'ingName') === 0) {
                $index = str_replace('ingName', '', $key);
                $ingredientName = trim($_POST["ingName$index"] ?? '');
                $ingredientQty  = trim($_POST["ingQty$index"] ?? '');

                if ($ingredientName !== '' && $ingredientQty !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO ingredients (recipeID, ingredientName, ingredientQuantity)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$recipeID, $ingredientName, $ingredientQty]);
                }
            }
        }

        // Delete old instructions
        $stmt = $pdo->prepare("DELETE FROM instructions WHERE recipeID = ?");
        $stmt->execute([$recipeID]);

        // Insert new instructions
        foreach ($_POST as $key => $value) {
            if (preg_match('/^step(\d+)$/', $key, $matches)) {
                $stepOrder = (int)$matches[1];
                $stepText  = trim($_POST[$key]);

                if ($stepText !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO instructions (recipeID, step, stepOrder)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$recipeID, $stepText, $stepOrder]);
                }
            }
        }

        header("Location: my_recipes.php?success=Recipe updated successfully");
        exit();
    }
}

// Get current recipe data for display
$stmt = $pdo->prepare("SELECT * FROM recipe WHERE id = ? AND userID = ?");
$stmt->execute([$recipeID, $userID]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    die("Recipe not found or you don't have permission to edit it.");
}

// Get ingredients
$stmt = $pdo->prepare("SELECT * FROM ingredients WHERE recipeID = ? ORDER BY id ASC");
$stmt->execute([$recipeID]);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get instructions
$stmt = $pdo->prepare("SELECT * FROM instructions WHERE recipeID = ? ORDER BY stepOrder ASC");
$stmt->execute([$recipeID]);
$steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>HealBite | Edit Recipe</title>
  <link rel="stylesheet" href="../assets/css/style.css">
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
      <a class="ADDEDIT-link" href="home.html">Home</a>
      <a class="ADDEDIT-btn ADDEDIT-btn-outline" href="logout.php">Log out</a>
      <a class="ADDEDIT-avatar" href="Userpage.php" title="User">
        <img src="../assets/images/default-avatar.png" alt="User" />
      </a>
    </nav>
  </header>

  <main class="ADDEDIT-container">
    <section class="ADDEDIT-card">
      <h1 class="ADDEDIT-title">Edit Recipe</h1>
      <p class="ADDEDIT-subtitle">Update recipe details and keep it suitable for special conditions & allergies.</p>

      <?php if ($errorMessage !== "") { ?>
        <p style="color:red; font-weight:bold; margin-bottom:15px;">
          <?= htmlspecialchars($errorMessage) ?>
        </p>
      <?php } ?>

      <form class="ADDEDIT-form" action="edit-recipe.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="recipeID" value="<?= $recipeID ?>">

        <div class="ADDEDIT-row">
          <label class="ADDEDIT-label" for="recipeName">Name:</label>
          <input class="ADDEDIT-input" id="recipeName" name="recipeName" type="text"
                 value="<?= htmlspecialchars($recipe['name']) ?>" required />
        </div>

        <div class="ADDEDIT-row">
          <label class="ADDEDIT-label" for="category">Category:</label>
          <div class="ADDEDIT-select-wrap">
            <select class="ADDEDIT-select" id="category" name="category" required>
              <option value="" disabled>Select</option>
              <?php foreach ($categoriesResult as $row) { ?>
                <option value="<?= $row['id'] ?>" <?= ($row['id'] == $recipe['categoryID']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($row['categoryName']) ?>
                </option>
              <?php } ?>
            </select>
          </div>
        </div>

        <div class="ADDEDIT-row ADDEDIT-row-top">
          <label class="ADDEDIT-label" for="description">Description:</label>
          <textarea class="ADDEDIT-textarea" id="description" name="description" required><?= htmlspecialchars($recipe['description']) ?></textarea>
        </div>

        <div class="ADDEDIT-row ADDEDIT-row-split">
          <div class="ADDEDIT-split-left">
            <label class="ADDEDIT-label" for="photo">Upload Recipe Photo:</label>
            <input class="ADDEDIT-file" id="photo" name="photo" type="file" accept="image/*" />
            <small style="color:#666;">Leave empty to keep current photo</small>
          </div>

          <div class="ADDEDIT-split-right">
            <div class="ADDEDIT-current-box">
              <div class="ADDEDIT-current-title">Current Photo:</div>
              <div class="ADDEDIT-current-media">
                <?php if (!empty($recipe['photoFileName'])) { ?>
                  <img src="../assets/images/uploads/<?= htmlspecialchars($recipe['photoFileName']) ?>" alt="Current Photo" style="max-width:100%; max-height:100%; border-radius:10px;">
                <?php } else { ?>
                  No photo
                <?php } ?>
              </div>
            </div>
          </div>
        </div>

        <div class="ADDEDIT-section">
          <div class="ADDEDIT-section-head">
            <h2 class="ADDEDIT-section-title">Ingredients:</h2>
            <button type="button" class="ADDEDIT-btn ADDEDIT-btn-light" id="addIngredientBtn">
              + Add another ingredient
            </button>
          </div>

          <div id="ingredientsList" class="ADDEDIT-stack">
            <?php
            $ingredientCount = max(count($ingredients), 1);
            for ($i = 0; $i < $ingredientCount; $i++) {
              $num = $i + 1;
              $ingName = $ingredients[$i]['ingredientName'] ?? '';
              $ingQty  = $ingredients[$i]['ingredientQuantity'] ?? '';
            ?>
            <div class="ADDEDIT-ingredient">
              <div class="ADDEDIT-ingredient-label">Ingredient <?= $num ?>:</div>

              <div class="ADDEDIT-inline">
                <label class="ADDEDIT-mini-label" for="ingName<?= $num ?>">Name:</label>
                <input class="ADDEDIT-input" id="ingName<?= $num ?>" name="ingName<?= $num ?>" type="text"
                       value="<?= htmlspecialchars($ingName) ?>" />
              </div>

              <div class="ADDEDIT-inline">
                <label class="ADDEDIT-mini-label" for="ingQty<?= $num ?>">Quantity:</label>
                <input class="ADDEDIT-input" id="ingQty<?= $num ?>" name="ingQty<?= $num ?>" type="text"
                       value="<?= htmlspecialchars($ingQty) ?>" />
              </div>

              <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-ingredient-btn">×</button>
            </div>
            <?php } ?>
          </div>
        </div>

        <div class="ADDEDIT-section">
          <div class="ADDEDIT-section-head">
            <h2 class="ADDEDIT-section-title">Instructions:</h2>
            <button type="button" class="ADDEDIT-btn ADDEDIT-btn-light" id="addStepBtn">
              + Add another step
            </button>
          </div>

          <div id="stepsList" class="ADDEDIT-stack">
            <?php
            $stepCount = max(count($steps), 1);
            for ($i = 0; $i < $stepCount; $i++) {
              $num = $i + 1;
              $stepText = $steps[$i]['step'] ?? '';
            ?>
            <div class="ADDEDIT-step">
              <div class="ADDEDIT-step-label">Step <?= $num ?>:</div>
              <input class="ADDEDIT-input" id="step<?= $num ?>" name="step<?= $num ?>" type="text"
                     value="<?= htmlspecialchars($stepText) ?>" />
              <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-step-btn">×</button>
            </div>
            <?php } ?>
          </div>
        </div>

        <div class="ADDEDIT-section">
          <h2 class="ADDEDIT-section-title">Upload Video or URL (Optional):</h2>

          <div class="ADDEDIT-row ADDEDIT-row-split">
            <div class="ADDEDIT-split-left">
              <label class="ADDEDIT-label" for="videoFile">Upload Video:</label>
              <input class="ADDEDIT-file" id="videoFile" name="videoFile" type="file" accept="video/*" />
              <small style="color:#666;">Leave empty to keep current video</small>

              <div class="ADDEDIT-spacer"></div>

              <label class="ADDEDIT-label" for="videoUrl">Video URL:</label>
              <input class="ADDEDIT-input" id="videoUrl" name="videoUrl" type="url"
                     value="<?= htmlspecialchars($recipe['videoURL'] ?? '') ?>" />
            </div>

            <div class="ADDEDIT-split-right">
              <div class="ADDEDIT-current-box">
                <div class="ADDEDIT-current-title">Current Video:</div>
                <div class="ADDEDIT-current-media">
                  <?php if (!empty($recipe['videoFileName'])) { ?>
                    Video file saved
                  <?php } elseif (!empty($recipe['videoURL'])) { ?>
                    <?= htmlspecialchars($recipe['videoURL']) ?>
                  <?php } else { ?>
                    No video
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="ADDEDIT-actions">
          <button class="ADDEDIT-btn ADDEDIT-btn-primary" type="submit">Update Recipe</button>
        </div>
      </form>
    </section>
  </main>

  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const ingredientsList = document.getElementById("ingredientsList");
    const stepsList = document.getElementById("stepsList");
    const addIngredientBtn = document.getElementById("addIngredientBtn");
    const addStepBtn = document.getElementById("addStepBtn");

    function updateIngredientNumbers() {
      const ingredientBlocks = ingredientsList.querySelectorAll(".ADDEDIT-ingredient");
      ingredientBlocks.forEach((block, index) => {
        const num = index + 1;
        block.querySelector(".ADDEDIT-ingredient-label").textContent = `Ingredient ${num}:`;

        const nameInput = block.querySelector("input[name^='ingName']");
        const qtyInput = block.querySelector("input[name^='ingQty']");
        const nameLabel = block.querySelector("label[for^='ingName']");
        const qtyLabel = block.querySelector("label[for^='ingQty']");

        nameInput.id = `ingName${num}`;
        nameInput.name = `ingName${num}`;
        qtyInput.id = `ingQty${num}`;
        qtyInput.name = `ingQty${num}`;

        nameLabel.setAttribute("for", `ingName${num}`);
        qtyLabel.setAttribute("for", `ingQty${num}`);
      });
    }

    function updateStepNumbers() {
      const stepBlocks = stepsList.querySelectorAll(".ADDEDIT-step");
      stepBlocks.forEach((block, index) => {
        const num = index + 1;
        block.querySelector(".ADDEDIT-step-label").textContent = `Step ${num}:`;

        const stepInput = block.querySelector("input[name^='step']");
        stepInput.id = `step${num}`;
        stepInput.name = `step${num}`;
      });
    }

    addIngredientBtn.addEventListener("click", function () {
      const count = ingredientsList.querySelectorAll(".ADDEDIT-ingredient").length + 1;

      const ingredientDiv = document.createElement("div");
      ingredientDiv.className = "ADDEDIT-ingredient";
      ingredientDiv.innerHTML = `
        <div class="ADDEDIT-ingredient-label">Ingredient ${count}:</div>

        <div class="ADDEDIT-inline">
          <label class="ADDEDIT-mini-label" for="ingName${count}">Name:</label>
          <input class="ADDEDIT-input" id="ingName${count}" name="ingName${count}" type="text" placeholder="e.g., Oats" />
        </div>

        <div class="ADDEDIT-inline">
          <label class="ADDEDIT-mini-label" for="ingQty${count}">Quantity:</label>
          <input class="ADDEDIT-input" id="ingQty${count}" name="ingQty${count}" type="text" placeholder="e.g., 1/2 cup" />
        </div>

        <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-ingredient-btn">×</button>
      `;

      ingredientsList.appendChild(ingredientDiv);
      updateIngredientNumbers();
    });

    addStepBtn.addEventListener("click", function () {
      const count = stepsList.querySelectorAll(".ADDEDIT-step").length + 1;

      const stepDiv = document.createElement("div");
      stepDiv.className = "ADDEDIT-step";
      stepDiv.innerHTML = `
        <div class="ADDEDIT-step-label">Step ${count}:</div>
        <input class="ADDEDIT-input" id="step${count}" name="step${count}" type="text" placeholder="Write step ${count}..." />
        <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-step-btn">×</button>
      `;

      stepsList.appendChild(stepDiv);
      updateStepNumbers();
    });

    document.addEventListener("click", function (e) {
      if (e.target.classList.contains("remove-ingredient-btn")) {
        const ingredientBlocks = ingredientsList.querySelectorAll(".ADDEDIT-ingredient");
        if (ingredientBlocks.length > 1) {
          e.target.closest(".ADDEDIT-ingredient").remove();
          updateIngredientNumbers();
        }
      }

      if (e.target.classList.contains("remove-step-btn")) {
        const stepBlocks = stepsList.querySelectorAll(".ADDEDIT-step");
        if (stepBlocks.length > 1) {
          e.target.closest(".ADDEDIT-step").remove();
          updateStepNumbers();
        }
      }
    });
  });
  </script>
</body>
</html>