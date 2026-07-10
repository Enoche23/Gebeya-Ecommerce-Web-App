<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin  = $isLoggedIn ? has_role($conn, 'admin')  : false;
$isSeller = $isLoggedIn ? has_role($conn, 'seller') : false;

// In single-role mode, buyer is "not admin and not seller"
$isBuyer = $isLoggedIn && !$isAdmin && !$isSeller;

// Fetch categories for the global search drawer
$global_categories = get_categories($conn);
$global_search_val = $_GET['search'] ?? '';
$global_cat_val = (int)($_GET['category_id'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gebeya</title>

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <script defer src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</head>
<body>

<div class="navbar">
  <div class="nav-inner">

    <!-- Brand (Home) -->
    <a class="brand" href="<?= BASE_URL ?>/public/index.php">

  <svg class="brand-logo"
     viewBox="0 0 48 48"
     xmlns="http://www.w3.org/2000/svg"
     aria-hidden="true">

  <!-- Gradient (brand blue) -->
  <defs>
    <linearGradient id="gGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#2563eb"/>
      <stop offset="100%" stop-color="#1d4ed8"/>
    </linearGradient>
  </defs>

  <!-- Bag body -->
  <rect x="8" y="16" width="32" height="26"
        rx="6"
        fill="url(#gGrad)"/>

  <!-- Bag handle -->
  <path d="M16 16v-3a8 8 0 0 1 16 0v3"
        stroke="#1e40af"
        stroke-width="4"
        fill="none"
        stroke-linecap="round"/>

  <!-- Letter G -->
  <text x="24" y="34"
        text-anchor="middle"
        font-size="18"
        font-weight="800"
        fill="white"
        font-family="system-ui, sans-serif">
    G
  </text>

</svg>

    
    <!-- Bag handle -->
    <path d="M16 16v-3a8 8 0 0 1 16 0v3"
          stroke="#16a34a"
          stroke-width="4"
          fill="none"
          stroke-linecap="round"/>

    <!-- Letter G -->
    <text x="24" y="34"
          text-anchor="middle"
          font-size="18"
          font-weight="800"
          fill="white"
          font-family="system-ui, sans-serif">
      
    </text>
  </svg>

  <span class="brand-text">Gebeya</span>
</a>

    <!-- Hamburger Menu Toggle (Mobile Only) -->
    <button class="icon-btn hamburger-toggle" type="button" id="toggleNav" aria-label="Toggle Navigation">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <div class="nav-links" id="navLinks">

      <!-- Store button (Marketplace listings live here) -->
      <a href="<?= BASE_URL ?>/public/store.php">Store</a>

      <!-- Search icon (toggles search drawer) -->
      <button class="icon-btn" type="button" id="toggleSearch" aria-label="Search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"
                stroke="currentColor" stroke-width="2"/>
          <path d="M16.5 16.5 21 21"
                stroke="currentColor" stroke-width="2"
                stroke-linecap="round"/>
        </svg>
      </button>

      <?php if ($isLoggedIn): ?>

        <?php if ($isAdmin): ?>

          <a href="<?= BASE_URL ?>/admin/admin_dashboard.php">Admin Dashboard</a>
          <a href="<?= BASE_URL ?>/public/profile.php">Profile</a>

        <?php elseif ($isSeller): ?>

          <a href="<?= BASE_URL ?>/seller/seller_dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/seller/orders.php">Orders</a>
          <a href="<?= BASE_URL ?>/seller/listings.php">Listings</a>
          <a href="<?= BASE_URL ?>/public/profile.php">Profile</a>

        <?php elseif ($isBuyer): ?>

          <!--  Buyer Bag Dropdown -->
          <div class="menu">
            <button class="icon-btn" type="button" id="toggleBag" aria-label="Menu">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 8h12l-1 13H7L6 8Z"
                      stroke="currentColor" stroke-width="2"
                      stroke-linejoin="round"/>
                <path d="M9 8V7a3 3 0 0 1 6 0v1"
                      stroke="currentColor" stroke-width="2"
                      stroke-linecap="round"/>
              </svg>
            </button>

            <div class="menu-panel" id="bagMenu" hidden>
              <a href="<?= BASE_URL ?>/public/cart.php">Cart</a>
              <a href="<?= BASE_URL ?>/public/my_orders.php">My Orders</a>
              <a href="<?= BASE_URL ?>/public/profile.php">Profile</a>
              <div class="menu-sep"></div>
              <a class="danger" href="<?= BASE_URL ?>/public/logout.php">Logout</a>
            </div>
          </div>

        <?php endif; ?>

      <?php else: ?>

        <!-- Guest -->
        <a href="<?= BASE_URL ?>/public/login.php">Login</a>
        <!-- <a href="<?= BASE_URL ?>/public/register.php">Register</a> -->

      <?php endif; ?>

    </div>
  </div>
</div>

<!--  Search Drawer -->
<div id="searchPanel" class="search-panel">
  <div class="container">
    <form method="get" action="<?= BASE_URL ?>/public/store.php">
      <div class="grid">
        <div class="col-6">
          <label class="small"><strong>Search</strong></label>
          <input class="input" type="text" name="search" placeholder="Search products..."
                 value="<?= e($global_search_val) ?>">
        </div>

        <div class="col-4">
          <label class="small"><strong>Category</strong></label>
          <select class="input" name="category_id">
            <option value="0">All Categories</option>
            <?php foreach ($global_categories as $c): ?>
              <?php $cid = (int)$c['category_id']; ?>
              <option value="<?= $cid ?>" <?= ($global_cat_val === $cid) ? 'selected' : '' ?>>
                <?= e($c['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-2" style="display:flex; align-items:flex-end;">
          <button class="btn primary" type="submit" style="width:100%;">Filter</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php
$flashes = flash_get_all();
if (!empty($flashes)):
?>
<div class="container" style="margin-top:20px; margin-bottom:-10px;">
  <?php foreach ($flashes as $type => $messages): ?>
    <?php foreach ($messages as $msg): ?>
      <div class="flash <?= e($type) ?>" style="margin-bottom:10px;">
        <?= e($msg) ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="container main">
