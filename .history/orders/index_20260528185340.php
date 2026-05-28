<?php
/**
 * orders/index.php — Orders List (Presentation Layer)
 * Features: search, filter by status/payment/date, pagination,
 *           order summary row, CSV export button.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
require_once "../classes/StockMovement.php";
requireLogin();

// ── POST: update status ───────────────────────────────────
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["order_id"], $_POST["new_status"])) {
    Order::updateStatus($conn,(int)$_POST["order_id"],$_POST["new_status"],$_SESSION["uid"]);
    flash("success","Order status updated."); header("Location: index.php"); exit;
}
// ── POST: toggle payment ──────────────────────────────────
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["pay_id"])) {
    $newPaid = (int)$_POST["current_paid"]===1 ? 0 : 1;
    Order::updatePaid($conn,(int)$_POST["pay_id"],$newPaid);
    flash("success","Payment status updated."); header("Location: index.php"); exit;
}
// ── POST: cancel order ────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["delete_id"])) {
    requireOwner();
    Order::updateStatus($conn,(int)$_POST["delete_id"],"cancelled",$_SESSION["uid"]);
    flash("success","Order cancelled."); header("Location: index.php"); exit;
}
// ── POST: hard delete order ───────────────────────────────
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["hard_delete_id"])) {
    requireOwner();
    Order::delete($conn,(int)$_POST["hard_delete_id"]);
    flash("success","Order permanently deleted."); header("Location: index.php"); exit;
}

// ── Filters from URL ──────────────────────────────────────
$q         = trim($_GET["q"]         ?? "");
$status    = trim($_GET["status"]    ?? "");
$paid      = $_GET["paid"]           ?? "";
$date_from = trim($_GET["date_from"] ?? "");
$date_to   = trim($_GET["date_to"]   ?? "");
$page      = max(1,(int)($_GET["page"] ?? 1));
$perPage   = 10;

// ── Fetch all matching orders then paginate in PHP ────────
$allOrders = Order::search(
    $conn,
    $q        ?: null,
    $status   ?: null,
    $date_from?: null,
    $date_to  ?: null,
    $paid !== "" ? $paid : null
);

$summary    = Order::getSummary($allOrders);     // stats across ALL results
$totalCount = count($allOrders);
$totalPages = max(1,(int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$orders     = array_slice($allOrders, ($page-1)*$perPage, $perPage); // current page slice

// Build query string helper for pagination links
$qs = http_build_query(array_filter([
    'q'=>$q,'status'=>$status,'paid'=>$paid,'date_from'=>$date_from,'date_to'=>$date_to
]));

$t = "Orders"; $a = "orders";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<div class="page-hdr">
  <h1>Orders <span style="font-size:14px;color:var(--t2);font-weight:400">(<?= $totalCount ?>)</span></h1>
  <div style="display:flex;gap:8px">
    <a href="export.php?<?= $qs ?>" class="btn btn-outline btn-sm" title="Download CSV">&#8659; Export CSV</a>
    <a href="add.php" class="btn btn-primary">+ New order</a>
  </div>
</div>

<!-- Filter bar -->
<div class="card">
<form method="GET" class="filter-bar" style="flex-wrap:wrap;gap:8px">
  <input type="text" name="q" class="fc" placeholder="Search order # or customer…"
         value="<?= h($q) ?>" style="width:200px">
  <select name="status" class="fc">
    <option value="">All statuses</option>
    <?php foreach(["pending","processing","completed","cancelled"] as $s): ?>
    <option value="<?= $s ?>" <?= $status===$s?"selected":"" ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="paid" class="fc">
    <option value="">All payments</option>
    <option value="0" <?= $paid==="0"?"selected":"" ?>>Unpaid only</option>
    <option value="1" <?= $paid==="1"?"selected":"" ?>>Paid only</option>
  </select>
  <input type="date" name="date_from" class="fc" value="<?= h($date_from) ?>" title="From date">
  <input type="date" name="date_to"   class="fc" value="<?= h($date_to) ?>"   title="To date">
  <button class="btn btn-outline btn-sm">Filter</button>
  <?php if($q||$status||$paid!=""||$date_from||$date_to): ?>
  <a href="index.php" class="btn btn-outline btn-sm">Clear</a>
  <?php endif; ?>
  <span style="margin-left:auto;font-size:12px;color:var(--t2)">
    <?= $totalCount ?> order<?= $totalCount!==1?"s":"" ?>
  </span>
</form>

<!-- Orders table -->
<table class="tbl">
  <thead>
    <tr>
      <th>Order #</th><th>Customer</th><th>Items</th>
      <th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if(empty($orders)): ?>
  <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--t3)">No orders found.</td></tr>
  <?php else: ?>
  <?php foreach($orders as $order): ?>
  <tr>
    <td class="fw mono" style="font-size:11px"><?= h($order->getOrderNumber()) ?></td>
    <td><?= h($order->getCustomer()) ?></td>
    <td class="mono"><?= $order->getItemCount() ?> item<?= $order->getItemCount()!=1?"s":"" ?></td>
    <td class="mono"><?= $order->getFormattedTotal() ?></td>

    <!-- Status: dropdown if editable, badge if locked -->
    <td>
      <?php if($order->isEditable()): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="order_id" value="<?= $order->getId() ?>">
        <select name="new_status" class="fc"
                style="height:28px;padding:2px 8px;font-size:11px;width:120px"
                onchange="if(confirm('Update status?'))this.form.submit()">
          <?php foreach(["pending","processing","completed","cancelled"] as $s): ?>
          <option value="<?= $s ?>" <?= $order->getStatus()===$s?"selected":"" ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php else: ?>
      <span class="badge <?= $order->getStatusBadge() ?>"><?= ucfirst($order->getStatus()) ?></span>
      <?php endif; ?>
    </td>

    <!-- Payment toggle button — uses isPaid() boolean -->
    <td>
      <form method="POST" style="margin:0">
        <input type="hidden" name="pay_id"       value="<?= $order->getId() ?>">
        <input type="hidden" name="current_paid"  value="<?= $order->getIsPaid() ?>">
        <button type="submit"
                class="badge <?= $order->getPaidBadge() ?>"
                style="border:none;cursor:pointer;font-size:11px"
                onclick="return confirm('<?= $order->isPaid()?"Mark as Unpaid?":"Mark as Paid?" ?>')"
                title="Click to toggle">
          <?= $order->getPaidLabel() ?>
        </button>
      </form>
    </td>

    <td class="muted" style="font-size:11px"><?= $order->getFormattedDate() ?></td>

    <td><div style="display:flex;gap:5px">
      <a href="view.php?id=<?= $order->getId() ?>"    class="icon-btn" title="View">&#128065;</a>
      <a href="invoice.php?id=<?= $order->getId() ?>" class="icon-btn" title="Print invoice">&#128438;</a>
      <?php if($order->isEditable()): ?>
      <a href="edit.php?id=<?= $order->getId() ?>" class="icon-btn" title="Edit">&#9998;</a>
      <?php endif; ?>
      <?php if(isOwner() && $order->isCancellable()): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $order->getId() ?>">
        <button class="icon-btn del" onclick="return confirm('Cancel this order?')" title="Cancel">&#128465;</button>
      </form>
      <?php endif; ?>
      <?php if(isOwner() && $order->getStatus()==='cancelled'): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="hard_delete_id" value="<?= $order->getId() ?>">
        <button class="icon-btn del" onclick="return confirm('Permanently DELETE this order? This cannot be undone.')" title="Delete permanently">&#10006;</button>
      </form>
      <?php endif; ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php endif; ?>
  </tbody>

  <!-- Summary row — shows totals across all filtered results -->
  <?php if($totalCount > 0): ?>
  <tfoot>
    <tr style="background:var(--bg2);font-weight:600;font-size:12px">
      <td colspan="3" style="padding:10px 14px;color:var(--t2)">
        Summary — <?= $summary['count'] ?> order<?= $summary['count']!=1?"s":"" ?>
      </td>
      <td class="mono" style="padding:10px 14px">
        $<?= number_format($summary['total'],2) ?>
      </td>
      <td style="padding:10px 14px"></td>
      <td style="padding:10px 14px;font-size:11px">
        <span class="badge b-green">Paid $<?= number_format($summary['paid'],2) ?></span><br>
        <span class="badge b-amber" style="margin-top:3px">Unpaid $<?= number_format($summary['unpaid'],2) ?></span>
      </td>
      <td colspan="2" style="padding:10px 14px"></td>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>

<!-- Pagination -->
<?php if($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:6px;padding:14px">
  <?php if($page > 1): ?>
  <a href="?<?= $qs ?>&page=<?= $page-1 ?>" class="btn btn-outline btn-sm">&larr; Prev</a>
  <?php endif; ?>

  <?php for($p=1;$p<=$totalPages;$p++): ?>
  <a href="?<?= $qs ?>&page=<?= $p ?>"
     class="btn btn-sm <?= $p===$page?"btn-primary":"btn-outline" ?>">
    <?= $p ?>
  </a>
  <?php endfor; ?>

  <?php if($page < $totalPages): ?>
  <a href="?<?= $qs ?>&page=<?= $page+1 ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
  <?php endif; ?>

  <span style="font-size:12px;color:var(--t2);margin-left:8px">
    Page <?= $page ?> of <?= $totalPages ?>
  </span>
</div>
<?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>