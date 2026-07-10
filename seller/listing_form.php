<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_role($conn, "seller");

$categories = get_categories($conn);
$errors = [];

$title = "";
$description = "";
$price = "";
$stock = "0";
$condition = "used";
$cat_ids = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  check_csrf();
  $title = trim($_POST["title"] ?? "");
  $description = trim($_POST["description"] ?? "");
  $price = (float)($_POST["price"] ?? 0);
  $stock = (int)($_POST["stock"] ?? 0);
  $condition = $_POST["product_condition"] ?? "used";
  $cat_ids = $_POST["categories"] ?? [];

  if ($title === "") $errors[] = "Title is required.";
  if ($price <= 0) $errors[] = "Price must be greater than 0.";
  if ($stock < 0) $errors[] = "Stock cannot be negative.";
  if (!in_array($condition, ["new", "used"], true)) $errors[] = "Invalid condition.";
  if (!is_array($cat_ids) || count($cat_ids) === 0) $errors[] = "Select at least one category.";

  if (!$errors) {
    $seller_id = (int)$_SESSION["user_id"];

    // We'll upload AFTER inserting product.
    $uploadedAbsPath = null;
    $imagePath = null;
    $imageAlt  = null;

    mysqli_begin_transaction($conn);

    try {
      /* 1) Insert product WITHOUT image first */
      $sql = "INSERT INTO products
                (seller_id, title, description, price, stock, product_condition, status)
              VALUES
                (?, ?, ?, ?, ?, ?, 'active')";
      $stmt = mysqli_prepare($conn, $sql);
      mysqli_stmt_bind_param(
        $stmt,
        "issdis",
        $seller_id,
        $title,
        $description,
        $price,
        $stock,
        $condition
      );
      mysqli_stmt_execute($stmt);
      $product_id = mysqli_insert_id($conn);
      mysqli_stmt_close($stmt);

      /* 2) Insert categories */
      $sql2 = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)";
      $stmt2 = mysqli_prepare($conn, $sql2);
      foreach ($cat_ids as $cid) {
        $cid = (int)$cid;
        mysqli_stmt_bind_param($stmt2, "ii", $product_id, $cid);
        mysqli_stmt_execute($stmt2);
      }
      mysqli_stmt_close($stmt2);

      /* 3) Upload image (optional) AFTER product exists */
      if (!empty($_FILES['image']['name'])) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
          throw new Exception("Image upload failed.");
        }

        $tmp  = $_FILES['image']['tmp_name'];
        $size = (int)$_FILES['image']['size'];

        if ($size > 2 * 1024 * 1024) {
          throw new Exception("Image too large (max 2MB).");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $allowed = [
          'image/jpeg' => 'jpg',
          'image/png'  => 'png',
          'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
          throw new Exception("Invalid image type. Use JPG, PNG, or WebP.");
        }

        $ext = $allowed[$mime];

        $destDir = __DIR__ . '/../uploads/products';
        if (!is_dir($destDir)) {
          @mkdir($destDir, 0755, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
          throw new Exception("Upload folder not writable: " . $destDir);
        }

        // Deterministic-ish name (traceable), avoids random spam duplicates
        $name = $product_id . "_" . time() . "." . $ext;
        $dest = $destDir . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
          throw new Exception("Could not save uploaded image.");
        }

        $uploadedAbsPath = $dest;
        $imagePath = '/uploads/products/' . $name;
        $imageAlt  = $title ?: null;

        /* 4) Update product with image fields */
        $stmt3 = mysqli_prepare($conn,
          "UPDATE products SET image_path=?, image_alt=? WHERE product_id=?"
        );
        mysqli_stmt_bind_param($stmt3, "ssi", $imagePath, $imageAlt, $product_id);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
      }

      mysqli_commit($conn);

      redirect('/seller/listings.php');
      exit;

    } catch (Throwable $e) {
      mysqli_rollback($conn);

      // If we uploaded a file but later failed, delete it
      if ($uploadedAbsPath && is_file($uploadedAbsPath)) {
        @unlink($uploadedAbsPath);
      }

      $errors[] = "Failed to create listing.";
      // (Optional for debugging during dev)
      // $errors[] = $e->getMessage();
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">Create Listing</h1>
          <div class="small">Post a new product to the marketplace.</div>
        </div>
        <div style="text-align:right;">
          <a class="btn small" href="<?= BASE_URL ?>/seller/seller_dashboard.php">← Dashboard</a>
          <a class="btn small" href="<?= BASE_URL ?>/seller/listings.php">My Listings</a>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="hr"></div>
        <div class="flash danger">
          <strong>Please fix the following:</strong>
          <ul style="margin:8px 0 0 18px;">
            <?php foreach ($errors as $e): ?>
              <li><?= e($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="hr"></div>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <div class="grid">
          <div class="col-8">
            <label><strong>Title</strong></label>
            <input class="input" name="title" required value="<?= e($title) ?>">

            <div style="height:10px;"></div>

            <label><strong>Description</strong></label>
            <textarea name="description" class="input"><?= e($description) ?></textarea>

            <div style="height:10px;"></div>

            <div class="form-row">
              <div>
                <label><strong>Price</strong></label>
                <input class="input" name="price" type="number" step="0.01" min="0.01" required value="<?= e((string)$price) ?>">
              </div>
              <div>
                <label><strong>Stock</strong></label>
                <input class="input" name="stock" type="number" min="0" required value="<?= e((string)$stock) ?>">
              </div>
            </div>

            <div style="height:10px;"></div>

            <label><strong>Condition</strong></label>
            <select class="input" name="product_condition">
              <option value="used" <?= $condition === 'used' ? 'selected' : '' ?>>Used</option>
              <option value="new"  <?= $condition === 'new'  ? 'selected' : '' ?>>New</option>
            </select>

            <div style="height:10px;"></div>

            <label><strong>Product Image (optional)</strong></label>
            <input class="input" type="file" name="image" accept="image/*">
            <div class="small">JPG/PNG/WebP, max 2MB.</div>
          </div>

          <div class="col-4">
            <div class="card card-pad" style="box-shadow:none;">
              <h2 style="margin-bottom:8px;">Categories</h2>
              <div class="small" style="margin-bottom:10px;">Choose at least one.</div>

              <?php foreach ($categories as $c): ?>
                <?php $cid = (int)$c['category_id']; ?>
                <label style="display:flex; gap:10px; align-items:center; margin: 8px 0;">
                  <input type="checkbox" name="categories[]" value="<?= $cid ?>"
                    <?= in_array((string)$cid, array_map('strval', (array)$cat_ids), true) ? 'checked' : '' ?>>
                  <span><?= e($c['category_name']) ?></span>
                </label>
              <?php endforeach; ?>

              <div class="hr"></div>

              <button class="btn primary" type="submit" style="width:100%;">Post Listing</button>
              <div style="height:8px;"></div>
              <a class="btn" href="<?= BASE_URL ?>/seller/listings.php" style="width:100%; text-align:center;">Cancel</a>
            </div>
          </div>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
