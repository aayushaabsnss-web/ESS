<?php
/**
 * orders/add.php — Create New Order (Presentation Layer)
 * Multi-product order form with live total calculator.
 * Uses Order::validate() and Order::create() static methods.
 * Stock is NOT deducted on creation — only on completion via updateStatus().
 * Access: All authenticated users.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
requireLogin();

// ── POST handler BEFORE HTML starts ──────────────────────
$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer = trim($_POST["customer"] ?? "");
    $notes    = trim($_POST["notes"]    ?? "");
    $is_paid  = isset($_POST["is_paid"]) ? 1 : 0; // BOOLEAN — checkbox: 1=paid, 0=unpaid
    $pids     = $_POST["pids"] ?? [];
    $qtys     = $_POST["qtys"] ?? [];

    // Build items array from parallel POST arrays
    $items = [];
    foreach ($pids as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($qtys[$i] ?? 1);
        if ($pid <= 0 || $qty <= 0) continue;

        // Verify product exists and has sufficient stock
        $p = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id, price, name, quantity FROM products WHERE id=$pid AND is_active=1"));
        if (!$p) { $err = "Product not found."; break; }
        if ($p["quantity"] < $qty) { $err = h($p["name"])." — only {$p["quantity"]} in stock."; break; }

        $items[] = [
            "product_id" => $pid,
            "quantity"   => $qty,
            "price"      => (float)$p["price"]
        ];
    }

    if (!$err) {
        // Validate using Order class static method
        $errors = Order::validate($customer, $items);
        if (!$errors) {
            // Create order — passes is_paid boolean (0 or 1) to be saved in DB
            $oid = Order::create($conn, $customer, $notes, $items, $_SESSION["uid"], $is_paid);
            if ($oid) {
                flash("success", "Order created successfully.");
                header("Location: index.php");
                exit;
            } else {
                $err = "Failed to create order. Please try again.";
            }
        } else {
            $err = implode(" ", $errors);
        }
    }
}

// ── Fetch products for dropdowns ──────────────────────────
$products = [];
$r = mysqli_query($conn,
    "SELECT id, name, price, quantity, category
     FROM products WHERE is_active=1 AND quantity>0 ORDER BY name");
while ($p = mysqli_fetch_assoc($r)) $products[] = $p;

// ── HTML starts here ──────────────────────────────────────
$t = "New Order"; $a = "orders";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<?php if ($err): ?>
<div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<div class="page-hdr">
  <a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>New Order</h1>
</div>

<div class="card" style="max-width:800px">
  <div class="card-hdr"><span class="card-title">Order details</span></div>
  <div class="card-body">
    <form method="POST" id="order-form">

      <div class="form2">
        <div class="fg">
          <label>Customer name *</label>
          <input type="text" name="customer" class="fc"
                 value="<?= h($_POST["customer"] ?? "") ?>" required>
        </div>
        <div class="fg">
          <label>Notes</label>
          <input type="text" name="notes" class="fc"
                 value="<?= h($_POST["notes"] ?? "") ?>" placeholder="Optional notes">
        </div>
      </div>

      <!-- Payment status — boolean stored as TINYINT(1) in orders table -->
      <div class="fg" style="margin-bottom:14px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="is_paid" value="1"
                 <?= isset($_POST["is_paid"]) ? "checked" : "" ?>>
          <span>Mark as paid</span>
          <span class="badge b-amber" style="font-size:10px">TINYINT(1) boolean</span>
        </label>
        <small style="color:var(--t2);margin-top:4px;display:block">
          Tick if payment has already been received. Stored as 1 (paid) or 0 (unpaid) in the database.
        </small>
      </div>

      <!-- Order line items -->
      <div style="border:1px solid var(--b);border-radius:var(--r);overflow:hidden;margin-bottom:14px">
        <div class="card-hdr" style="background:var(--bg3)">
          <span class="card-title">Products</span>
          <button type="button" class="btn btn-outline btn-sm" onclick="addRow()">+ Add row</button>
        </div>
        <div id="rows" style="padding:12px;display:flex;flex-direction:column;gap:8px">
          <div class="oi-row" style="display:grid;grid-template-columns:1fr 100px 80px;gap:10px;align-items:center">
            <select name="pids[]" class="fc oi-pid" required onchange="updatePrice(this);calcTotal()">
              <option value="">Select product…</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p["id"] ?>" data-price="<?= $p["price"] ?>">
                <?= h($p["name"]) ?> — $<?= number_format($p["price"], 2) ?> (<?= $p["quantity"] ?> left)
              </option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="qtys[]" class="fc oi-qty" min="1" value="1"
                   required oninput="calcTotal()">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button>
          </div>
        </div>
      </div>

      <!-- Live order total -->
      <div style="text-align:right;font-size:14px;font-weight:600;margin-bottom:14px">
        Order total: <span id="order-total">$0.00</span>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end">
        <a href="index.php" class="btn btn-outline">Cancel</a>
        <button class="btn btn-primary">Create order &rarr;</button>
      </div>

    </form>
  </div>
</div>

<script>
// Add a new product line item row
function addRow() {
  const tmpl = document.querySelector(".oi-row").cloneNode(true);
  tmpl.querySelector(".oi-pid").value = "";
  tmpl.querySelector(".oi-qty").value = 1;
  tmpl.querySelector(".oi-pid").addEventListener("change", function () { updatePrice(this); calcTotal(); });
  tmpl.querySelector(".oi-qty").addEventListener("input", calcTotal);
  document.getElementById("rows").appendChild(tmpl);
}

// Remove a line item row (keep at least one)
function removeRow(btn) {
  if (document.querySelectorAll(".oi-row").length > 1) btn.closest(".oi-row").remove();
  calcTotal();
}

// Store selected product price on the select element for calculation
function updatePrice(sel) {
  sel.dataset.price = sel.options[sel.selectedIndex].dataset.price || 0;
}

// Recalculate and display live order total from all rows
function calcTotal() {
  let t = 0;
  document.querySelectorAll(".oi-row").forEach(row => {
    t += parseInt(row.querySelector(".oi-qty")?.value || 0)
       * parseFloat(row.querySelector(".oi-pid")?.dataset.price || 0);
  });
  document.getElementById("order-total").textContent = "$" + t.toFixed(2);
}

// Attach events to initial row
document.querySelectorAll(".oi-pid").forEach(s => {
  s.addEventListener("change", function () { updatePrice(this); calcTotal(); });
});
document.querySelectorAll(".oi-qty").forEach(i => i.addEventListener("input", calcTotal));
</script>

<?php require_once "../includes/footer.php"; ?>