<?php
/**
 * orders/edit.php — Order Detail / Status Update (Presentation Layer)
 * Shows full order details including line items.
 * Uses Order class: getById() and updateStatus().
 * Completing triggers stock deduction via Stock class internally.
 * Access: All authenticated users.
 */
$t = "Order Details"; $a = "orders";
require_once "../includes/header.php";
require_once "../classes/Order.php";
include "../includes/flash.php";

$orderObj = new Order($conn); // Middle layer
$id = (int)($_GET["id"] ?? 0);

// Fetch order and items via Order class
$data = $orderObj->getById($id);
if(!$data){ flash("error","Order not found."); header("Location: index.php"); exit; }
$o = $data["order"]; $items = $data["items"];
$sc = ["pending"=>"b-amber","processing"=>"b-blue","completed"=>"b-green","cancelled"=>"b-gray"];

// Handle status update
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["new_status"])){
    $orderObj->updateStatus($id, $_POST["new_status"], $_SESSION["uid"]);
    flash("success","Order status updated."); header("Location: edit.php?id=$id"); exit;
}
?>
<div class="page-hdr"><a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a><h1>Order <?= h($o["order_number"]) ?></h1>
  <span class="badge <?= $sc[$o["status"]] ?>" style="font-size:12px"><?= ucfirst($o["status"]) ?></span>
</div>
<div class="g2">
  <!-- Order info summary -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Order info</span></div>
    <div class="card-body">
    <table style="width:100%;font-size:12px;border-collapse:collapse">
      <?php foreach(["Order #"=>$o["order_number"],"Customer"=>$o["customer"],"Total"=>"$".number_format($o["total"],2),"Created by"=>$o["created_by_name"],"Date"=>date("d M Y H:i",strtotime($o["created_at"])),"Notes"=>($o["notes"]??"—")] as $k=>$v): ?>
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
      <?php if($s!==$o["status"]): ?>
      <form method="POST"><input type="hidden" name="new_status" value="<?= $s ?>">
        <button class="btn <?= $s==="completed"?"btn-success":($s==="cancelled"?"btn-danger":"btn-outline") ?> w100"
                <?= $s==="completed"?"onclick="return confirm('Complete order? Stock will be deducted.')""  :""  ?>><?= $lbl ?></button></form>
      <?php endif; endforeach; ?>
    </div>
  </div>
</div>
<!-- Line items table -->
<div class="card">
  <div class="card-hdr"><span class="card-title">Order items</span></div>
  <table ="tbl">
    <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody>
    <?php $grand=0; foreach($items as $it): $lt=$it["quantity"]*$it["unit_price"]; $grand+=$lt; ?>
    <tr><td class="fw"><?= h($it["product_name"]) ?></td><td class="mono muted"><?= h($it["sku"]) ?></td>
    <td class="mono"><?= $it["quantity"] ?></td><td class="mono">$<?= number_format($it["unit_price"],2) ?></td>
    <td class="mono fw">$<?= number_format($lt,2) ?></td></tr>
    <?php endforeach; ?>
    <tr style="border-top:2px solid var(--b2)">
      <td colspan="4" style="text-align:right;font-weight:600;padding:10px 14px">Total</td>
      <td class="mono fw" style="padding:10px 14px">$<?= number_format($grand,2) ?></td>
    </tr>
    </tbody>
  </table>
</div>
<?php require_once "../includes/footer.php"; ?>
