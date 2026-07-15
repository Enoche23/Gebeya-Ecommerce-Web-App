<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ENFORCE BUYER-ONLY ACCESS */
require_buyer_only($conn);

$buyer_id = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "
  SELECT o.order_id, o.order_status, o.total_amount, o.created_at,
         p.payment_status
  FROM orders o
  LEFT JOIN payments p ON p.order_id = o.order_id
  WHERE o.buyer_id = ?
  ORDER BY o.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

require_once __DIR__ . '/../includes/header.php';

/* Badge helpers */
function badge_for_order_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'cancelled') return 'danger';
  return '';
}

function badge_for_payment_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'failed') return 'danger';
  return '';
}
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <h1 style="margin-bottom:5px;">My Orders</h1>
      <div class="small">Track your purchases and payment status.</div>
    </div>
  </div>

  <div class="col-12">

<?php if (mysqli_num_rows($res) === 0): ?>

    <div class="card card-pad">
      <div class="flash">You have not placed any orders yet.</div>
      <div style="margin-top:10px;">
        <a class="btn primary" href="<?= BASE_URL ?>/public/index.php">Start Shopping</a>
      </div>
    </div>

<?php else: ?>

    <?php while ($o = mysqli_fetch_assoc($res)): ?>
      <?php
        $status = (string)$o['order_status'];
        $pay = (string)($o['payment_status'] ?? 'pending');
      ?>

      <div class="card card-pad" style="margin-bottom:15px;">
        <div class="form-row" style="align-items:center;">

          <div>
            <strong>Order #<?= (int)$o['order_id'] ?></strong>
            <div class="small">
              Placed on <?= e($o['created_at']) ?>
            </div>
          </div>

          <div style="text-align:right;">
            <div>
              <span class="badge <?= badge_for_order_status($status) ?>">
                Order: <?= ucfirst(e($status)) ?>
              </span>

              <span class="badge <?= badge_for_payment_status($pay) ?>">
                Payment: <?= ucfirst(e($pay)) ?>
              </span>
            </div>

            <div style="margin-top:8px;">
              <strong>€<?= number_format((float)$o['total_amount'],2) ?></strong>
            </div>

            <div style="margin-top:8px;">
              <a class="btn small" href="<?= BASE_URL ?>/public/order_detail.php?id=<?= (int)$o['order_id'] ?>">
                View Details
              </a>
            </div>
          </div>

        </div>
      </div>

    <?php endwhile; ?>

<?php endif; ?>

  </div>
</div>

<?php
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../includes/footer.php';
