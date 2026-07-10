<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

/* Load Featured Products */
$stmt = mysqli_prepare($conn, "
  SELECT product_id, title, price, image_path
  FROM products
  WHERE status='active' AND stock > 0
  ORDER BY created_at DESC
  LIMIT 4
");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$slides = [];
while ($row = mysqli_fetch_assoc($res)) {
  $slides[] = $row;
}
mysqli_stmt_close($stmt);
?>

<!-- HERO SLIDER -->
<div class="hero-slider">

  <?php foreach ($slides as $index => $s): ?>
    <?php
      $img = !empty($s['image_path']) ? BASE_URL . $s['image_path'] : null;
    ?>
    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>">

      <div class="hero-content-vertical">

        <div class="hero-image-large">
          <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="">
          <?php endif; ?>
        </div>

        <div class="hero-text-below">
          <h1><?= e($s['title']) ?></h1>
          <p class="muted">
            Discover amazing deals and exclusive offers on Gebeya Marketplace.
          </p>
          <div style="margin-top:18px;">
            <a class="btn primary"
               href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$s['product_id'] ?>">
              Shop Now
            </a>
          </div>
        </div>

      </div>

    </div>
  <?php endforeach; ?>

  <!-- Arrows -->
  <!--
  <button class="hero-arrow left" id="prevSlide">&#10094;</button>
  <button class="hero-arrow right" id="nextSlide">&#10095;</button>
  -->
</div>

<!-- FEATURED PRODUCTS -->
<div class="container" style="margin-top:40px;">

  <div class="form-row" style="align-items:center;">
    <div>
      <h2>Featured Products</h2>
      <div class="small">Recently added items.</div>
    </div>
    <div style="text-align:right;">
      <a class="btn small" href="<?= BASE_URL ?>/public/store.php">Visit Store →</a>
    </div>
  </div>

  <div class="hr"></div>

  <div class="products">
    <?php foreach ($slides as $s): ?>
      <?php
        $img = !empty($s['image_path']) ? BASE_URL . $s['image_path'] : null;
      ?>
      <div class="product-card">
        <a class="product-media"
           href="<?= BASE_URL ?>/public/product.php?id=<?= (int)$s['product_id'] ?>">
          <?php if ($img): ?>
            <img src="<?= e($img) ?>" style="object-fit:contain;">
          <?php else: ?>
            No Image
          <?php endif; ?>
        </a>

        <div class="product-body">
          <div class="product-title"><?= e($s['title']) ?></div>
          <strong>€<?= number_format($s['price'], 2) ?></strong>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
