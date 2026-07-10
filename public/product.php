<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  ?>
  <div class="card card-pad">
    <div class="flash danger">Invalid product.</div>
    <div class="hr"></div>
    <a class="btn" href="<?= BASE_URL ?>/public/index.php">← Back to Marketplace</a>
  </div>
  <?php
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

$sql = "SELECT p.*, u.full_name AS seller_name
        FROM products p
        JOIN users u ON u.user_id = p.seller_id
        WHERE p.product_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product || $product['status'] !== 'active') {
  ?>
  <div class="card card-pad">
    <div class="flash">Product not available.</div>
    <div class="hr"></div>
    <a class="btn" href="<?= BASE_URL ?>/public/index.php">← Back to Marketplace</a>
  </div>
  <?php
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

$stock = (int)$product['stock'];

// ✅ robust image handling (treat '0' as empty)
$imgPath = $product['image_path'] ?? '';
$img = (!empty($imgPath) && $imgPath !== '0') ? (BASE_URL . $imgPath) : null;
$alt = $product['image_alt'] ?: ($product['title'] ?? 'Product');
?>

<div class="grid">
  <div class="col-12">
    <a class="btn small" href="<?= BASE_URL ?>/public/index.php">← Back to Marketplace</a>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="grid" style="gap:0;">
        <!-- Image -->
        <div class="col-6">
          <!-- ✅ dedicated product detail wrapper (no crop) -->
          <div class="product-detail-media">
            <?php if ($img): ?>
              <img src="<?= e($img) ?>" alt="<?= e($alt) ?>" loading="lazy">
            <?php else: ?>
              <span class="small">No Image</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Details -->
        <div class="col-6">
          <div class="card-pad">
            <h1 style="margin-bottom:6px;"><?= e($product['title']) ?></h1>

            <div class="product-meta" style="margin-bottom:10px;">
              <span class="badge">Seller: <?= e($product['seller_name']) ?></span>
              <span class="badge"><?= e($product['product_condition']) ?></span>
            </div>

            <div class="hr"></div>

            <div class="form-row" style="align-items:center;">
              <div>
                <div class="small">Price</div>
                <div style="font-size:22px; font-weight:900;">
                  €<?= number_format((float)$product['price'], 2) ?>
                </div>
              </div>
              <div style="text-align:right;">
                <?php if ($stock > 0): ?>
                  <span class="badge ok"><?= $stock ?> in stock</span>
                <?php else: ?>
                  <span class="badge danger">Out of stock</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="hr"></div>

            <?php if (!empty($product['description'])): ?>
              <div class="small" style="margin-bottom:6px;"><strong>Description</strong></div>
              <div style="white-space:pre-wrap;"><?= e($product['description']) ?></div>
              <div class="hr"></div>
            <?php endif; ?>

            <?php if ($stock > 0): ?>
              <form method="post" action="<?= BASE_URL ?>/public/cart.php" style="margin:0;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">

                <div class="form-row" style="align-items:end;">
                  <div style="max-width:150px;">
                    <label class="small"><strong>Quantity</strong></label>
                    <input class="input" type="number" name="qty" min="1" max="<?= $stock ?>" value="1">
                  </div>
                  <div style="flex:1;">
                    <button class="btn primary" type="submit" style="width:100%;">Add to cart</button>
                  </div>
                </div>
              </form>
            <?php else: ?>
              <div class="flash danger"><strong>Out of stock.</strong></div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
