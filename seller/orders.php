<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "seller");
if (session_status() === PHP_SESSION_NONE) session_start();

$seller_id = (int)$_SESSION['user_id'];

/* ============================
   UPDATE STATUS
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
  check_csrf();

  $order_item_id = (int)($_POST['order_item_id'] ?? 0);
  $new_status = $_POST['item_status'] ?? 'pending';

  $valid = ['pending','packed','shipped','cancelled'];

  if ($order_item_id > 0 && in_array($new_status, $valid, true)) {

    $stmt = mysqli_prepare(
      $conn,
      "UPDATE order_items
       SET item_status=?
       WHERE order_item_id=? AND seller_id=?"
    );

    mysqli_stmt_bind_param($stmt, "sii", $new_status, $order_item_id, $seller_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    flash_set('ok', 'Item status updated.');
  }

  redirect('/seller/orders.php');
}

require_once __DIR__ . '/../includes/header.php';

/* ============================
   LOAD SELLER ORDER ITEMS
============================ */
$stmt = mysqli_prepare($conn, "
  SELECT oi.order_item_id, oi.order_id, oi.quantity, oi.line_total, oi.item_status,
         o.created_at,
         pr.title,
         u.full_name AS buyer_name
  FROM order_items oi
  JOIN orders o ON o.order_id = oi.order_id
  JOIN products pr ON pr.product_id = oi.product_id
  JOIN users u ON u.user_id = o.buyer_id
  WHERE oi.seller_id = ?
  ORDER BY o.created_at DESC, oi.order_item_id DESC
");
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">Orders</h1>
          <div class="small">Manage fulfillment of your sold items.</div>
        </div>
        <div>
          <a class="btn small" href="<?= BASE_URL ?>/seller/seller_dashboard.php">
            ← Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">

<?php if (mysqli_num_rows($res) === 0): ?>

    <div class="card card-pad">
      <div class="flash">No orders yet.</div>
    </div>

<?php else: ?>

    <div class="table-responsive">
<table class="table">
      <tr>
        <th>Order</th>
        <th>Date</th>
        <th>Buyer</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Total</th>
        <th>Status</th>
        <th>Update</th>
      </tr>

      <?php while ($r = mysqli_fetch_assoc($res)): ?>

        <?php
          $status = $r['item_status'];
          $badge = ($status === 'shipped') ? 'ok'
                  : (($status === 'pending') ? 'warn'
                  : (($status === 'cancelled') ? 'danger' : ''));
        ?>

        <tr>
          <td>
            <strong>#<?= (int)$r['order_id'] ?></strong>
          </td>

          <td><?= e($r['created_at']) ?></td>

          <td><?= e($r['buyer_name']) ?></td>

          <td>
            <strong><?= e($r['title']) ?></strong>
          </td>

          <td><?= (int)$r['quantity'] ?></td>

          <td>
            <strong>€<?= number_format((float)$r['line_total'],2) ?></strong>
          </td>

          <td>
            <span class="badge <?= $badge ?>">
              <?= e($status) ?>
            </span>
          </td>

          <td>
            <form method="post" class="form-row" style="gap:6px;">
              <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_item_id"
                     value="<?= (int)$r['order_item_id'] ?>">

              <select class="input" name="item_status" style="min-width:110px;">
                <?php foreach (['pending','packed','shipped','cancelled'] as $st): ?>
                  <option value="<?= $st ?>"
                    <?= ($st === $status) ? 'selected' : '' ?>>
                    <?= ucfirst($st) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <button class="btn small" type="submit">
                Save
              </button>
            </form>
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
