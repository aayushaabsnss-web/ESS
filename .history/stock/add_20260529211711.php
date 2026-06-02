<?php
/**
 * stock/add.php — Add Stock Transaction (Presentation Layer)
 * Uses Stock::validate() and Stock::add() static methods.
 * POST handler is BEFORE header.php to prevent headers-already-sent error.
 * Shows is_available boolean result after each transaction.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Stock.php";
requireLogin();

$preselect = (int)($_GET["pid"] ?? 0);
$errors    = [];

// ── POST handler BEFORE HTML ──────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $errors = Stock::validate($_POST, isOwner());
    if (!$errors) {
        [$ok, $err, $newQty] = Stock::add(
            $conn,
            (int)$_POST["product_id"],
            $_POST["type"],
            (int)$_POST["quantity"],
            $_SESSION["uid"],
            trim($_POST["notes"] ?? "")
        );

        if ($ok) {
            // Use is_available boolean to show meaningful success message
            // newQty > 0 means is_available = 1 (true), = 0 means is_available = 0 (false)
            $availLabel = $newQty > 0 ? "In Stock" : "Out of Stock";
            $availMsg   = $newQty > 0
                ? "Stock updated. New quantity: $newQty units — product is now In Stock."
                : "Stock updated. New quantity: $newQty units — product is now Out of Stock.";
            flash("success", $availMsg);
            header("Location: index.php"); exit;
        } else {
            flash("error", $err);
        }
    }
}

// ── Fetch product list for dropdown ──────────────────────
$productList = Stock::getProductList($conn);

// ── HTML starts here ──────────────────────────────────────
$t = "Record Stock"; $a = "stock";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<!-- Show all validation errors as list -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="border-radius:6px;padding:12px 16px;margin-bottom:14px">
  <strong style="display:block;margin-bottom:6px">Please fix the following:</strong>
  <ul style="margin:0;padding-left:18px">
    <?php foreach($errors as $e): ?>
    <li style="margin-bottom:3px"><?= h($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="page-hdr">
  <a href="index.php" class="btn btn-outline btn-sm">&larr; Back to stock</a>
  <h1>Record Stock</h1>
</div>

<div class="card" style="max-width:620px">
  <div class="card-hdr"><span class="card-title">Record stock movement</span></div>
  <div class="card-body">
  <form method="POST">

    <!-- Product selection -->
    <div class="fg">
      <label>Product *</label>
      <select name="product_id" class="fc" required
              onchange="updateStockInfo(this)">
        <option value="">Select a product...</option>
        <?php foreach($productList as $p): ?>
        <option value="<?= $p["id"] ?>"
                data-qty="<?= $p["quantity"] ?>"
                <?= $preselect === $p["id"] ? "selected" : "" ?>>
          <?= h($p["name"]) ?> — <?= $p["quantity"] ?> in stock
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Live stock info — shows current availability before transaction -->
    <div id="stock-info" style="display:none;margin-bottom:12px;padding:10px 14px;
         background:var(--bg2);border-radius:6px;font-size:12px">
      <span style="color:var(--t2)">Current stock:</span>
      <strong id="current-qty" style="margin-left:6px"></strong>
      <span id="avail-badge" class="badge" style="margin-left:8px"></span>
    </div>

    <div class="form2">
      <!-- Transaction type -->
      <div class="fg">
        <label>Type *</label>
        <select name="type" class="fc" required
                value="<?= h($_POST["type"] ?? "IN") ?>">
          <option value="IN"  <?= ($_POST["type"]??"")=="IN"?"selected":"" ?>>IN — Delivery received</option>
          <option value="OUT" <?= ($_POST["type"]??"")=="OUT"?"selected":"" ?>>OUT — Item sold / removed</option>
          <?php if(isOwner()): ?>
          <option value="ADJUSTMENT" <?= ($_POST["type"]??"")=="ADJUSTMENT"?"selected":"" ?>>ADJUSTMENT — Manual correction</option>
          <?php endif; ?>
        </select>
      </div>

      <!-- Quantity -->
      <div class="fg">
        <label>Quantity *</label>
        <input type="number" name="quantity" min="1" class="fc"
               value="<?= h($_POST["quantity"] ?? "") ?>"
               placeholder="Enter quantity" required
               oninput="previewAvailability(this)">
      </div>
    </div>

    <!-- Preview — shows what is_available BOOLEAN will be after transaction -->
    <div id="avail-preview" style="display:none;margin-bottom:12px;padding:10px 14px;
         border-radius:6px;font-size:12px">
    </div>

    <!-- Notes -->
    <div class="fg">
      <label>Notes (optional)</label>
      <input type="text" name="notes" class="fc"
             value="<?= h($_POST["notes"] ?? "") ?>"
             placeholder="e.g. Delivery batch #42">
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
      <a href="index.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-primary">Add &rarr;</button>
    </div>

  </form>
  </div>
</div>

<script>
// Show current stock and availability when product is selected
function updateStockInfo(sel) {
  const qty = parseInt(sel.options[sel.selectedIndex]?.dataset.qty ?? -1);
  const info = document.getElementById("stock-info");
  if (qty < 0 || !sel.value) { info.style.display = "none"; return; }

  info.style.display = "block";
  document.getElementById("current-qty").textContent = qty + " units";

  // Show is_available boolean (1 or 0) based on current qty
  const badge = document.getElementById("avail-badge");
  if (qty > 0) {
    badge.textContent = "In Stock (is_available = 1)";
    badge.className = "badge b-green";
  } else {
    badge.textContent = "Out of Stock (is_available = 0)";
    badge.className = "badge b-red";
  }
  previewAvailability(document.querySelector("[name='quantity']"));
}

// Preview what is_available BOOLEAN will be AFTER the transaction
function previewAvailability(input) {
  const sel = document.querySelector("[name='product_id']");
  const type = document.querySelector("[name='type']").value;
  const currentQty = parseInt(sel.options[sel.selectedIndex]?.dataset.qty ?? 0);
  const qty = parseInt(input.value || 0);
  const preview = document.getElementById("avail-preview");

  if (!sel.value || qty <= 0) { preview.style.display = "none"; return; }

  let newQty = currentQty;
  if (type === "IN") newQty = currentQty + qty;
  else if (type === "OUT") newQty = currentQty - qty;
  else newQty = qty; // ADJUSTMENT sets absolute qty

  // This is what is_available TINYINT(1) will be saved as
  const isAvailable = newQty > 0 ? 1 : 0;
  const label = isAvailable === 1 ? "In Stock" : "Out of Stock";
  const color = isAvailable === 1 ? "#E1F5EE" : "#FCEBEB";
  const textColor = isAvailable === 1 ? "#085041" : "#791F1F";

  preview.style.display = "block";
  preview.style.background = color;
  preview.style.color = textColor;
  preview.innerHTML =
    `After this transaction: <strong>${newQty} units</strong> — ` +
    `<strong>${label}</strong> ` +
    `<span style="opacity:0.7">(is_available will be saved as ${isAvailable})</span>`;
}

// Trigger on page load if product was pre-selected
window.addEventListener("load", () => {
  const sel = document.querySelector("[name='product_id']");
  if (sel.value) updateStockInfo(sel);
});
</script>

<?php require_once "../includes/footer.php"; ?>