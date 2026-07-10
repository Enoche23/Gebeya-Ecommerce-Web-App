<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "admin");

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/header.php';

/* ADD CATEGORY  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  check_csrf();
  $name = trim($_POST['category_name'] ?? '');

  if ($name === '') {
    flash_set('danger', 'Category name is required.');
    redirect('/admin/categories.php');
  }

  $stmt = mysqli_prepare($conn, "INSERT INTO categories (category_name) VALUES (?)");
  mysqli_stmt_bind_param($stmt, "s", $name);

  try {
    mysqli_stmt_execute($stmt);
    flash_set('ok', 'Category added.');
  } catch (Throwable $e) {
    flash_set('danger', 'Category already exists or could not be added.');
  }

  mysqli_stmt_close($stmt);
  redirect('/admin/categories.php');
}

/* DELETE CATEGORY */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  check_csrf();
  $cid = (int)($_POST['category_id'] ?? 0);

  if ($cid <= 0) {
    flash_set('danger', 'Invalid category.');
    redirect('/admin/categories.php');
  }

  $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id=?");
  mysqli_stmt_bind_param($stmt, "i", $cid);

  try {
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
      flash_set('ok', 'Category deleted.');
    } else {
      flash_set('danger', 'Category not found.');
    }
  } catch (Throwable $e) {
    flash_set('danger', 'Cannot delete: category might be linked to products.');
  }

  mysqli_stmt_close($stmt);
  redirect('/admin/categories.php');
}

$cats = get_categories($conn);
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">Manage Categories</h1>
          <div class="small">Create and maintain marketplace categories.</div>
        </div>
        <div>
          <!--<a class="btn small" href="<?= BASE_URL ?>/admin/admin_dashboard.php">← Admin Dashboard</a>-->
        </div>
      </div>
    </div>
  </div>

  <!-- Add Category -->
  <div class="col-12">
    <div class="card card-pad">
      <h2 style="margin-bottom:10px;">Add Category</h2>

      <form method="post" class="form-row" style="gap:10px; align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="add">

        <div style="flex:1;">
          <label class="small"><strong>Category name</strong></label>
          <input class="input" name="category_name" placeholder="e.g., Electronics" required>
        </div>

        <div>
          <button class="btn primary" type="submit">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Existing Categories -->
  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <h2 style="margin:0;">Existing Categories</h2>
        <span class="badge"><?= count($cats) ?> total</span>
      </div>

      <div class="hr"></div>

      <?php if (!$cats): ?>
        <div class="flash">No categories yet.</div>
      <?php else: ?>
        <div class="table-responsive">
<table class="table">
          <tr>
            <th>Name</th>
            <th style="width:140px;">Action</th>
          </tr>

          <?php foreach ($cats as $c): ?>
            <tr>
              <td><strong><?= e($c['category_name']) ?></strong></td>
              <td>
                <form method="post" style="margin:0;" onsubmit="return confirm('Delete this category?');">
                  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                  <button class="btn danger small" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
</div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
