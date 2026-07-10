<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role($conn, "admin");
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/header.php';

/* USERS + ROLES */
$res = mysqli_query($conn, "
  SELECT u.user_id, u.full_name, u.email, u.status, u.created_at,
         GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles
  FROM users u
  LEFT JOIN user_roles ur ON ur.user_id = u.user_id
  LEFT JOIN roles r ON r.role_id = ur.role_id
  GROUP BY u.user_id
  ORDER BY u.created_at DESC
");

function badge_user_status(string $s): string {
  $s = strtolower($s);
  if ($s === 'active') return 'ok';
  if ($s === 'disabled') return 'danger';
  if ($s === 'pending') return 'warn';
  return '';
}

function role_badge_class(string $role): string {
  $role = strtolower(trim($role));
  if ($role === 'admin') return 'danger';
  if ($role === 'seller') return 'warn';
  if ($role === 'buyer') return 'ok';
  return '';
}
?>

<div class="grid">

  <div class="col-12">
    <div class="card card-pad">
      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:5px;">Users</h1>
          <div class="small">View registered users and their roles.</div>
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
        <h2 style="margin:0;">All Users</h2>
        <span class="badge"><?= (int)mysqli_num_rows($res) ?> total</span>
      </div>

      <div class="hr"></div>

      <?php if (mysqli_num_rows($res) === 0): ?>
        <div class="flash">No users found.</div>
      <?php else: ?>
        <div class="table-responsive">
<table class="table">
          <tr>
            <th style="width:70px;">ID</th>
            <th>Name</th>
            <th>Email</th>
            <th style="width:220px;">Roles</th>
            <th style="width:130px;">Status</th>
            <th style="width:190px;">Created</th>
          </tr>

          <?php while ($u = mysqli_fetch_assoc($res)): ?>
            <?php
              $status = (string)($u['status'] ?? '');
              $statusCls = badge_user_status($status);

              $rolesRaw = (string)($u['roles'] ?? '');
              $rolesArr = array_filter(array_map('trim', explode(',', $rolesRaw)));
            ?>
            <tr>
              <td><?= (int)$u['user_id'] ?></td>
              <td><strong><?= e($u['full_name']) ?></strong></td>
              <td><?= e($u['email']) ?></td>

              <td>
                <?php if (!$rolesArr): ?>
                  <span class="badge">none</span>
                <?php else: ?>
                  <?php foreach ($rolesArr as $rname): ?>
                    <span class="badge <?= e(role_badge_class($rname)) ?>">
                      <?= e($rname) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>

              <td>
                <span class="badge <?= e($statusCls) ?>">
                  <?= e($status) ?>
                </span>
              </td>

              <td class="small"><?= e($u['created_at']) ?></td>
            </tr>
          <?php endwhile; ?>
        </table>
</div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
