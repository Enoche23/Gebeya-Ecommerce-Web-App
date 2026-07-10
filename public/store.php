<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);

$params = [];
$types = "";

// Include image columns
$sql = "SELECT DISTINCT
          p.product_id, p.title, p.price, p.stock, p.product_condition, p.created_at,
          p.image_path, p.image_alt,
          u.full_name AS seller_name
        FROM products p
        JOIN users u ON u.user_id = p.seller_id
        LEFT JOIN product_categories pc ON pc.product_id = p.product_id
        WHERE p.status = 'active' AND p.stock > 0";

if ($search !== '') {
  $sql .= " AND p.title LIKE ?";
  $params[] = "%" . $search . "%";
  $types .= "s";
}

if ($category_id > 0) {
  $sql .= " AND pc.category_id = ?";
  $params[] = $category_id;
  $types .= "i";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
  mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$categories = get_categories($conn);
?>



<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">Gebeya Marketplace</h1>
          <div class="small">Browse items from sellers near you.</div>

          <!-- Optional hint for users -->
          <div class="small" style="margin-top:6px;">
            <!--Use the <strong>🔍</strong> icon in the top bar to search & filter.-->
          </div>
        </div>
        <div style="text-align:right;">
          <span class="badge"><?= (int)mysqli_num_rows($res) ?> results</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <?php if (mysqli_num_rows($res) === 0): ?>
      <div class="card card-pad">
        <div class="flash">No products found.</div>
      </div>
    <?php else: ?>
      <div class="products">
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
          <?php
            $pid = (int)$row['product_id'];
            $title = $row['title'] ?? '';
            $stock = (int)$row['stock'];
            $img = !empty($row['image_path']) ? (BASE_URL . $row['image_path']) : null;
            $alt = $row['image_alt'] ?: $title;
          ?>

          <div class="product-card">
            <a class="product-media" href="<?= BASE_URL ?>/public/product.php?id=<?= $pid ?>">
              <?php if ($img): ?>
                <img
                  src="<?= e($img) ?>"
                  alt="<?= e($alt) ?>"
                  style="max-width:100%; max-height:100%; object-fit:contain;"
                  loading="lazy"
                >
              <?php else: ?>
                No Image
              <?php endif; ?>
            </a>

            <div class="product-body">
              <div class="product-title">
                <a href="<?= BASE_URL ?>/public/product.php?id=<?= $pid ?>"><?= e($title) ?></a>
              </div>

              <div class="product-meta">
                <span><?= e($row['seller_name']) ?></span>
                <span class="badge"><?= e($row['product_condition']) ?></span>
              </div>

              <div class="product-meta">
                <strong>€<?= number_format((float)$row['price'], 2) ?></strong>
                <span class="small"><?= $stock ?> in stock</span>
              </div>

              <div class="product-actions">
                <form method="post" action="<?= BASE_URL ?>/public/cart.php" style="margin:0; display:flex; gap:10px; width:100%;">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= $pid ?>">

                  <input
                    class="input"
                    type="number"
                    name="qty"
                    min="1"
                    max="<?= $stock ?>"
                    value="1"
                    style="width:90px;"
                  >

                  <button class="btn primary" type="submit" style="flex:1;">Add to cart</button>
                </form>
              </div>

              <div class="small">Posted: <?= e($row['created_at']) ?></div>
            </div>
          </div>

        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../includes/footer.php';
