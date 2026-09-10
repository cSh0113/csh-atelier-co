<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$me = current_user();
$myId = (int)$me['id'];
$pdo = db();
$pageTitle = 'My Account';

$tab = $_GET['tab'] ?? 'overview';
$error = '';
$success = '';

if (isset($_GET['saved'])) {
    $success = 'Your profile details and settings have been saved.';
}

// Older copies of the database do not have this column yet, so add it
// quietly rather than letting the page fall over.
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'showcase_sold'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN showcase_sold TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {}

// What a member can do to their own listings from the wardrobe tab:
// mark it sold, put it back up, or remove it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listing_action'])) {
    $action = $_POST['listing_action'];
    $listingId = (int)($_POST['listing_id'] ?? 0);

    $chk = $pdo->prepare("SELECT id, status FROM listings WHERE id = ? AND seller_id = ?");
    $chk->execute([$listingId, $myId]);
    $listing = $chk->fetch();

    if ($listing) {
        if ($action === 'mark_sold') {
            $up = $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ? AND seller_id = ?");
            $up->execute([$listingId, $myId]);
            $success = 'Item marked as sold.';
        } elseif ($action === 'relist') {
            $up = $pdo->prepare("UPDATE listings SET status = 'active' WHERE id = ? AND seller_id = ?");
            $up->execute([$listingId, $myId]);
            $success = 'Item is active in the marketplace again.';
        } elseif ($action === 'delete') {
            $up = $pdo->prepare("UPDATE listings SET status = 'removed' WHERE id = ? AND seller_id = ?");
            $up->execute([$listingId, $myId]);
            $success = 'Listing removed from marketplace.';
        }
    }
    $tab = 'listings';
}

// Saving the profile: details, photo, and whether their sold items show
// on their public page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name         = trim($_POST['name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $province     = trim($_POST['province'] ?? '');
    $bio          = trim($_POST['bio'] ?? '');
    $avatarUrl    = $me['avatar_url'] ?? null;
    $showcaseSold = !empty($_POST['showcase_sold']) ? 1 : 0;

    if ($name === '') {
        $error = 'Your name cannot be empty.';
    }

    // Profile photo. Only touched if they actually picked a new file.
    if (empty($error) && !empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $error = 'Please upload a valid image (JPG, PNG, or WEBP).';
        } elseif ($_FILES['avatar_file']['size'] > 4 * 1024 * 1024) {
            $error = 'Photo size cannot exceed 4MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'avatar_' . $myId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $uploadDir . $fileName)) {
                $avatarUrl = 'uploads/avatars/' . $fileName;
            }
        }
    } elseif (!empty($_POST['remove_avatar'])) {
        $avatarUrl = null;
    }

    // Password only changes if they filled the field in and it matches.
    $newPass = trim($_POST['new_password'] ?? '');
    if (empty($error) && $newPass !== '') {
        $curPass = $_POST['current_password'] ?? '';
        if (!password_verify($curPass, $me['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $myId]);
        }
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, province = ?, bio = ?, avatar_url = ?, showcase_sold = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $city, $province, $bio, $avatarUrl, $showcaseSold, $myId]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, province = ?, bio = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $city, $province, $bio, $avatarUrl, $myId]);
        }

        header('Location: account.php?tab=settings&saved=1');
        exit;
    }
}

// Everything the dashboard tabs need. I pull it all up front so switching
// tabs is instant instead of hitting the database again each time.
$orders = [];
try {
    $oStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $oStmt->execute([$myId]);
    $orders = $oStmt->fetchAll();
} catch (Exception $e) {}

$myListings = [];
try {
    $lStmt = $pdo->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY id DESC");
    $lStmt->execute([$myId]);
    $myListings = $lStmt->fetchAll();
} catch (Exception $e) {}

$donations = [];
try {
    $dStmt = $pdo->prepare("SELECT * FROM donations WHERE user_id = ? ORDER BY id DESC");
    $dStmt->execute([$myId]);
    $donations = $dStmt->fetchAll();
} catch (Exception $e) {}

$creditLogs = [];
try {
    $cStmt = $pdo->prepare("SELECT * FROM credit_transactions WHERE user_id = ? ORDER BY id DESC");
    $cStmt->execute([$myId]);
    $creditLogs = $cStmt->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/includes/header.php';
?>

<style>
.account-hero {
  background: var(--bg-raised, #fff);
  border-radius: var(--r-lg, 16px);
  padding: 28px;
  box-shadow: var(--nm-out, 0 4px 20px rgba(0,0,0,0.06));
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 24px;
  border: 1px solid var(--line, #e2ded6);
}
.account-tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  border-bottom: 2px solid var(--line, #e2ded6);
  margin-bottom: 28px;
  padding-bottom: 2px;
}
.account-tab-link {
  padding: 11px 20px;
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--ink-soft, #5b5b5b);
  text-decoration: none;
  border-radius: 8px 8px 0 0;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
  transition: all 0.15s;
}
.account-tab-link:hover { color: var(--ink, #1a1a1a); }
.account-tab-link.active {
  color: var(--leaf, #3d6b4f);
  border-bottom-color: var(--leaf, #3d6b4f);
  background: var(--bg-raised, #fff);
}
.stat-card {
  background: var(--bg-raised, #fff);
  border-radius: 14px;
  padding: 20px;
  border: 1px solid var(--line, #e2ded6);
  flex: 1;
  min-width: 160px;
}
.account-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.account-table th {
  text-align: left;
  padding: 12px 14px;
  background: var(--bg-sunken, #eceae5);
  border-bottom: 1px solid var(--line, #e2ded6);
  font-weight: 700;
}
.account-table td {
  padding: 14px;
  border-bottom: 1px solid var(--line, #e2ded6);
}

.wardrobe-card {
  background: var(--bg-raised, #fff);
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid var(--line, #e2ded6);
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.wardrobe-thumb {
  display: block;
  height: 200px;
  background: var(--bg-sunken, #eceae5);
  position: relative;
  overflow: hidden;
}
.wardrobe-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.status-pill {
  position: absolute;
  top: 10px;
  right: 10px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 4px 9px;
  border-radius: 999px;
  backdrop-filter: blur(4px);
}
.status-pill.active { background: #dcf3e4; color: #1e4620; }
.status-pill.sold { background: #e2ded6; color: #5b5b5b; }
.status-pill.removed { background: #f8d7da; color: #721c24; }

.action-btn {
  padding: 7px 12px;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 6px;
  border: 1px solid var(--line, #e2ded6);
  background: var(--bg, #f6f4f0);
  color: var(--ink, #1a1a1a);
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.action-btn:hover { background: var(--bg-sunken, #eceae5); }
.action-btn.edit { background: var(--leaf, #3d6b4f); color: #fff; border-color: var(--leaf, #3d6b4f); }
.action-btn.danger { color: #b4483c; }

/* The sliding on and off switch, built off a normal checkbox so it still
   works with the keyboard. */
.switch-box {
  background: var(--bg, #f6f4f0);
  border-radius: 12px;
  padding: 16px 20px;
  border: 1px solid var(--line, #e2ded6);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  flex-shrink: 0;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute; cursor: pointer; inset: 0;
  background-color: #d1c7b7; transition: .25s;
  border-radius: 34px;
}
.slider:before {
  position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px;
  background-color: white; transition: .25s; border-radius: 50%;
}
.switch input:checked + .slider { background-color: var(--leaf, #3d6b4f); }
.switch input:checked + .slider:before { transform: translateX(22px); }
</style>

<div class="container" style="max-width: 1140px; margin: 30px auto; padding: 0 16px;">

  <!-- Hero Profile Banner -->
  <div class="account-hero">
    <div style="display: flex; align-items: center; gap: 20px;">
      <?php if (!empty($me['avatar_url'])): ?>
        <img src="<?= e($me['avatar_url']) ?>" alt="<?= e($me['name']) ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--leaf, #3d6b4f);">
      <?php else: ?>
        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 2rem; font-weight: 800;">
          <?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?>
        </div>
      <?php endif; ?>

      <div>
        <h1 style="margin: 0 0 4px; font-size: 1.6rem;"><?= e($me['name']) ?></h1>
        <p style="margin: 0 0 6px; color: var(--ink-faint, #8a8a8a); font-size: 0.88rem;"><?= e($me['email']) ?></p>
        <span style="font-size: 0.78rem; background: var(--bg-sunken, #eceae5); padding: 3px 8px; border-radius: 999px; text-transform: uppercase; font-weight: 700;">
          <?= e($me['role']) ?> &middot; Member
        </span>
      </div>
    </div>

    <div style="display: flex; gap: 24px;">
      <div>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--leaf, #3d6b4f);">R<?= number_format((float)($me['credits'] / 10), 2) ?></div>
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--ink-faint, #8a8a8a); font-weight: 700;">Store Credits</div>
      </div>
      <div>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--leaf, #3d6b4f);"><?= (int)$me['items_diverted'] ?></div>
        <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--ink-faint, #8a8a8a); font-weight: 700;">Items Diverted</div>
      </div>
    </div>
  </div>

  <!-- Dashboard Navigation Menu (Tabs) -->
  <nav class="account-tabs">
    <a href="account.php?tab=overview" class="account-tab-link <?= $tab === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="account.php?tab=orders" class="account-tab-link <?= $tab === 'orders' ? 'active' : '' ?>">My Orders (<?= count($orders) ?>)</a>
    <a href="account.php?tab=listings" class="account-tab-link <?= $tab === 'listings' ? 'active' : '' ?>">My Wardrobe (<?= count($myListings) ?>)</a>
    <a href="account.php?tab=donations" class="account-tab-link <?= $tab === 'donations' ? 'active' : '' ?>">Donations (<?= count($donations) ?>)</a>
    <a href="account.php?tab=credits" class="account-tab-link <?= $tab === 'credits' ? 'active' : '' ?>">Credit Ledger</a>
    <a href="account.php?tab=settings" class="account-tab-link <?= $tab === 'settings' ? 'active' : '' ?>">Settings</a>
  </nav>

  <?php if (!empty($success)): ?>
    <div style="padding: 12px 18px; border-radius: 10px; background: #dcf3e4; color: #1e4620; margin-bottom: 24px; font-weight: 600;">
      <?= e($success) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div style="padding: 12px 18px; border-radius: 10px; background: #fdeeec; color: #8f3a30; margin-bottom: 24px; font-weight: 600;">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: OVERVIEW                                            -->
  <!-- -------------------------------------------------------- -->
  <?php if ($tab === 'overview'): ?>
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 30px;">
      <div class="stat-card">
        <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a); font-weight: 700;">CREDITS BALANCE</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin: 6px 0;"><?= (int)$me['credits'] ?></div>
        <small style="color: var(--ink-soft, #5b5b5b);">Worth R<?= number_format((float)($me['credits'] / 10), 2) ?></small>
      </div>
      <div class="stat-card">
        <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a); font-weight: 700;">GARMENTS DIVERTED</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin: 6px 0;"><?= (int)$me['items_diverted'] ?></div>
        <small style="color: var(--ink-soft, #5b5b5b);">Kept out of landfills</small>
      </div>
      <div class="stat-card">
        <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a); font-weight: 700;">MY WARDROBE</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin: 6px 0;"><?= count($myListings) ?></div>
        <a href="sell.php" style="color: var(--leaf, #3d6b4f); font-weight: 700; font-size: 0.85rem; text-decoration: none;">+ List new piece</a>
      </div>
      <div class="stat-card">
        <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a); font-weight: 700;">TOTAL ORDERS</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin: 6px 0;"><?= count($orders) ?></div>
        <a href="marketplace.php" style="color: var(--leaf, #3d6b4f); font-weight: 700; font-size: 0.85rem; text-decoration: none;">Browse marketplace</a>
      </div>
    </div>

    <div style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 24px; border: 1px solid var(--line, #e2ded6);">
      <h3 style="margin: 0 0 16px; font-size: 1.15rem;">Recent Orders</h3>
      <?php if (empty($orders)): ?>
        <p style="color: var(--ink-faint, #8a8a8a); margin: 0;">You have not placed any orders yet.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach (array_slice($orders, 0, 3) as $o): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: var(--bg, #f6f4f0); border-radius: 10px;">
              <div>
                <b><?= e($o['order_no']) ?></b>
                <div style="font-size: 0.8rem; color: var(--ink-faint, #8a8a8a);"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
              </div>
              <div style="text-align: right;">
                <b>R<?= number_format((float)$o['total'], 2) ?></b>
                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--leaf, #3d6b4f); font-weight: 700;"><?= e($o['status']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: MY WARDROBE (LISTINGS)                              -->
  <!-- -------------------------------------------------------- -->
  <?php elseif ($tab === 'listings'): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 1.35rem;">My Wardrobe (<?= count($myListings) ?>)</h2>
      <a href="sell.php" style="padding: 10px 20px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 700; font-size: 0.88rem;">+ List New Item</a>
    </div>

    <?php if (empty($myListings)): ?>
      <div style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 50px 20px; text-align: center; border: 1px solid var(--line, #e2ded6);">
        <p style="color: var(--ink-soft, #5b5b5b); margin: 0 0 14px; font-size: 1rem;">You don't have any items listed for sale yet.</p>
        <a href="sell.php" style="display: inline-block; padding: 10px 22px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 700; font-size: 0.88rem;">List an Item Now</a>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
        <?php foreach ($myListings as $l): ?>
          <div class="wardrobe-card">
            <a href="listing.php?slug=<?= e($l['slug']) ?>" class="wardrobe-thumb" title="Click to view listing">
              <img src="<?= e($l['image_url'] ?: 'assets/img/placeholder.svg') ?>" alt="<?= e($l['title']) ?>">
              <span class="status-pill <?= e($l['status']) ?>"><?= e($l['status']) ?></span>
            </a>

            <div style="padding: 14px 16px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <a href="listing.php?slug=<?= e($l['slug']) ?>" style="font-weight: 700; font-size: 1rem; color: inherit; text-decoration: none; display: block; margin-bottom: 4px; line-height: 1.35;">
                  <?= e($l['title']) ?>
                </a>
                
                <div style="font-size: 0.82rem; color: var(--ink-faint, #8a8a8a); margin-bottom: 8px;">
                  <?= !empty($l['item_size']) ? 'Size ' . e($l['item_size']) . ' &middot; ' : '' ?>
                  <?= e(ucwords(str_replace('_', ' ', $l['item_condition']))) ?>
                  <?php if (!empty($l['brand'])): ?> &middot; <?= e($l['brand']) ?><?php endif; ?>
                </div>

                <div style="font-size: 1.15rem; font-weight: 800; color: var(--leaf, #3d6b4f); margin-bottom: 12px;">
                  R<?= number_format((float)$l['price'], 2) ?>
                  <span style="font-size: 0.76rem; font-weight: 500; color: var(--ink-faint, #8a8a8a); margin-left: 6px;">
                    &middot; <?= (int)$l['views'] ?> views
                  </span>
                </div>
              </div>

              <div style="border-top: 1px solid var(--line, #e2ded6); padding-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="sell.php?id=<?= (int)$l['id'] ?>" class="action-btn edit" title="Edit listing">
                  Edit
                </a>

                <a href="listing.php?slug=<?= e($l['slug']) ?>" class="action-btn" target="_blank">
                  View
                </a>

                <?php if ($l['status'] === 'active'): ?>
                  <form method="POST" action="account.php?tab=listings" style="margin: 0; display: inline;">
                    <input type="hidden" name="listing_action" value="mark_sold">
                    <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                    <button type="submit" class="action-btn">Sold</button>
                  </form>
                <?php elseif ($l['status'] === 'sold'): ?>
                  <form method="POST" action="account.php?tab=listings" style="margin: 0; display: inline;">
                    <input type="hidden" name="listing_action" value="relist">
                    <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                    <button type="submit" class="action-btn">Relist</button>
                  </form>
                <?php endif; ?>

                <?php if ($l['status'] !== 'removed'): ?>
                  <form method="POST" action="account.php?tab=listings" style="margin: 0; display: inline;" onsubmit="return confirm('Remove this listing from marketplace?');">
                    <input type="hidden" name="listing_action" value="delete">
                    <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                    <button type="submit" class="action-btn danger">Remove</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: ORDERS                                              -->
  <!-- -------------------------------------------------------- -->
  <?php elseif ($tab === 'orders'): ?>
    <div style="background: var(--bg-raised, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--line, #e2ded6);">
      <?php if (empty($orders)): ?>
        <div style="padding: 50px 20px; text-align: center; color: var(--ink-soft, #5b5b5b);">No orders placed yet.</div>
      <?php else: ?>
        <table class="account-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Date</th>
              <th>Total</th>
              <th>Status</th>
              <th>Delivery</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><b><?= e($o['order_no']) ?></b></td>
                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                <td><b>R<?= number_format((float)$o['total'], 2) ?></b></td>
                <td><span style="font-weight: 700; text-transform: uppercase; font-size: 0.78rem; color: var(--leaf, #3d6b4f);"><?= e($o['status']) ?></span></td>
                <td><?= e(ucfirst($o['delivery_method'] ?? 'shipping')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: DONATIONS                                           -->
  <!-- -------------------------------------------------------- -->
  <?php elseif ($tab === 'donations'): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 1.35rem;">Garment Donations (Take Backs)</h2>
      <a href="donate.php" style="padding: 10px 20px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; text-decoration: none; font-weight: 700; font-size: 0.88rem;">Donate Clothing</a>
    </div>

    <div style="background: var(--bg-raised, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--line, #e2ded6);">
      <?php if (empty($donations)): ?>
        <div style="padding: 50px 20px; text-align: center; color: var(--ink-soft, #5b5b5b);">No donations logged yet.</div>
      <?php else: ?>
        <table class="account-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Items</th>
              <th>Method</th>
              <th>Outcome</th>
              <th>Credits Earned</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($donations as $d): ?>
              <tr>
                <td><b><?= e($d['reference']) ?></b></td>
                <td><?= (int)$d['item_count'] ?> pieces</td>
                <td><?= e(ucfirst($d['dropoff_method'])) ?></td>
                <td><span style="font-weight: 700; text-transform: uppercase; font-size: 0.78rem;"><?= e($d['outcome']) ?></span></td>
                <td><b style="color: var(--leaf, #3d6b4f);">+<?= (int)$d['credits_awarded'] ?></b></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: CREDITS                                             -->
  <!-- -------------------------------------------------------- -->
  <?php elseif ($tab === 'credits'): ?>
    <div style="background: var(--bg-raised, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--line, #e2ded6);">
      <?php if (empty($creditLogs)): ?>
        <div style="padding: 50px 20px; text-align: center; color: var(--ink-soft, #5b5b5b);">No credit transactions on record.</div>
      <?php else: ?>
        <table class="account-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Reason</th>
              <th>Amount</th>
              <th>Balance After</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($creditLogs as $cl): ?>
              <tr>
                <td><?= date('d M Y', strtotime($cl['created_at'])) ?></td>
                <td><span style="font-weight: 700; text-transform: uppercase; font-size: 0.78rem;"><?= e(str_replace('_', ' ', $cl['reason'])) ?></span></td>
                <td><b style="color: <?= $cl['amount'] >= 0 ? '#1e4620' : '#8f3a30' ?>;"><?= $cl['amount'] >= 0 ? '+' : '' ?><?= (int)$cl['amount'] ?></b></td>
                <td><?= (int)$cl['balance_after'] ?></td>
                <td style="color: var(--ink-soft, #5b5b5b);"><?= e($cl['note'] ?? $cl['reference']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <!-- -------------------------------------------------------- -->
  <!-- TAB: SETTINGS (WITH SHOWCASE SOLD ITEMS SWITCH)          -->
  <!-- -------------------------------------------------------- -->
  <?php elseif ($tab === 'settings'): ?>
    <div style="background: var(--bg-raised, #fff); border-radius: 16px; padding: 32px; border: 1px solid var(--line, #e2ded6);">
      <h2 style="margin: 0 0 20px; font-size: 1.35rem;">Profile &amp; Account Settings</h2>

      <form method="POST" action="account.php?tab=settings" enctype="multipart/form-data">
        <input type="hidden" name="update_profile" value="1">

        <!-- Profile Photo Row -->
        <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--line, #e2ded6);">
          <?php if (!empty($me['avatar_url'])): ?>
            <img src="<?= e($me['avatar_url']) ?>" alt="Avatar" style="width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 2px solid var(--leaf, #3d6b4f);">
          <?php else: ?>
            <div style="width: 84px; height: 84px; border-radius: 50%; background: var(--leaf, #3d6b4f); color: #fff; display: grid; place-items: center; font-size: 2rem; font-weight: 800;">
              <?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?>
            </div>
          <?php endif; ?>

          <div>
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 0.92rem;">Profile Picture</label>
            <input type="file" name="avatar_file" accept="image/png, image/jpeg, image/webp" style="font-size: 0.85rem;">
            <?php if (!empty($me['avatar_url'])): ?>
              <label style="display: block; margin-top: 6px; font-size: 0.82rem; color: #b4483c; cursor: pointer;">
                <input type="checkbox" name="remove_avatar" value="1"> Remove current picture
              </label>
            <?php endif; ?>
          </div>
        </div>

        <!-- NEW: Switch to showcase sold items on public profile -->
        <div class="switch-box" style="margin-bottom: 22px;">
          <div>
            <b style="display: block; font-size: 0.95rem; margin-bottom: 4px;">Showcase Sold Items on Profile</b>
            <p style="margin: 0; font-size: 0.83rem; color: var(--ink-soft, #5b5b5b); line-height: 1.45;">
              Display your recently sold wardrobe pieces on your public member profile with "SOLD" badges to showcase your circular selling history.
            </p>
          </div>
          <label class="switch">
            <input type="checkbox" name="showcase_sold" value="1" <?= !empty($me['showcase_sold']) ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Full Name</label>
            <input type="text" name="name" value="<?= e($me['name']) ?>" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Email (Read Only)</label>
            <input type="email" value="<?= e($me['email']) ?>" disabled style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg-sunken, #eceae5); opacity: 0.7; font: inherit; color: inherit; cursor: not-allowed;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-bottom: 18px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Phone</label>
            <input type="text" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="+27..." style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">City</label>
            <input type="text" name="city" value="<?= e($me['city'] ?? '') ?>" placeholder="e.g. Johannesburg" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Province</label>
            <input type="text" name="province" value="<?= e($me['province'] ?? '') ?>" placeholder="e.g. Gauteng" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
        </div>

        <div style="margin-bottom: 24px;">
          <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Bio</label>
          <textarea name="bio" rows="3" placeholder="Tell other members about your circular fashion style..." style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;"><?= e($me['bio'] ?? '') ?></textarea>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--line, #e2ded6); margin: 26px 0;">

        <h3 style="margin: 0 0 14px; font-size: 1.1rem;">Security &amp; Password</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">Current Password</label>
            <input type="password" name="current_password" placeholder="Only if changing password" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px;">New Password</label>
            <input type="password" name="new_password" placeholder="At least 6 characters" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--line, #e2ded6); background: var(--bg, #f6f4f0); font: inherit; color: inherit;">
          </div>
        </div>

        <button type="submit" style="padding: 12px 28px; border-radius: 999px; background: var(--leaf, #3d6b4f); color: #fff; border: 0; font-weight: 700; cursor: pointer; font: inherit; font-size: 0.92rem;">
          Save Settings
        </button>
      </form>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>