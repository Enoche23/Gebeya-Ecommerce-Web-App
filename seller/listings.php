<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_role($conn, "seller");
require_once __DIR__ . '/../includes/header.php';

$seller_id = (int)($_SESSION['user_id'] ?? 0);
$success = "";
$errors = [];

/* DELETE LISTING (and image) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  check_csrf();
  $product_id = (int)($_POST['product_id'] ?? 0);

  if ($product_id > 0) {
    // Fetch image_path first (so we can delete the file after DB delete)
    $imgPath = null;
    $stmtImg = mysqli_prepare(
      $conn,
      "SELECT image_path FROM products WHERE product_id = ? AND seller_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtImg, "ii", $product_id, $seller_id);
    mysqli_stmt_execute($stmtImg);
    $imgRes = mysqli_stmt_get_result($stmtImg);
    if ($r = mysqli_fetch_assoc($imgRes)) {
      $imgPath = $r['image_path'] ?? null;
    }
    mysqli_stmt_close($stmtImg);

    // Delete product
    $stmtDel = mysqli_prepare(
      $conn,
      "DELETE FROM products WHERE product_id = ? AND seller_id = ?"
    );
    mysqli_stmt_bind_param($stmtDel, "ii", $product_id, $seller_id);
    mysqli_stmt_execute($stmtDel);

    if (mysqli_stmt_affected_rows($stmtDel) > 0) {
      $success = "Listing deleted successfully.";

      // Delete the image file if it exists and looks valid (optional cleanup)
      if (!empty($imgPath) && $imgPath !== '0' && str_starts_with($imgPath, '/uploads/products/')) {
        $abs = __DIR__ . '/../' . ltrim($imgPath, '/');
        if (is_file($abs)) @unlink($abs);
      }

    } else {
      $errors[] = "Unable to delete listing.";
    }
    mysqli_stmt_close($stmtDel);
  }
}

/* FETCH SELLER LISTINGS */
$stmt = mysqli_prepare(
  $conn,
  "SELECT product_id, title, price, stock, product_condition, status, created_at, image_path
   FROM products
   WHERE seller_id = ?
   ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function status_badge_class(string $s): string {
  $s = strtolower($s);
  if ($s === 'active') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'hidden') return 'danger';
  return '';
}
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">My Listings</h1>
          <div class="small">Manage your products and keep stock updated.</div>
        </div>
        <div style="text-align:right;">
          <a class="btn small" href="<?= BASE_URL ?>/seller/seller_dashboard.php">← Dashboard</a>
          <a class="btn primary small" href="<?= BASE_URL ?>/seller/listing_form.php">+ Create New Listing</a>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="hr"></div>
        <div class="flash ok"><strong><?= e($success) ?></strong></div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="hr"></div>
        <div class="flash danger">
          <strong>Action failed:</strong>
          <ul style="margin:8px 0 0 18px;">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12">
    <?php if (mysqli_num_rows($result) === 0): ?>
      <div class="card card-pad">
        <div class="flash">You have not created any listings yet.</div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
<table class="table">
        <tr>
          <th style="width:84px;">Image</th>
          <th>Title</th>
          <th style="width:110px;">Price</th>
          <th style="width:90px;">Stock</th>
          <th style="width:110px;">Condition</th>
          <th style="width:110px;">Status</th>
          <th style="width:170px;">Created</th>
          <th style="width:170px;">Actions</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <?php
            $pid = (int)$row['product_id'];
            $img = (!empty($row['image_path']) && $row['image_path'] !== '0')
              ? (BASE_URL . $row['image_path'])
              : null;

            $stock = (int)$row['stock'];
            $stockBadge = $stock > 0 ? 'ok' : 'danger';
            $status = (string)($row['status'] ?? '');
            $statusClass = status_badge_class($status);
          ?>
          <tr>
            <td>
              <div style="width:64px; height:48px; border-radius:10px; overflow:hidden; border:1px solid var(--border); background:#e2e8f0; display:flex; align-items:center; justify-content:center;">
                <?php if ($img): ?>
                  <img src="<?= e($img) ?>" alt="thumb" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                  <span class="small">No</span>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <div style="font-weight:900;"><?= e($row['title']) ?></div>
              <div class="small">ID: <?= $pid ?></div>
            </td>

            <td>€<?= number_format((float)$row['price'], 2) ?></td>

            <td>
              <span class="badge <?= $stockBadge ?>"><?= $stock ?> left</span>
            </td>

            <td><span class="badge"><?= e($row['product_condition']) ?></span></td>

            <td>
              <span class="badge <?= e($statusClass) ?>"><?= e($status) ?></span>
            </td>

            <td class="small"><?= e($row['created_at']) ?></td>

            <td>
              <div style="display:flex; gap:10px; align-items:center;">
                <a class="btn small" href="<?= BASE_URL ?>/seller/edit_listing.php?id=<?= $pid ?>">Edit</a>

                <form method="post" style="margin:0;" onsubmit="return confirm('Delete this listing?');">
                  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="product_id" value="<?= $pid ?>">
                  <button class="btn danger small" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
</div>
    <?php endif; ?>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../includes/footer.php';
