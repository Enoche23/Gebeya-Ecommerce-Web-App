<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "admin");
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/header.php';

function badge_order(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  return '';
}

function badge_payment(string $s): string {
  $s = strtolower($s);
  if ($s === 'paid') return 'ok';
  if ($s === 'pending') return 'warn';
  if ($s === 'failed') return 'danger';
  return '';
}

$res = mysqli_query($conn, "
  SELECT o.order_id, o.created_at, o.order_status, o.total_amount,
         u.full_name AS buyer_name,
         p.payment_method, p.payment_status
  FROM orders o
  JOIN users u ON u.user_id = o.buyer_id
  LEFT JOIN payments p ON p.order_id = o.order_id
  ORDER BY o.created_at DESC
");
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">All Orders & Payments</h1>
          <div class="small">Monitor marketplace transactions and payment states.</div>
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
        <h2 style="margin:0;">Orders</h2>
        <span class="badge"><?= (int)mysqli_num_rows($res) ?> total</span>
      </div>

      <div class="hr"></div>

      <?php if (mysqli_num_rows($res) === 0): ?>
        <div class="flash">No orders found.</div>
      <?php else: ?>
        <div class="table-responsive">
<table class="table">
          <tr>
            <th style="width:90px;">Order</th>
            <th style="width:190px;">Date</th>
            <th>Buyer</th>
            <th style="width:130px;">Total</th>
            <th style="width:140px;">Order Status</th>
            <th style="width:190px;">Payment</th>
            <th style="width:120px;">Action</th>
          </tr>

          <?php while ($o = mysqli_fetch_assoc($res)): ?>
            <?php
              $oid = (int)$o['order_id'];
              $orderStatus = (string)($o['order_status'] ?? '');
              $paymentStatus = (string)($o['payment_status'] ?? 'pending');
              $paymentMethod = (string)($o['payment_method'] ?? '-');

              $orderCls = badge_order($orderStatus);
              $payCls = badge_payment($paymentStatus);
            ?>
            <tr>
              <td><strong>#<?= $oid ?></strong></td>
              <td class="small"><?= e($o['created_at']) ?></td>
              <td><?= e($o['buyer_name']) ?></td>
              <td><strong>€<?= number_format((float)$o['total_amount'], 2) ?></strong></td>

              <td>
                <span class="badge <?= e($orderCls) ?>">
                  <?= e($orderStatus) ?>
                </span>
              </td>

              <td>
                <div class="small"><strong><?= e($paymentMethod) ?></strong></div>
                <span class="badge <?= e($payCls) ?>">
                  <?= e($paymentStatus) ?>
                </span>
              </td>

              <td>
                <a class="btn small" href="<?= BASE_URL ?>/public/order_detail.php?id=<?= $oid ?>" target="_blank" rel="noopener">
                  View
                </a>
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
