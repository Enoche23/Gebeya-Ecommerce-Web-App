<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

require_role($conn, "seller");
require_once __DIR__ . '/../includes/header.php';

$seller_id = (int)$_SESSION['user_id'];

/* QUICK STATS (same data, nicer summary) */
$stats = [
  'total_listings' => 0,
  'active_listings' => 0,
  'total_stock' => 0,
];

$st = mysqli_prepare($conn, "
  SELECT
    COUNT(*) AS total_listings,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_listings,
    COALESCE(SUM(stock), 0) AS total_stock
  FROM products
  WHERE seller_id = ?
");
mysqli_stmt_bind_param($st, "i", $seller_id);
mysqli_stmt_execute($st);
$r = mysqli_stmt_get_result($st);
if ($row = mysqli_fetch_assoc($r)) {
  $stats['total_listings'] = (int)$row['total_listings'];
  $stats['active_listings'] = (int)$row['active_listings'];
  $stats['total_stock'] = (int)$row['total_stock'];
}
mysqli_stmt_close($st);

/* LISTINGS */
$stmt = mysqli_prepare($conn, "
  SELECT product_id, title, price, stock, status, created_at
  FROM products
  WHERE seller_id = ?
  ORDER BY created_at DESC
  LIMIT 50
");
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

function badge_for_status(string $s): string {
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
          <h1 style="margin-bottom:5px;">Dashboard</h1>
          <div class="small">Manage your listings and orders from one place.</div>
        </div>
        <div style="text-align:right;">
          <a class="btn primary small" href="<?= BASE_URL ?>/seller/listing_form.php">+ Create Listing</a>
          <a class="btn small" href="<?= BASE_URL ?>/seller/listings.php">My Listings</a>
          <a class="btn small" href="<?= BASE_URL ?>/seller/orders.php">Orders</a>
        </div>
      </div>

      <div class="hr"></div>

      <div class="grid">
        <div class="col-4">
          <div class="card card-pad" style="box-shadow:none;">
            <div class="small">Total Listings</div>
            <div style="font-size:26px; font-weight:900;"><?= (int)$stats['total_listings'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="card card-pad" style="box-shadow:none;">
            <div class="small">Active Listings</div>
            <div style="font-size:26px; font-weight:900;"><?= (int)$stats['active_listings'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="card card-pad" style="box-shadow:none;">
            <div class="small">Total Stock</div>
            <div style="font-size:26px; font-weight:900;"><?= (int)$stats['total_stock'] ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <h2 style="margin:0;">Latest Listings</h2>
        <div class="small">Showing up to 50 newest items.</div>
      </div>

      <div class="hr"></div>

      <?php if (mysqli_num_rows($res) === 0): ?>
        <div class="flash">You don’t have any listings yet.</div>
        <div style="margin-top:10px;">
          <a class="btn primary" href="<?= BASE_URL ?>/seller/listing_form.php">Create your first listing</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
<table class="table">
          <tr>
            <th>Title</th>
            <th style="width:120px;">Price</th>
            <th style="width:110px;">Stock</th>
            <th style="width:120px;">Status</th>
            <th style="width:190px;">Created</th>
            <th style="width:110px;">Action</th>
          </tr>

          <?php while ($p = mysqli_fetch_assoc($res)): ?>
            <?php
              $pid = (int)$p['product_id'];
              $status = (string)$p['status'];
              $statusCls = badge_for_status($status);
              $stock = (int)$p['stock'];
              $stockCls = $stock > 0 ? 'ok' : 'danger';
            ?>
            <tr>
              <td><strong><?= e($p['title']) ?></strong></td>
              <td>€<?= number_format((float)$p['price'], 2) ?></td>
              <td><span class="badge <?= $stockCls ?>"><?= $stock ?></span></td>
              <td><span class="badge <?= e($statusCls) ?>"><?= e($status) ?></span></td>
              <td class="small"><?= e($p['created_at']) ?></td>
              <td>
                <a class="btn small" href="<?= BASE_URL ?>/seller/edit_listing.php?id=<?= $pid ?>">Edit</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
</div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../includes/footer.php';
