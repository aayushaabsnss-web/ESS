<?php
/**
 * stock/view.php — Stock Detail (Presentation Layer)
 * Fetches a Product object and Stock objects.
 * HTML accesses data via getter methods only — no SQL here.
 * Shows is_available boolean badge on each transaction row.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Product.php";
require_once "../classes/Stock.php";
requireLogin();

$id = (int)($_GET["id"] ?? 0);

// Fetch Product object by ID
$product = Product::getById($conn, $id);
if (!$product) {
    flash("error", "Product not found.");
    header("Location: index.php"); exit;
}

// Fetch Stock objects for this product — each is a Stock object
$transactions = Stock::getByProduct($conn, $id, 20);

// HTML starts here
$t = "Stock Detail"; $a = "stock";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<div class="page-hdr">
  <a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1><?= h($product->getName()) ?></h1>
  <a href="add.php?pid=<?= $id ?>" class="btn btn-primary">+ Add Transaction</a>
  <?php if(isOwner()): ?>
  <a href="edit.php?id=<?= $id ?>" class="btn btn-outline">Edit stock settings</a>
  <?php endif; ?>
</div>

<div class="g2" style="margin-bottom:16px">

  <!-- Product stock information -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Stock information</span></div>
    <div class="card-body">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <?php foreach([
          "Product"         => $product->getName(),
          "SKU"             => $product->getSku(),
          "Category"        => $product->getCategory(),
          "Current qty"     => $product->getQuantity()." units",
          "Min stock level" => $product->getMinQty()." units",
          "Status"          => $product->getStockStatus(),
        ] as $k => $v): ?>
        <tr>
          <td style="padding:8px 0;color:var(--t2);width:45%;border-bottom:0.5px solid var(--b)"><?= $k ?></td>
          <td style="padding:8px 0;font-weight:500;border-bottom:0.5px solid var(--b)"><?= h($v) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- is_available BOOLEAN row — reads TINYINT(1) from latest transaction -->
        <tr>
          <td style="padding:8px 0;color:var(--t2);border-bottom:0.5px solid var(--b)">Availability</td>
          <td style="padding:8px 0;border-bottom:0.5px solid var(--b)">
            <?php if (!empty($transactions)):
              $latest = $transactions[0]; // most recent transaction ?>
              <!-- isAvailable() returns bool from is_available TINYINT(1) -->
              <span class="badge <?= $latest->getAvailabilityBadge() ?>">
                <?= $latest->getAvailabilityLabel() ?>
              </span>
            <?php else: ?>
              <span class="badge b-gray">No transactions yet</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Current stock level panel -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Current stock level</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <div style="padding:14px;background:var(--bg3);border-radius:8px;text-align:center">
        <div style="font-size:42px;font-weight:700;font-family:var(--mono)"><?= $product->getQuantity() ?></div>
        <div style="font-size:12px;color:var(--t2);margin-top:4px">units in stock</div>
      </div>
      <span class="badge <?= $product->getStockBadge() ?>"
            style="font-size:13px;padding:6px 14px;text-align:center">
        <?= $product->getStockStatus() ?>
      </span>

      <!-- Boolean availability summary using is_available TINYINT(1) -->
      <?php if (!empty($transactions)): ?>
      <div style="font-size:12px;padding:10px;background:var(--bg2);border-radius:6px;text-align:center">
        <span style="color:var(--t2)">Stock availability:</span>
        <span class="badge <?= $transactions[0]->getAvailabilityBadge() ?>"
              style="margin-left:6px">
          <?= $transactions[0]->getAvailabilityLabel() ?>
        </span>
        <div style="font-size:10px;color:var(--t3);margin-top:4px">
          Stored as TINYINT(1): <?= $transactions[0]->getIsAvailable() ?>
          (<?= $transactions[0]->isAvailable() ? 'true' : 'false' ?>)
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Transaction history — includes Availability column showing is_available boolean -->
<div class="card">
  <div class="card-hdr">
    <span class="card-title">Transaction history</span>
    <span style="font-size:12px;color:var(--t2)"><?= count($transactions) ?> records</span>
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th>Type</th>
        <th>Qty change</th>
        <th>Availability</th>
        <th>Date</th>
        <th>By</th>
        <th>Notes</th>
        <?php if(isOwner()): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($transactions)): ?>
    <tr>
      <td colspan="7" style="text-align:center;padding:20px;color:var(--t3)">
        No transactions yet.
      </td>
    </tr>
    <?php else: ?>
    <?php foreach ($transactions as $tx): // Each $tx is a Stock object ?>
    <tr>
      <td><span class="badge <?= $tx->getTypeBadge() ?>"><?= $tx->getType() ?></span></td>
      <td class="mono fw"><?= $tx->getSignedQuantity() ?></td>

      <!-- is_available BOOLEAN column — isAvailable() reads TINYINT(1) from DB -->
      <!-- Shows whether product had stock AFTER this transaction was recorded -->
      <td>
        <span class="badge <?= $tx->getAvailabilityBadge() ?>">
          <?= $tx->getAvailabilityLabel() ?>
        </span>
      </td>

      <td class="muted"><?= $tx->getFormattedDate() ?></td>
      <td><?= h($tx->getMovedBy()) ?></td>
      <td class="muted"><?= h($tx->getNotes() ?: "—") ?></td>
      <?php if(isOwner()): ?>
      <td><div style="display:flex;gap:5px">
        <a href="edit_tx.php?id=<?= $tx->getId() ?>&pid=<?= $id ?>"
           class="icon-btn" title="Edit">&#9998;</a>
        <a href="delete_tx.php?id=<?= $tx->getId() ?>&pid=<?= $id ?>"
           class="icon-btn del" title="Delete"
           onclick="return confirm('Delete this transaction?')">&#128465;</a>
      </div></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once "../includes/footer.php"; ?>