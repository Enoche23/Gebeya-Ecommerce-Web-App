<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ENFORCE BUYER-ONLY ACCESS */
require_buyer_only($conn);

if (empty($_SESSION['cart'])) {
  flash_set('danger', 'Your cart is empty.');
  redirect('/public/index.php');
}

$buyer_id = (int)$_SESSION['user_id'];
$errors = [];

/* Keep selections on validation errors */
$selected_address_id = (int)($_POST['address_id'] ?? 0);
$selected_payment = $_POST['payment_method'] ?? 'cash_on_delivery';

/* LOAD ADDRESSES */
$addresses = [];
$stmt = mysqli_prepare($conn, "
  SELECT address_id, label, city, street, house_no
  FROM addresses
  WHERE user_id=?
  ORDER BY created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($a = mysqli_fetch_assoc($res)) $addresses[] = $a;
mysqli_stmt_close($stmt);

/* LOAD CART ITEMS */
$cart = $_SESSION['cart'];
$ids = array_keys($cart);

$items = [];
$total = 0.0;

if ($ids) {
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $stmt = mysqli_prepare($conn, "
    SELECT product_id, seller_id, title, price, stock, status
    FROM products
    WHERE product_id IN ($placeholders)
  ");
  mysqli_stmt_bind_param($stmt, $types, ...$ids);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  while ($p = mysqli_fetch_assoc($res)) {
    $pid = (int)$p['product_id'];
    $qty = min((int)$cart[$pid], (int)$p['stock']);

    if ($p['status'] !== 'active' || (int)$p['stock'] <= 0) continue;
    if ($qty <= 0) continue;

    $unit = (float)$p['price'];
    $line = $qty * $unit;
    $total += $line;

    $items[] = [
      'product_id' => $pid,
      'seller_id' => (int)$p['seller_id'],
      'title' => $p['title'],
      'qty' => $qty,
      'unit_price' => $unit,
      'line_total' => $line
    ];
  }
  mysqli_stmt_close($stmt);
}

if (!$items) {
  flash_set('danger', 'Cart items unavailable.');
  redirect('/public/cart.php');
}

/* HANDLE ORDER */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $address_id = (int)($_POST['address_id'] ?? 0);
  $payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';

  $valid = ['cash_on_delivery','bank_transfer','mobile_money','card'];

  if ($address_id <= 0) $errors[] = "Select a delivery address.";
  if (!in_array($payment_method, $valid, true)) $errors[] = "Invalid payment method.";

  // keep selections on errors
  $selected_address_id = $address_id;
  $selected_payment = $payment_method;

  if (!$errors) {
    mysqli_begin_transaction($conn);

    try {
      // Create order
      $stmt = mysqli_prepare($conn,
        "INSERT INTO orders (buyer_id, address_id, order_status, total_amount)
         VALUES (?, ?, 'pending', ?)"
      );
      mysqli_stmt_bind_param($stmt, "iid", $buyer_id, $address_id, $total);
      mysqli_stmt_execute($stmt);
      $order_id = mysqli_insert_id($conn);
      mysqli_stmt_close($stmt);

      // Insert items + reduce stock
      $oi = mysqli_prepare($conn,
        "INSERT INTO order_items
         (order_id, product_id, seller_id, quantity, unit_price, line_total, item_status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
      );

      $stock_stmt = mysqli_prepare($conn,
        "UPDATE products SET stock = stock - ?
         WHERE product_id = ? AND stock >= ?"
      );

      foreach ($items as $it) {
        mysqli_stmt_bind_param(
          $oi,
          "iiiidd",
          $order_id,
          $it['product_id'],
          $it['seller_id'],
          $it['qty'],
          $it['unit_price'],
          $it['line_total']
        );
        mysqli_stmt_execute($oi);

        mysqli_stmt_bind_param($stock_stmt, "iii",
          $it['qty'],
          $it['product_id'],
          $it['qty']
        );
        mysqli_stmt_execute($stock_stmt);

        if (mysqli_stmt_affected_rows($stock_stmt) !== 1) {
          throw new Exception("Stock failed");
        }
      }

      mysqli_stmt_close($oi);
      mysqli_stmt_close($stock_stmt);

      // Payment simulation
      $payment_status = in_array($payment_method, ['mobile_money','card']) ? 'paid' : 'pending';
      $paid_at = ($payment_status === 'paid') ? date('Y-m-d H:i:s') : null;

      $stmt = mysqli_prepare($conn,
        "INSERT INTO payments (order_id, payment_method, amount, payment_status, paid_at)
         VALUES (?, ?, ?, ?, ?)"
      );
      mysqli_stmt_bind_param($stmt, "isdss",
        $order_id,
        $payment_method,
        $total,
        $payment_status,
        $paid_at
      );
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      if ($payment_status === 'paid') {
        $up = mysqli_prepare($conn, "UPDATE orders SET order_status='paid' WHERE order_id=?");
        mysqli_stmt_bind_param($up, "i", $order_id);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
      }

      mysqli_commit($conn);

      $_SESSION['cart'] = [];
      flash_set('ok', 'Order placed successfully!');
      redirect('/public/my_orders.php');

    } catch (Throwable $e) {
      mysqli_rollback($conn);
      $errors[] = "Checkout failed. Try again.";
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">

  <div class="col-8">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <h2 style="margin:0;">Order Summary</h2>
        <a class="btn small" href="<?= BASE_URL ?>/public/cart.php">← Back to Cart</a>
      </div>

      <div class="hr"></div>

      <div class="table-responsive">
<table class="table">
        <tr>
          <th>Product</th>
          <th>Qty</th>
          <th>Unit</th>
          <th>Total</th>
        </tr>

        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['title']) ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td>€<?= number_format($it['unit_price'],2) ?></td>
            <td><strong>€<?= number_format($it['line_total'],2) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </table>
</div>

      <div style="text-align:right; margin-top:15px;">
        <strong style="font-size:18px;">
          Grand Total: €<?= number_format($total,2) ?>
        </strong>
      </div>
    </div>
  </div>

  <div class="col-4">
    <div class="card card-pad">
      <h2>Delivery & Payment</h2>

      <?php if ($errors): ?>
        <div class="flash danger">
          <?php foreach ($errors as $e): ?>
            <div><?= e($e) ?></div>
          <?php endforeach; ?>
        </div>
        <div style="height:10px;"></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <label class="small"><strong>Delivery Address</strong></label>
        <?php if (!$addresses): ?>
          <div class="flash danger" style="margin-top:10px;">
            No address found.
            <a href="<?= BASE_URL ?>/public/profile.php">Add one in Profile</a>.
          </div>
        <?php else: ?>
          <select class="input" name="address_id" required>
            <option value="">Select address</option>
            <?php foreach ($addresses as $a): ?>
              <?php $aid = (int)$a['address_id']; ?>
              <option value="<?= $aid ?>" <?= $selected_address_id === $aid ? 'selected' : '' ?>>
                <?= e($a['label']) ?> — <?= e($a['city']) ?>, <?= e($a['street']) ?> <?= e($a['house_no'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <div style="height:15px;"></div>

        <label class="small"><strong>Payment Method</strong></label>
        <select class="input" name="payment_method">
          <option value="cash_on_delivery" <?= $selected_payment === 'cash_on_delivery' ? 'selected' : '' ?>>Cash on Delivery</option>
          <option value="bank_transfer"    <?= $selected_payment === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
          <option value="mobile_money"     <?= $selected_payment === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
          <option value="card"             <?= $selected_payment === 'card' ? 'selected' : '' ?>>Card</option>
        </select>

        <div style="height:20px;"></div>

        <button class="btn primary" style="width:100%;" type="submit">
          Place Order
        </button>

      </form>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
