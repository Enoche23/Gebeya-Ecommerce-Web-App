<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "admin");
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/header.php';

function badge_for_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'active') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'hidden') return 'danger';
  return '';
}

/* UPDATE LISTING STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
  check_csrf();
  $pid = (int)($_POST['product_id'] ?? 0);
  $status = $_POST['status'] ?? 'active';
  $valid = ['active','hidden','pending'];

  if ($pid > 0 && in_array($status, $valid, true)) {
    $stmt = mysqli_prepare($conn, "UPDATE products SET status=? WHERE product_id=?");
    mysqli_stmt_bind_param($stmt, "si", $status, $pid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    flash_set('ok', 'Listing status updated.');
  } else {
    flash_set('danger', 'Invalid request.');
  }

  redirect('/admin/listings.php');
}

/* FETCH LISTINGS */
$res = mysqli_query($conn, "
  SELECT p.product_id, p.title, p.price, p.stock, p.status, p.created_at,
         u.full_name AS seller_name
  FROM products p
  JOIN users u ON u.user_id = p.seller_id
  ORDER BY p.created_at DESC
");
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">Moderate Listings</h1>
          <div class="small">Approve, hide, or keep listings pending.</div>
        </div>
        <div>
          <!--<a class="btn small" href="<?= BASE_URL ?>/admin/admin_dashboard.php">← Admin Dashboard</a>-->
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-pad">

      <div class="form-row" style="align-items:center;">
        <h2 style="margin:0;">All Listings</h2>
        <span class="badge"><?= (int)mysqli_num_rows($res) ?> total</span>
      </div>

      <div class="hr"></div>

      <?php if (mysqli_num_rows($res) === 0): ?>
        <div class="flash">No listings found.</div>
      <?php else: ?>
        <div class="table-responsive">
<table class="table">
          <tr>
            <th style="width:70px;">ID</th>
            <th>Title</th>
            <th style="width:190px;">Seller</th>
            <th style="width:120px;">Price</th>
            <th style="width:100px;">Stock</th>
            <th style="width:130px;">Status</th>
            <th style="width:220px;">Update</th>
          </tr>

          <?php while ($p = mysqli_fetch_assoc($res)): ?>
            <?php
              $pid = (int)$p['product_id'];
              $status = (string)$p['status'];
              $cls = badge_for_status($status);
              $stock = (int)$p['stock'];
              $stockCls = $stock > 0 ? 'ok' : 'danger';
            ?>
            <tr>
              <td><?= $pid ?></td>
              <td>
                <strong><?= e($p['title']) ?></strong>
                <div class="small">Posted: <?= e($p['created_at']) ?></div>
              </td>
              <td><?= e($p['seller_name']) ?></td>
              <td>€<?= number_format((float)$p['price'], 2) ?></td>
              <td><span class="badge <?= $stockCls ?>"><?= $stock ?></span></td>
              <td><span class="badge <?= e($cls) ?>"><?= e($status) ?></span></td>
              <td>
                <form method="post" class="form-row" style="gap:8px; align-items:end; margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="product_id" value="<?= $pid ?>">

                  <select class="input" name="status" style="min-width:120px;">
                    <?php foreach (['active','hidden','pending'] as $st): ?>
                      <option value="<?= $st ?>" <?= ($st === $status) ? 'selected' : '' ?>>
                        <?= ucfirst($st) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <button class="btn small" type="submit">Save</button>

                  <a class="btn small" href="<?= BASE_URL ?>/public/product.php?id=<?= $pid ?>" target="_blank" rel="noopener">
                    View
                  </a>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
</div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
