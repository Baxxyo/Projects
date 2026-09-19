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

    // Validation
    if ($recipeName === '' || $categoryID === 0 || $description === '') {
        $errorMessage = "Please fill in all required fields.";
    } else {
        // Handle photo upload
        $photoFileName = "";

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0 && !empty($_FILES['photo']['name'])) {
            $photoOriginalName = $_FILES['photo']['name'];
            $photoTmpName      = $_FILES['photo']['tmp_name'];
            $photoExt          = strtolower(pathinfo($photoOriginalName, PATHINFO_EXTENSION));
            
            // Allowed image extensions
            $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($photoExt, $allowedImageExts)) {
                $photoFileName = "recipe_" . time() . "_" . rand(1000, 9999) . "." . $photoExt;
                $uploadPath = $uploadImageDir . $photoFileName;
                move_uploaded_file($photoTmpName, $uploadPath);
            } else {
                $errorMessage = "Invalid image format. Allowed: jpg, jpeg, png, gif, webp";
            }
        } else {
            $errorMessage = "Recipe photo is required.";
        }

        // Handle video file upload 
        $videoFileName = "";
        if (empty($errorMessage) && isset($_FILES['videoFile']) && $_FILES['videoFile']['error'] === 0 && !empty($_FILES['videoFile']['name'])) {
            $videoOriginalName = $_FILES['videoFile']['name'];
            $videoTmpName      = $_FILES['videoFile']['tmp_name'];
            $videoExt          = strtolower(pathinfo($videoOriginalName, PATHINFO_EXTENSION));
            
            // Allowed video extensions
            $allowedVideoExts = ['mp4', 'webm', 'ogg', 'mov'];
            
            if (in_array($videoExt, $allowedVideoExts)) {
                $videoFileName = "video_" . time() . "_" . rand(1000, 9999) . "." . $videoExt;
                $uploadPath = $uploadVideoDir . $videoFileName;
                move_uploaded_file($videoTmpName, $uploadPath);
            }
        }

        // If no error, insert recipe
        if (empty($errorMessage)) {
            $stmt = $pdo->prepare("
                INSERT INTO recipe (userID, categoryID, name, description, photoFileName, videoFileName, videoFilePath)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userID,
                $categoryID,
                $recipeName,
                $description,
                $photoFileName,
                $videoFileName,
                $videoFilePath
            ]);

            $recipeID = $pdo->lastInsertId();

            // Insert ingredients
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

            // Insert instructions
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

            header("Location: my_recipes.php");
            exit();
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>HealBite | Add Recipe</title>
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
      <h1 class="ADDEDIT-title">Add New Recipe</h1>
      <p class="ADDEDIT-subtitle">Share a healthy recipe for special conditions & allergies.</p>

      <?php if ($errorMessage !== "") { ?>
        <p style="color:red; font-weight:bold; margin-bottom:15px;">
          <?= htmlspecialchars($errorMessage) ?>
        </p>
      <?php } ?>

      <form class="ADDEDIT-form" action="add-recipe.php" method="POST" enctype="multipart/form-data">

        <div class="ADDEDIT-row">
          <label class="ADDEDIT-label" for="recipeName">Name:</label>
          <input class="ADDEDIT-input" id="recipeName" name="recipeName" type="text"
                 placeholder="e.g., Diabetic Oatmeal Bowl" required />
        </div>

        <div class="ADDEDIT-row">
          <label class="ADDEDIT-label" for="category">Category:</label>
          <div class="ADDEDIT-select-wrap">
            <select class="ADDEDIT-select" id="category" name="category" required>
              <option value="" selected disabled>Select</option>
              <?php
              if (!empty($categoriesResult)) {
                  foreach ($categoriesResult as $row) {
                      echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['categoryName']) . '</option>';
                  }
              }
              ?>
            </select>
          </div>
        </div>

        <div class="ADDEDIT-row ADDEDIT-row-top">
          <label class="ADDEDIT-label" for="description">Description:</label>
          <textarea class="ADDEDIT-textarea" id="description" name="description"
                    placeholder="Briefly describe why this recipe is suitable." required></textarea>
        </div>

        <div class="ADDEDIT-row">
          <label class="ADDEDIT-label" for="photo">Upload Recipe Photo:</label>
          <input class="ADDEDIT-file" id="photo" name="photo" type="file" accept="image/*" required />
        </div>

        <div class="ADDEDIT-section">
          <div class="ADDEDIT-section-head">
            <h2 class="ADDEDIT-section-title">Ingredients:</h2>
            <button type="button" class="ADDEDIT-btn ADDEDIT-btn-light" id="addIngredientBtn">
              + Add another ingredient
            </button>
          </div>

          <div id="ingredientsList" class="ADDEDIT-stack">
            <div class="ADDEDIT-ingredient">
              <div class="ADDEDIT-ingredient-label">Ingredient 1:</div>

              <div class="ADDEDIT-inline">
                <label class="ADDEDIT-mini-label" for="ingName1">Name:</label>
                <input class="ADDEDIT-input" id="ingName1" name="ingName1" type="text"
                       placeholder="e.g., Oats" required />
              </div>

              <div class="ADDEDIT-inline">
                <label class="ADDEDIT-mini-label" for="ingQty1">Quantity:</label>
                <input class="ADDEDIT-input" id="ingQty1" name="ingQty1" type="text"
                       placeholder="e.g., 1/2 cup" required />
              </div>

              <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-ingredient-btn">×</button>
            </div>
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
            <div class="ADDEDIT-step">
              <div class="ADDEDIT-step-label">Step 1:</div>
              <input class="ADDEDIT-input" id="step1" name="step1" type="text"
                     placeholder="Write step 1..." required />
              <button type="button" class="ADDEDIT-icon-btn ADDEDIT-danger remove-step-btn">×</button>
            </div>
          </div>
        </div>

        <div class="ADDEDIT-section">
          <h2 class="ADDEDIT-section-title">Upload Video or URL (Optional):</h2>

          <div class="ADDEDIT-row">
            <label class="ADDEDIT-label" for="videoFile">Upload Video:</label>
            <input class="ADDEDIT-file" id="videoFile" name="videoFile" type="file" accept="video/*" />
          </div>

          <div class="ADDEDIT-row">
            <label class="ADDEDIT-label" for="videoUrl">Video URL:</label>
            <input class="ADDEDIT-input" id="videoUrl" name="videoUrl" type="url"
                   placeholder="https://youtube.com/..." />
          </div>
        </div>

        <div class="ADDEDIT-actions">
          <button class="ADDEDIT-btn ADDEDIT-btn-primary" type="submit">Add Recipe</button>
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