<?php
/**
 * orders/edit.php — Edit Order (Presentation Layer)
 * Allows editing customer name, notes, payment status.
 * Allows updating order status.
 * Allows adding more products to an existing order.
 * Uses Order::update(), Order::updateStatus(), Order::addItems() static methods.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
require_once "../classes/StockMovement.php";
requireLogin();

$id = (int)($_GET["id"] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

// ── POST: update details (customer, notes, is_paid) ───────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_details") {
    $customer = trim($_POST["customer"] ?? "");
    $notes    = trim($_POST["notes"]    ?? "");
    $is_paid  = isset($_POST["is_paid"]) ? 1 : 0;
    if (empty($customer)) {
        flash("error", "Customer name is required.");
    } else {
        Order::update($conn, $id, $customer, $notes, $is_paid);
        flash("success", "Order details updated.");
    }
    header("Location: edit.php?id=$id"); exit;
}

// ── POST: add more products to this order ─────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_items") {
    $pids = $_POST["pids"] ?? [];
    $qtys = $_POST["qtys"] ?? [];
    $newItems = [];
    $err = "";

    foreach ($pids as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($qtys[$i] ?? 1);
        if ($pid <= 0 || $qty <= 0) continue;

        // Check product exists and has enough stock
        $p = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id, name, price, quantity FROM products WHERE id=$pid AND is_active=1"));
        if (!$p) { $err = "Product not found."; break; }
        if ($p["quantity"] < $qty) {
            $err = h($p["name"])." — only {$p["quantity"]} in stock."; break;
        }
        $newItems[] = [
            "product_id" => $pid,
            "quantity"   => $qty,
            "price"      => (float)$p["price"]
        ];
    }

    if ($err) {
        flash("error", $err);
    } elseif (empty($newItems)) {
        flash("error", "Please select at least one product.");
    } else {
        Order::addItems($conn, $id, $newItems);
        flash("success", "Items added to order. Total updated.");
    }
    header("Location: edit.php?id=$id"); exit;
}

// ── POST: update order status ─────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["new_status"])) {
    Order::updateStatus($conn, $id, $_POST["new_status"], $_SESSION["uid"]);
    flash("success", "Order status updated.");
    header("Location: edit.php?id=$id"); exit;
}

// ── Fetch order and items ─────────────────────────────────
$order = Order::getById($conn, $id);
if (!$order) {
    flash("error", "Order not found.");
    header("Location: index.php"); exit;
}
if (!$order->isEditable()) {
    flash("error", "This order cannot be edited.");
    header("Location: index.php"); exit;
}

$items    = Order::getItems($conn, $id);
$products = [];
$r = mysqli_query($conn,
    "SELECT id, name, price, quantity, category
     FROM products WHERE is_active=1 AND quantity>0 ORDER BY name");
while ($p = mysqli_fetch_assoc($r)) $products[] = $p;

// ── HTML starts here ──────────────────────────────────────
$t = "Edit Order"; $a = "orders";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<div class="page-hdr">
  <a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit — <?= h($order->getOrderNumber()) ?></h1>
  <span class="badge <?= $order->getStatusBadge() ?>" style="font-size:12px">
    <?= ucfirst($order->getStatus()) ?>
  </span>
  <span class="badge <?= $order->getPaidBadge() ?>" style="font-size:12px">
    <?= $order->getPaidLabel() ?>
  </span>
</div>

<div class="g2">

  <!-- LEFT: Edit details form -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Edit details</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_details">

        <div class="fg" style="margin-bottom:12px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">Customer name *</label>
          <input type="text" name="customer" class="fc"
                 value="<?= h($order->getCustomer()) ?>" required>
        </div>

        <div class="fg" style="margin-bottom:12px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">Notes</label>
          <input type="text" name="notes" class="fc"
                 value="<?= h($order->getNotes()) ?>"
                 placeholder="Optional notes">
        </div>

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
            TINYINT(1) — 1 = paid, 0 = unpaid
          </small>
        </div>

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

  <!-- RIGHT: Status buttons -->
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
          <?= $s==="completed" ? "onclick=\"return confirm('Complete order? Stock will be deducted for all items.')\"" : "" ?>>
          <?= $lbl ?>
        </button>
      </form>
      <?php endif; ?>
      <?php endforeach; ?>
      <div style="margin-top:4px;font-size:11px;color:var(--t2);padding:8px;background:var(--bg2);border-radius:var(--r)">
        Current: <span class="badge <?= $order->getStatusBadge() ?>"><?= ucfirst($order->getStatus()) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Current line items -->
<div class="card">
  <div class="card-hdr">
    <span class="card-title">Current items</span>
    <span style="font-size:12px;color:var(--t2)">
      <?= $order->getItemCount() ?> item<?= $order->getItemCount() != 1 ? "s" : "" ?>
    </span>
  </div>
  <table class="tbl">
    <thead>
      <tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
    <tr>
      <td class="fw"><?= h($item->getProductName()) ?></td>
      <td class="mono muted"><?= h($item->getSku()) ?></td>
      <td class="mono"><?= $item->getQuantity() ?></td>
      <td class="mono"><?= $item->getFormattedUnitPrice() ?></td>
      <td class="mono fw"><?= $item->getFormattedLineTotal() ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="border-top:2px solid var(--b2)">
      <td colspan="4" style="text-align:right;font-weight:600;padding:10px 14px">Order total</td>
      <td class="mono fw" style="padding:10px 14px"><?= $order->getFormattedTotal() ?></td>
    </tr>
    </tbody>
  </table>
</div>

<!-- Add more products to this order -->
<div class="card">
  <div class="card-hdr">
    <span class="card-title">Add more products</span>
    <span style="font-size:12px;color:var(--t2)">
      Items added here will be added to the existing order and total recalculated
    </span>
  </div>
  <div class="card-body">
    <form method="POST" id="add-items-form">
      <input type="hidden" name="action" value="add_items">

      <div id="new-rows" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
        <div class="new-row" style="display:grid;grid-template-columns:1fr 100px 80px;gap:10px;align-items:center">
          <select name="pids[]" class="fc new-pid" required
                  onchange="updateNewPrice(this);calcNewTotal()">
            <option value="">Select product…</option>
            <?php foreach ($products as $p): ?>
            <option value="<?= $p["id"] ?>" data-price="<?= $p["price"] ?>">
              <?= h($p["name"]) ?> — $<?= number_format($p["price"], 2) ?> (<?= $p["quantity"] ?> in stock)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="qtys[]" class="fc new-qty"
                 min="1" value="1" required oninput="calcNewTotal()">
          <button type="button" class="btn btn-danger btn-sm"
                  onclick="removeNewRow(this)">Remove</button>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <button type="button" class="btn btn-outline btn-sm"
                onclick="addNewRow()">+ Add another product</button>
        <span style="margin-left:auto;font-size:13px;font-weight:600">
          Adding: <span id="new-total" style="color:var(--primary)">$0.00</span>
        </span>
      </div>

      <button class="btn btn-primary">Add items to order &rarr;</button>
    </form>
  </div>
</div>

<script>
// Add a new product row to the add-items form
function addNewRow() {
  const tmpl = document.querySelector(".new-row").cloneNode(true);
  tmpl.querySelector(".new-pid").value = "";
  tmpl.querySelector(".new-qty").value = 1;
  tmpl.querySelector(".new-pid").addEventListener("change", function () {
    updateNewPrice(this); calcNewTotal();
  });
  tmpl.querySelector(".new-qty").addEventListener("input", calcNewTotal);
  document.getElementById("new-rows").appendChild(tmpl);
}

// Remove a row — keep at least one
function removeNewRow(btn) {
  if (document.querySelectorAll(".new-row").length > 1)
    btn.closest(".new-row").remove();
  calcNewTotal();
}

// Store price on the select for calculation
function updateNewPrice(sel) {
  sel.dataset.price = sel.options[sel.selectedIndex].dataset.price || 0;
}

// Calculate the total of NEW items being added
function calcNewTotal() {
  let t = 0;
  document.querySelectorAll(".new-row").forEach(row => {
    t += parseInt(row.querySelector(".new-qty")?.value || 0)
       * parseFloat(row.querySelector(".new-pid")?.dataset.price || 0);
  });
  document.getElementById("new-total").textContent = "$" + t.toFixed(2);
}

// Attach events to the initial row
document.querySelectorAll(".new-pid").forEach(s => {
  s.addEventListener("change", function () { updateNewPrice(this); calcNewTotal(); });
});
document.querySelectorAll(".new-qty").forEach(i => i.addEventListener("input", calcNewTotal));
</script>

<?php require_once "../includes/footer.php"; ?>