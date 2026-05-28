<?php
/**
 * orders/edit.php — Edit Order Details (Presentation Layer)
 * Allows editing of customer name, notes and payment status.
 * Also allows status update with stock deduction on completion.
 * Uses Order::getById(), Order::update(), Order::updateStatus() static methods.
 * Access: All authenticated users (editable orders only).
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
require_once "../classes/StockMovement.php";
requireLogin();

$id = (int)($_GET["id"] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

// ── POST: update order details (customer, notes, is_paid) ─
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_details") {
    $customer = trim($_POST["customer"] ?? "");
    $notes    = trim($_POST["notes"]    ?? "");
    $is_paid  = isset($_POST["is_paid"]) ? 1 : 0; // BOOLEAN toggle

    if (empty($customer)) {
        flash("error", "Customer name is required.");
    } else {
        Order::update($conn, $id, $customer, $notes, $is_paid);
        flash("success", "Order details updated.");
    }
    header("Location: edit.php?id=$id"); exit;
}

// ── POST: update order status ─────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["new_status"])) {
    Order::updateStatus($conn, $id, $_POST["new_status"], $_SESSION["uid"]);
    flash("success", "Order status updated.");
    header("Location: edit.php?id=$id"); exit;
}

// ── Fetch order and items via static class methods ────────
$order = Order::getById($conn, $id);
if (!$order) {
    flash("error", "Order not found.");
    header("Location: index.php"); exit;
}

// Only editable orders can be edited
if (!$order->isEditable()) {
    flash("error", "This order cannot be edited.");
    header("Location: index.php"); exit;
}

$items = Order::getItems($conn, $id);

// ── HTML starts here ──────────────────────────────────────
$t = "Edit Order"; $a = "orders";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<div class="page-hdr">
  <a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit Order — <?= h($order->getOrderNumber()) ?></h1>
  <span class="badge <?= $order->getStatusBadge() ?>" style="font-size:12px">
    <?= ucfirst($order->getStatus()) ?>
  </span>
  <span class="badge <?= $order->getPaidBadge() ?>" style="font-size:12px">
    <?= $order->getPaidLabel() ?>
  </span>
</div>

<div class="g2">

  <!-- LEFT: Edit order details form -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Edit details</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_details">

        <!-- Customer name -->
        <div class="fg" style="margin-bottom:12px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Customer name *
          </label>
          <input type="text" name="customer" class="fc"
                 value="<?= h($order->getCustomer()) ?>" required>
        </div>

        <!-- Notes -->
        <div class="fg" style="margin-bottom:12px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Notes
          </label>
          <input type="text" name="notes" class="fc"
                 value="<?= h($order->getNotes()) ?>"
                 placeholder="Optional notes">
        </div>

        <!-- Payment status — is_paid TINYINT(1) boolean -->
        <div class="fg" style="margin-bottom:16px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" name="is_paid" value="1"
                   <?= $order->isPaid() ? "checked" : "" ?>>
            <span>Mark as paid</span>
            <span class="badge <?= $order->getPaidBadge() ?>" style="font-size:10px">
              <?= $order->getPaidLabel() ?>
            </span>
          </label>
          <small style="color:var(--t2);margin-top:4px;display:block">
            Stored as TINYINT(1) — 1 (paid) or 0 (unpaid)
          </small>
        </div>

        <!-- Order info (read-only) -->
        <div style="background:var(--bg2);border-radius:var(--r);padding:10px 12px;margin-bottom:14px;font-size:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><span style="color:var(--t2)">Order #</span><br>
              <strong><?= h($order->getOrderNumber()) ?></strong></div>
            <div><span style="color:var(--t2)">Total</span><br>
              <strong><?= $order->getFormattedTotal() ?></strong></div>
            <div><span style="color:var(--t2)">Created by</span><br>
              <strong><?= h($order->getCreatedBy()) ?></strong></div>
            <div><span style="color:var(--t2)">Date</span><br>
              <strong><?= $order->getFormattedDate() ?></strong></div>
          </div>
        </div>

        <button class="btn btn-primary w100">Save changes</button>
      </form>
    </div>
  </div>

  <!-- RIGHT: Update status buttons -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Update status</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ([
        "pending"    => "Mark as Pending",
        "processing" => "Mark as Processing",
        "completed"  => "Mark as Completed — deducts stock",
        "cancelled"  => "Cancel Order"
      ] as $s => $lbl): ?>
      <?php if ($s !== $order->getStatus()): ?>
      <form method="POST">
        <input type="hidden" name="new_status" value="<?= $s ?>">
        <button class="btn w100 <?= $s==="completed" ? "btn-success" : ($s==="cancelled" ? "btn-danger" : "btn-outline") ?>"
          <?= $s === "completed" ? "onclick=\"return confirm('Complete order? Stock will be deducted for all items.')\"" : "" ?>>
          <?= $lbl ?>
        </button>
      </form>
      <?php endif; ?>
      <?php endforeach; ?>

      <div style="margin-top:8px;font-size:11px;color:var(--t2);padding:8px;background:var(--bg2);border-radius:var(--r)">
        Current status:
        <span class="badge <?= $order->getStatusBadge() ?>"><?= ucfirst($order->getStatus()) ?></span>
      </div>
    </div>
  </div>

</div>

<!-- Line items table — read only -->
<div class="card">
  <div class="card-hdr">
    <span class="card-title">Order items</span>
    <span style="font-size:12px;color:var(--t2)"><?= $order->getItemCount() ?> item<?= $order->getItemCount() != 1 ? "s" : "" ?></span>
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th>Product</th>
        <th>SKU</th>
        <th>Qty</th>
        <th>Unit price</th>
        <th>Line total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): // Each $item is an OrderItem object ?>
    <tr>
      <td class="fw"><?= h($item->getProductName()) ?></td>
      <td class="mono muted"><?= h($item->getSku()) ?></td>
      <td class="mono"><?= $item->getQuantity() ?></td>
      <td class="mono"><?= $item->getFormattedUnitPrice() ?></td>
      <td class="mono fw"><?= $item->getFormattedLineTotal() ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="border-top:2px solid var(--b2)">
      <td colspan="4" style="text-align:right;font-weight:600;padding:10px 14px">Total</td>
      <td class="mono fw" style="padding:10px 14px"><?= $order->getFormattedTotal() ?></td>
    </tr>
    </tbody>
  </table>
</div>

<?php require_once "../includes/footer.php"; ?>