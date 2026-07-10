<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "admin");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <!--<h1 style="margin-bottom:5px;">Admin Dashboard</h1>-->
      <div class="small">Manage categories, listings, orders, and users.</div>
    </div>
  </div>

  <div class="col-12">
    <div class="grid">

      <div class="col-3">
        <div class="card card-pad">
          <h2 style="margin-bottom:8px;">Categories</h2>
          <div class="small" style="margin-bottom:12px;">Create and manage marketplace categories.</div>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/categories.php" style="width:100%; text-align:center;">
            Manage Categories
          </a>
        </div>
      </div>

      <div class="col-3">
        <div class="card card-pad">
          <h2 style="margin-bottom:8px;">Listings</h2>
          <div class="small" style="margin-bottom:12px;">Approve, hide, or review products.</div>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/listings.php" style="width:100%; text-align:center;">
            Moderate Listings
          </a>
        </div>
      </div>

      <div class="col-3">
        <div class="card card-pad">
          <h2 style="margin-bottom:8px;">Orders</h2>
          <div class="small" style="margin-bottom:12px;">View all orders and payment records.</div>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/orders.php" style="width:100%; text-align:center;">
            View Orders
          </a>
        </div>
      </div>

      <div class="col-3">
        <div class="card card-pad">
          <h2 style="margin-bottom:8px;">Users</h2>
          <div class="small" style="margin-bottom:12px;">Monitor users and platform activity.</div>
          <a class="btn primary" href="<?= BASE_URL ?>/admin/users.php" style="width:100%; text-align:center;">
            Manage Users
          </a>
        </div>
      </div>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
