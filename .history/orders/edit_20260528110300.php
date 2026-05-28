<?php
/**
 * orders/edit.php — Order Detail / Status Update (Presentation Layer)
 * Shows full order details including line items.
 * Uses Order class: getById(), getItems(), and updateStatus().
 * Completing triggers stock deduction via StockMovement internally.
 * Access: All authenticated users.
 */
$t = "Order Details"; $a = "orders";
require_once "../includes/header.php";
require_once "../classes/Order.php";
include "../includes/flash.php";

// FIX: Order uses static methods only — do NOT instantiate with $conn.
$id = (int)($_GET["id"] ?? 0);

// FIX: getById() is static, takes $conn as first arg, returns an Order object (not an array).
$o = Order::getById($conn, $id);
if(!$o){ flash("error","Order not found."); header("Location: index.php"); exit; }

// FIX: fetch line items separately via the static getItems() method.
$items = Order::getItems($conn, $id);

// Handle status update
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["new_status"])){
    // FIX: updateStatus() is static — call as Order::updateStatus().
    Order::updateStatus($conn, $id, $_POST["new_status"], $_SESSION["uid"]);
    flash("success","Order status updated."); header("Location: edit.php?id=$id"); exit;
}
?>
<div class="page-hdr"><a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a><h1>Order <?= h($o->getOrderNumber()) ?></h1>
  <span class="badge <?= $o->getStatusBadge() ?>" style="font-size:12px"><?= ucfirst($o->getStatus()) ?></span>
</div>
<div class="g2">
  <!-- Order info summary -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Order info</span></div>
    <div class="card-body">
    <table style="width:100%;font-size:12px;border-collapse:collapse">
      <?php foreach([
        "Order #"    => $o->getOrderNumber(),
        "Customer"   => $o->getCustomer(),
        "Total"      => $o->getFormattedTotal(),
        "Created by" => $o->getCreatedBy(),
        "Date"       => $o->getFormattedDate(),
        "Notes"      => ($o->getNotes() ?: "—"),
      ] as $k=>$v): ?>
      <tr><td style="padding:7px 0;color:var(--t2);width:40%"><?= $k ?></td><td style="padding:7px 0;font-weight:500"><?= h((string)$v) ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
  <!-- Status update buttons -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Update status</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
      <?php foreach(["pending"=>"Mark as Pending","processing"=>"Mark as Processing","completed"=>"Mark as Completed (deducts stock)","cancelled"=>"Cancel Order"] as $s=>$lbl): ?>
      <?php if($s!==$o->getStatus()): ?>
      <form method="POST"><input type="hidden" name="new_status" value="<?= $s ?>">
        <button class="btn <?= $s==="completed"?"btn-success":($s==="cancelled"?"btn-danger":"btn-outline") ?> w100"
                <?= $s==="completed"?"onclick=\"return confirm('Complete order? Stock will be deducted.')\"" :"" ?>><?= $lbl ?></button></form>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div>
<!-- Line items table -->
<div class="card">
  <div class="card-hdr"><span class="card-title">Order items</span></div>
  <table class="tbl">
    <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody>
    <?php foreach($items as $item): ?>
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
      <td class="mono fw" style="padding:10px 14px"><?= $o->getFormattedTotal() ?></td>
    </tr>
    </tbody>
  </table>
</div>
<?php require_once "../includes/footer.php"; ?>
