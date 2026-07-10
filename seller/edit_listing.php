<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_role($conn, "seller");
require_once __DIR__ . '/../includes/header.php';

$seller_id  = (int)($_SESSION['user_id'] ?? 0);
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
  ?>
  <div class="card card-pad">
    <div class="flash danger">Invalid listing.</div>
    <div class="hr"></div>
    <a class="btn" href="<?= BASE_URL ?>/seller/listings.php">← Back to My Listings</a>
  </div>
  <?php
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

/*  Load product (ownership check) */
$stmt = mysqli_prepare(
  $conn,
  "SELECT product_id, seller_id, title, description, price, stock, product_condition, status, image_path, image_alt
   FROM products
   WHERE product_id = ? AND seller_id = ?
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "ii", $product_id, $seller_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
  ?>
  <div class="card card-pad">
    <div class="flash danger">Listing not found (or you don’t have permission).</div>
    <div class="hr"></div>
    <a class="btn" href="<?= BASE_URL ?>/seller/listings.php">← Back to My Listings</a>
  </div>
  <?php
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

/* Load categories + selected */
$categories = get_categories($conn);

$selected = [];
$stmtSel = mysqli_prepare($conn, "SELECT category_id FROM product_categories WHERE product_id = ?");
mysqli_stmt_bind_param($stmtSel, "i", $product_id);
mysqli_stmt_execute($stmtSel);
$rsel = mysqli_stmt_get_result($stmtSel);
while ($row = mysqli_fetch_assoc($rsel)) $selected[] = (int)$row['category_id'];
mysqli_stmt_close($stmtSel);

/* Form state */
$errors = [];
$success = "";

$title       = (string)($product['title'] ?? '');
$description = (string)($product['description'] ?? '');
$price       = (string)($product['price'] ?? '');
$stock       = (string)($product['stock'] ?? '0');
$condition   = (string)($product['product_condition'] ?? 'used');
$status      = (string)($product['status'] ?? 'active');

$imagePath = (!empty($product['image_path']) && $product['image_path'] !== '0') ? $product['image_path'] : null;
$imageAlt  = (string)($product['image_alt'] ?? '');

/* Handle POST update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $price = (float)($_POST['price'] ?? 0);
  $stock = (int)($_POST['stock'] ?? 0);
  $condition = $_POST['product_condition'] ?? 'used';
  $status = $_POST['status'] ?? 'active';
  $cat_ids = $_POST['categories'] ?? [];

  if ($title === "") $errors[] = "Title is required.";
  if ($price <= 0) $errors[] = "Price must be greater than 0.";
  if ($stock < 0) $errors[] = "Stock cannot be negative.";
  if (!in_array($condition, ["new", "used"], true)) $errors[] = "Invalid condition.";
  if (!in_array($status, ["active", "hidden", "pending"], true)) $errors[] = "Invalid status.";
  if (!is_array($cat_ids) || count($cat_ids) === 0) $errors[] = "Select at least one category.";

  // keep old path so we can delete after successful commit
  $oldImagePath = $imagePath;

  if (!$errors) {
    mysqli_begin_transaction($conn);

    $uploadedAbsPath = null;
    $newImagePath = null;
    $newImageAlt  = null;

    try {
      /* 1) Update product fields FIRST (no image yet) */
      $stmtUp = mysqli_prepare(
        $conn,
        "UPDATE products
         SET title=?, description=?, price=?, stock=?, product_condition=?, status=?
         WHERE product_id=? AND seller_id=?"
      );
      mysqli_stmt_bind_param(
        $stmtUp,
        "ssdissii",
        $title,
        $description,
        $price,
        $stock,
        $condition,
        $status,
        $product_id,
        $seller_id
      );
      mysqli_stmt_execute($stmtUp);
      mysqli_stmt_close($stmtUp);

      /* 2) Update categories: delete then insert */
      $stmtDelCats = mysqli_prepare($conn, "DELETE FROM product_categories WHERE product_id=?");
      mysqli_stmt_bind_param($stmtDelCats, "i", $product_id);
      mysqli_stmt_execute($stmtDelCats);
      mysqli_stmt_close($stmtDelCats);

      $stmtInsCats = mysqli_prepare($conn, "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
      foreach ($cat_ids as $cid) {
        $cid = (int)$cid;
        mysqli_stmt_bind_param($stmtInsCats, "ii", $product_id, $cid);
        mysqli_stmt_execute($stmtInsCats);
      }
      mysqli_stmt_close($stmtInsCats);

      /* 3) If new image uploaded, upload AFTER DB updates */
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
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        if (!is_dir($destDir) || !is_writable($destDir)) {
          throw new Exception("Upload folder not writable: " . $destDir);
        }

        // traceable filename
        $name = $product_id . "_" . time() . "." . $ext;
        $dest = $destDir . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
          throw new Exception("Could not save uploaded image.");
        }

        $uploadedAbsPath = $dest;
        $newImagePath = '/uploads/products/' . $name;
        $newImageAlt  = $title ?: null;

        // Update image fields
        $stmtImg = mysqli_prepare(
          $conn,
          "UPDATE products SET image_path=?, image_alt=? WHERE product_id=? AND seller_id=?"
        );
        mysqli_stmt_bind_param($stmtImg, "ssii", $newImagePath, $newImageAlt, $product_id, $seller_id);
        mysqli_stmt_execute($stmtImg);
        mysqli_stmt_close($stmtImg);
      }

      mysqli_commit($conn);

      /* 4) After commit: delete old image if replaced */
      if ($newImagePath && !empty($oldImagePath) && $oldImagePath !== '0') {
        $okPrefix = (strpos($oldImagePath, '/uploads/products/') === 0);
        if ($okPrefix) {
          $absOld = __DIR__ . '/../' . ltrim($oldImagePath, '/');
          if (is_file($absOld)) @unlink($absOld);
        }
      }

      $success = "Listing updated successfully.";

      // refresh local display state
      if ($newImagePath) {
        $imagePath = $newImagePath;
        $imageAlt  = $newImageAlt ?: $title;
      } else {
        $imageAlt = $imageAlt ?: $title;
      }

      $selected = array_map('intval', $cat_ids);

    } catch (Throwable $e) {
      mysqli_rollback($conn);

      // If new image was uploaded but DB failed, delete new file
      if ($uploadedAbsPath && is_file($uploadedAbsPath)) {
        @unlink($uploadedAbsPath);
      }

      $errors[] = "Failed to update listing.";
      // For debugging while dev:
      // $errors[] = $e->getMessage();
    }
  }
}

/* For display */
$imgUrl = (!empty($imagePath) && $imagePath !== '0') ? (BASE_URL . $imagePath) : null;
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">Edit Listing</h1>
          <div class="small">Update your product details, stock, categories, and image.</div>
        </div>
        <div style="text-align:right;">
          <a class="btn small" href="<?= BASE_URL ?>/seller/listings.php">← My Listings</a>
          <a class="btn small" href="<?= BASE_URL ?>/seller/seller_dashboard.php">Seller Dashboard</a>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="hr"></div>
        <div class="flash ok"><strong><?= e($success) ?></strong></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="hr"></div>
        <div class="flash danger">
          <strong>Please fix the following:</strong>
          <ul style="margin:8px 0 0 18px;">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
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
            <textarea class="input" name="description"><?= e($description) ?></textarea>

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

            <div class="form-row">
              <div>
                <label><strong>Condition</strong></label>
                <select class="input" name="product_condition">
                  <option value="used" <?= $condition === 'used' ? 'selected' : '' ?>>Used</option>
                  <option value="new"  <?= $condition === 'new'  ? 'selected' : '' ?>>New</option>
                </select>
              </div>
              <div>
                <label><strong>Status</strong></label>
                <select class="input" name="status">
                  <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                  <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
              </div>
            </div>

            <div style="height:10px;"></div>

            <label><strong>Replace Image (optional)</strong></label>
            <input class="input" type="file" name="image" accept="image/*">
            <div class="small">JPG/PNG/WebP, max 2MB. Uploading a new image replaces the old one.</div>

          </div>

          <div class="col-4">
            <div class="card card-pad" style="box-shadow:none;">
              <h2 style="margin-bottom:8px;">Preview</h2>

              <!-- NO CROP PREVIEW: contain -->
              <div style="width:100%; aspect-ratio:4/3; border-radius:14px; overflow:hidden; border:1px solid var(--border); background:#e2e8f0; display:flex; align-items:center; justify-content:center;">
                <?php if ($imgUrl): ?>
                  <img src="<?= e($imgUrl) ?>" alt="preview" style="max-width:100%; max-height:100%; width:auto; height:auto; object-fit:contain;">
                <?php else: ?>
                  <span class="small">No Image</span>
                <?php endif; ?>
              </div>

              <div class="hr"></div>

              <h2 style="margin-bottom:8px;">Categories</h2>
              <div class="small" style="margin-bottom:10px;">Choose at least one.</div>

              <?php foreach ($categories as $c): ?>
                <?php $cid = (int)$c['category_id']; ?>
                <label style="display:flex; gap:10px; align-items:center; margin: 8px 0;">
                  <input type="checkbox" name="categories[]" value="<?= $cid ?>"
                    <?= in_array($cid, $selected, true) ? 'checked' : '' ?>>
                  <span><?= e($c['category_name']) ?></span>
                </label>
              <?php endforeach; ?>

              <div class="hr"></div>

              <button class="btn primary" type="submit" style="width:100%;">Save Changes</button>
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
