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

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ============================
   HANDLE ACTIONS
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if (!empty($_POST['remove_product_id'])) {
    $pid = (int)$_POST['remove_product_id'];
    if ($pid > 0) {
      unset($_SESSION['cart'][$pid]);
      flash_set('ok', 'Item removed.');
    }
    redirect('/public/cart.php');
  }

  if ($action === 'add') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    if ($pid > 0) {
      $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
      flash_set('ok', 'Item added to cart.');
    }

    redirect('/public/store.php');
  }

  if ($action === 'update') {
    foreach ($_POST['qty'] ?? [] as $pid => $qty) {
      $pid = (int)$pid;
      $qty = (int)$qty;

      if ($qty <= 0) unset($_SESSION['cart'][$pid]);
      else $_SESSION['cart'][$pid] = $qty;
    }

    flash_set('ok', 'Cart updated.');
    redirect('/public/cart.php');
  }

}

require_once __DIR__ . '/../includes/header.php';

/* ============================
   LOAD CART PRODUCTS
============================ */
$cart = $_SESSION['cart'];
$product_rows = [];
$total = 0.0;

if ($cart) {
  $ids = array_keys($cart);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $sql = "SELECT product_id, title, price, stock, status
          FROM products
          WHERE product_id IN ($placeholders)";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, $types, ...$ids);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  while ($p = mysqli_fetch_assoc($res)) {
    $pid = (int)$p['product_id'];
    $qty = max(1, (int)$cart[$pid]);

    $available = ($p['status'] === 'active' && (int)$p['stock'] > 0);
    $max_qty = (int)$p['stock'];

    if ($available && $qty > $max_qty) {
      $qty = $max_qty;
      $_SESSION['cart'][$pid] = $qty;
    }

    $line = $available ? ($qty * (float)$p['price']) : 0;
    $total += $line;

    $product_rows[] = [
      'product_id' => $pid,
      'title' => $p['title'],
      'price' => (float)$p['price'],
      'stock' => $max_qty,
      'status' => $p['status'],
      'qty' => $qty,
      'available' => $available,
      'line_total' => $line
    ];
  }

  mysqli_stmt_close($stmt);
}
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">Your Cart</h1>
          <div class="small">Review your selected items before checkout.</div>
        </div>
        <div style="display: flex; justify-content: flex-end;">
          <a class="btn small" href="<?= BASE_URL ?>/public/index.php">← Continue Shopping</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">

<?php if (!$cart): ?>

    <div class="card card-pad">
      <div class="flash">Your cart is empty.</div>
    </div>

<?php else: ?>

    <form method="post" class="no-disable">
      <!-- Default submit button for Enter key presses -->
      <button type="submit" name="action" value="update" style="display:none;" aria-hidden="true"></button>
      <input type="hidden" name="action" value="update">

      <div class="table-responsive">
<table class="table">
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th style="width:120px;">Qty</th>
          <th>Line Total</th>
          <th style="width:100px;">Remove</th>
        </tr>

        <?php foreach ($product_rows as $r): ?>
          <tr>
            <td>
              <strong><?= e($r['title']) ?></strong>
              <?php if (!$r['available']): ?>
                <div class="badge danger" style="margin-top:6px;">Unavailable</div>
              <?php endif; ?>
            </td>

            <td>€<?= number_format($r['price'], 2) ?></td>

            <td>
              <input
                class="input"
                type="number"
                name="qty[<?= (int)$r['product_id'] ?>]"
                value="<?= (int)$r['qty'] ?>"
                min="0"
                max="<?= (int)$r['stock'] ?>"
              >
            </td>

            <td><strong>€<?= number_format($r['line_total'], 2) ?></strong></td>

            <td>
              <button
                class="btn danger small"
                type="submit"
                name="remove_product_id"
                value="<?= (int)$r['product_id'] ?>"
                formnovalidate
              >
                Remove
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
</div>

      <div class="card card-pad" style="margin-top:15px;">
        <div class="form-row" style="align-items:center;">
          <div>
            <strong style="font-size:18px;">Total: €<?= number_format($total, 2) ?></strong>
          </div>
          <div style="text-align:right;">
            <button class="btn" type="submit">Update Cart</button>
            <a class="btn primary" href="<?= BASE_URL ?>/public/checkout.php">Proceed to Checkout</a>
          </div>
        </div>
      </div>

    </form>

<?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
