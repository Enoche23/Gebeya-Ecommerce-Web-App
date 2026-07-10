<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ============================
   ENFORCE BUYER-ONLY ACCESS
============================ */
require_buyer_only($conn);

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
  flash_set('danger', 'Invalid order.');
  redirect('/public/my_orders.php');
}

$buyer_id = (int)$_SESSION['user_id'];

/* =============================
   LOAD ORDER
============================= */
$stmt = mysqli_prepare($conn, "
  SELECT o.order_id, o.order_status, o.total_amount, o.created_at,
         p.payment_method, p.payment_status
  FROM orders o
  LEFT JOIN payments p ON p.order_id = o.order_id
  WHERE o.order_id = ? AND o.buyer_id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
  flash_set('danger', 'Order not found.');
  redirect('/public/my_orders.php');
}

/* =============================
   LOAD ITEMS
============================= */
$stmt = mysqli_prepare($conn, "
  SELECT oi.quantity, oi.unit_price, oi.line_total, oi.item_status,
         pr.title,
         u.full_name AS seller_name
  FROM order_items oi
  JOIN products pr ON pr.product_id = oi.product_id
  JOIN users u ON u.user_id = oi.seller_id
  WHERE oi.order_id = ?
  ORDER BY oi.order_item_id ASC
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$res_items = mysqli_stmt_get_result($stmt);

/* Badge helpers */
function badge_for_order_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  return '';
}

function badge_for_payment_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'failed') return 'danger';
  return '';
}

function badge_for_item_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'shipped') return 'ok';
  if ($s === 'packed') return 'warn';
  if ($s === 'pending') return 'warn';
  if ($s === 'cancelled') return 'danger';
  if ($s === 'delivered') return 'ok';
  return '';
}

require_once __DIR__ . '/../includes/header.php';

$status = (string)($order['order_status'] ?? 'pending');
$pay = (string)($order['payment_status'] ?? 'pending');
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">Order #<?= (int)$order['order_id'] ?></h1>
          <div class="small">Placed on <?= e($order['created_at']) ?></div>
        </div>

        <div style="text-align:right;">
          <span class="badge <?= e(badge_for_order_status($status)) ?>">
            Order: <?= ucfirst(e($status)) ?>
          </span>

          <span class="badge <?= e(badge_for_payment_status($pay)) ?>">
            Payment: <?= ucfirst(e($pay)) ?>
          </span>

          <div style="margin-top:8px;">
            <strong style="font-size:18px;">
              €<?= number_format((float)$order['total_amount'],2) ?>
            </strong>
          </div>
        </div>
      </div>

      <div class="hr"></div>

      <div class="small">
        <strong>Payment Method:</strong> <?= e($order['payment_method'] ?? '-') ?>
      </div>

      <div style="margin-top:12px;">
        <a class="btn small" href="<?= BASE_URL ?>/public/my_orders.php">← Back to My Orders</a>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-pad">
      <h2 style="margin-bottom:15px;">Items</h2>

      <div class="table-responsive">
<table class="table">
        <tr>
          <th>Product</th>
          <th>Seller</th>
          <th>Qty</th>
          <th>Unit</th>
          <th>Total</th>
          <th>Status</th>
        </tr>

        <?php while ($it = mysqli_fetch_assoc($res_items)): ?>
          <?php $itemStatus = (string)($it['item_status'] ?? 'pending'); ?>
          <tr>
            <td><strong><?= e($it['title']) ?></strong></td>
            <td><?= e($it['seller_name']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td>€<?= number_format((float)$it['unit_price'],2) ?></td>
            <td><strong>€<?= number_format((float)$it['line_total'],2) ?></strong></td>
            <td>
              <span class="badge <?= e(badge_for_item_status($itemStatus)) ?>">
                <?= ucfirst(e($itemStatus)) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
</div>
    </div>
  </div>

</div>

<?php
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../includes/footer.php';
