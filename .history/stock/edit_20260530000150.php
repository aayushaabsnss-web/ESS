<?php
/**
 * stock/edit.php — Edit Stock Settings (Presentation Layer)
 * Allows Store Owner to update min stock level and product stock note.
 * Uses Stock::updateMinQty() and Product::getById() — three-layer architecture.
 * POST handler BEFORE header.php to prevent headers-already-sent error.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Stock.php";
require_once "../classes/Product.php";
requireLogin();
requireOwner(); // Only Store Owner can edit stock settings

$id  = (int)($_GET["id"] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

// Fetch Product object via OOP class — NOT raw SQL
$product = Product::getById($conn, $id);
if (!$product) {
    flash("error", "Product not found.");
    header("Location: index.php"); exit;
}

$errors = [];

// ── POST handler BEFORE HTML ──────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $min_qty    = (int)($_POST["min_qty"]    ?? 0);
    $notes      = trim($_POST["stock_note"]  ?? "");

    // ── Validation ────────────────────────────────────────
    if ($min_qty < 1)
        $errors[] = "Minimum stock level must be at least 1 unit.";
    if ($min_qty > 9999)
        $errors[] = "Minimum stock level cannot exceed 9,999.";
    if (strlen($notes) > 300)
        $errors[] = "Stock note must not exceed 300 characters.";

    if (empty($errors)) {
        // Update min qty via Stock class method — middle layer
        Stock::updateMinQty($conn, $id, $min_qty);

        // Update stock note directly (simple update)
        if ($notes !== "") {
            $stmt = mysqli_prepare($conn,
                "UPDATE products SET description=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "si", $notes, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        flash("success", "Stock settings updated for ".$product->getName().".");
        header("Location: view.php?id=$id"); exit;
    }
}

// ── HTML starts here ──────────────────────────────────────
$t = "Edit Stock Settings"; $a = "stock";
require_once "../includes/header.php";
include   "../includes/flash.php";

// Get latest transaction to show is_available boolean
$transactions = Stock::getByProduct($conn, $id, 1);
$latest = $transactions[0] ?? null;
?>

<!-- Validation error list -->
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
  <a href="view.php?id=<?= $id ?>" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit Stock Settings</h1>
</div>

<div class="g2">

  <!-- LEFT: Edit form -->
  <div class="card">
    <div class="card-hdr">
      <span class="card-title"><?= h($product->getName()) ?></span>
      <span class="badge <?= $product->getStockBadge() ?>"><?= $product->getStockStatus() ?></span>
    </div>
    <div class="card-body">

      <!-- Current stock summary -->
      <div style="background:var(--bg2);border-radius:6px;padding:12px 14px;
                  margin-bottom:18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;
                  font-size:12px;text-align:center">
        <div>
          <div style="color:var(--t2);margin-bottom:4px">Current stock</div>
          <div style="font-size:22px;font-weight:700;font-family:var(--mono)">
            <?= $product->getQuantity() ?>
          </div>
          <div style="color:var(--t2);font-size:10px">units</div>
        </div>
        <div>
          <div style="color:var(--t2);margin-bottom:4px">Min level</div>
          <div style="font-size:22px;font-weight:700;font-family:var(--mono)">
            <?= $product->getMinQty() ?>
          </div>
          <div style="color:var(--t2);font-size:10px">units</div>
        </div>
        <div>
          <div style="color:var(--t2);margin-bottom:4px">Availability</div>
          <div style="margin-top:6px">
            <?php if ($latest): ?>
            <!-- is_available BOOLEAN badge — TINYINT(1) from stock_movements -->
            <span class="badge <?= $latest->getAvailabilityBadge() ?>" style="font-size:11px">
              <?= $latest->getAvailabilityLabel() ?>
            </span>
            <div style="font-size:10px;color:var(--t3);margin-top:3px">
              TINYINT(1) = <?= $latest->getIsAvailable() ?>
            </div>
            <?php else: ?>
            <span class="badge b-gray">No data</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Warning if stock at or below minimum -->
      <?php if ($product->getQuantity() <= $product->getMinQty()): ?>
      <div class="alert alert-warning" style="font-size:12px;margin-bottom:14px;
           padding:10px 14px;border-radius:6px;background:#FFF3CD;color:#856404">
        &#9888; Current stock (<?= $product->getQuantity() ?>) is at or below
        minimum level (<?= $product->getMinQty() ?>). Consider restocking.
      </div>
      <?php endif; ?>

      <form method="POST">

        <!-- Min stock level -->
        <div class="fg" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Minimum stock level (alert threshold) *
          </label>
          <input type="number" name="min_qty" min="1" max="9999" class="fc"
                 value="<?= h($_POST["min_qty"] ?? $product->getMinQty()) ?>"
                 required>
          <small style="color:var(--t2);margin-top:4px;display:block">
            An alert fires automatically when quantity drops to or below this number.
            Current quantity is <?= $product->getQuantity() ?> units.
          </small>
        </div>

        <!-- Alert preview -->
        <div id="alert-preview" style="display:none;margin-bottom:14px;padding:10px 14px;
             border-radius:6px;font-size:12px"></div>

        <!-- Stock note -->
        <div class="fg" style="margin-bottom:18px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Stock note (optional)
          </label>
          <input type="text" name="stock_note" maxlength="300" class="fc"
                 value="<?= h($_POST["stock_note"] ?? $product->getDescription()) ?>"
                 placeholder="e.g. Stored in Warehouse B, reorder from Apple supplier">
          <small style="color:var(--t2);margin-top:4px;display:block">
            Max 300 characters. Used for internal reference only.
          </small>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end">
          <a href="view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
          <button class="btn btn-primary">Save changes &rarr;</button>
        </div>
      </form>
    </div>
  </div>

  <!-- RIGHT: Quick actions -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Quick actions</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <a href="add.php?pid=<?= $id ?>&type=IN" class="btn btn-outline w100">
        + Record stock IN (delivery)
      </a>
      <a href="add.php?pid=<?= $id ?>&type=OUT" class="btn btn-outline w100">
        − Record stock OUT (sold/removed)
      </a>
      <a href="add.php?pid=<?= $id ?>&type=ADJUSTMENT" class="btn btn-outline w100">
        &#9965; Manual adjustment
      </a>
      <a href="view.php?id=<?= $id ?>" class="btn btn-outline w100">
        &#128065; View transaction history
      </a>

      <!-- Product details read-only -->
      <div style="margin-top:10px;padding:12px;background:var(--bg2);border-radius:6px;font-size:12px">
        <div style="font-weight:500;margin-bottom:8px;color:var(--t1)">Product details</div>
        <?php foreach([
          "SKU"      => $product->getSku(),
          "Category" => $product->getCategory(),
          "Price"    => $product->getFormattedPrice(),
          "Supplier" => $product->getSupplier(),
        ] as $k => $v): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="color:var(--t2)"><?= $k ?></span>
          <span style="font-weight:500"><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Preview alert threshold warning as user types
document.querySelector("[name='min_qty']").addEventListener("input", function() {
  const min     = parseInt(this.value || 0);
  const current = <?= $product->getQuantity() ?>;
  const preview = document.getElementById("alert-preview");

  if (!this.value) { preview.style.display = "none"; return; }

  if (current <= min) {
    preview.style.display = "block";
    preview.style.background = "#FFF3CD";
    preview.style.color = "#856404";
    preview.textContent = "⚠ With this threshold, an alert will trigger immediately — current stock (" +
      current + ") is at or below " + min + " units.";
  } else {
    preview.style.display = "block";
    preview.style.background = "#E1F5EE";
    preview.style.color = "#085041";
    preview.textContent = "✓ Alert will trigger when stock drops to " + min + " units (" +
      (current - min) + " units of buffer remaining).";
  }
});
</script>

<?php require_once "../includes/footer.php"; ?>