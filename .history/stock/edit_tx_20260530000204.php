<?php
/**
 * stock/edit_tx.php — Edit Stock Transaction (Presentation Layer)
 * Store Owner can edit the notes and type of a transaction.
 * Quantity is locked — changing it would corrupt the audit trail.
 * Uses Stock class for OOP object creation and method calls.
 * POST handler BEFORE header.php to prevent headers-already-sent error.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Stock.php";
requireLogin();
requireOwner(); // Only Store Owner can edit transactions

$id  = (int)($_GET["id"]  ?? 0);
$pid = (int)($_GET["pid"] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

// Fetch raw transaction row (Stock class has no getById for transactions)
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT sm.*, p.name product_name, p.sku, u.full_name moved_by_name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     JOIN users u ON u.id = sm.moved_by
     WHERE sm.id = $id"));

if (!$row) {
    flash("error", "Transaction not found.");
    header("Location: index.php"); exit;
}

// Wrap raw row in Stock object — gives access to all getter and business logic methods
$tx = new Stock($row);

$errors = [];

// ── POST handler BEFORE HTML ──────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $notes   = trim($_POST["notes"]   ?? "");
    $newType = trim($_POST["type"]    ?? $tx->getType());

    // ── Validation ────────────────────────────────────────
    if (strlen($notes) > 500)
        $errors[] = "Notes must not exceed 500 characters.";
    if (!in_array($newType, ["IN", "OUT", "ADJUSTMENT"]))
        $errors[] = "Invalid transaction type selected.";
    // Quantity must be checked — cannot change to 0 through type swap
    // (quantity stays locked, only notes and type can change)

    if (empty($errors)) {
        // Update notes via Stock class method
        Stock::updateNotes($conn, $id, $notes);

        // Update type if changed — direct update (no Stock method exists for type)
        if ($newType !== $tx->getType()) {
            $stmt = mysqli_prepare($conn,
                "UPDATE stock_movements SET type=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "si", $newType, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        flash("success", "Transaction updated successfully.");
        header("Location: view.php?id=$pid"); exit;
    }
}

// ── HTML starts here ──────────────────────────────────────
$t = "Edit Transaction"; $a = "stock";
require_once "../includes/header.php";
include   "../includes/flash.php";
?>

<!-- Validation errors -->
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
  <a href="view.php?id=<?= $pid ?>" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit Transaction</h1>
</div>

<div class="g2">

  <!-- LEFT: Edit form -->
  <div class="card">
    <div class="card-hdr">
      <span class="card-title"><?= h($tx->getProductName()) ?></span>
      <span class="badge <?= $tx->getTypeBadge() ?>"><?= $tx->getType() ?></span>
    </div>
    <div class="card-body">

      <!-- Transaction summary — read-only fields using Stock getter methods -->
      <div style="background:var(--bg2);border-radius:6px;padding:12px 14px;
                  margin-bottom:18px;font-size:12px">
        <div style="font-weight:500;margin-bottom:10px;color:var(--t1)">
          Transaction details (read-only)
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">

          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">Product</span>
            <strong><?= h($tx->getProductName()) ?></strong>
          </div>
          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">SKU</span>
            <span class="mono"><?= h($tx->getSku()) ?></span>
          </div>
          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">
              Quantity <small style="color:var(--t3)">(locked)</small>
            </span>
            <span class="mono fw" style="font-size:14px"><?= $tx->getSignedQuantity() ?> units</span>
          </div>
          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">Date</span>
            <span class="muted"><?= $tx->getFormattedDate() ?></span>
          </div>
          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">Recorded by</span>
            <strong><?= h($tx->getMovedBy()) ?></strong>
          </div>
          <div>
            <span style="color:var(--t2);display:block;margin-bottom:2px">
              Availability <small style="color:var(--t3)">(BOOLEAN)</small>
            </span>
            <!-- isAvailable() reads is_available TINYINT(1) from DB -->
            <span class="badge <?= $tx->getAvailabilityBadge() ?>" style="font-size:11px">
              <?= $tx->getAvailabilityLabel() ?>
            </span>
            <span style="font-size:10px;color:var(--t3);margin-left:4px">
              (<?= $tx->getIsAvailable() ?>)
            </span>
          </div>
        </div>
      </div>

      <!-- Quantity locked notice -->
      <div style="background:#FFF8E1;border-left:3px solid #F59E0B;padding:10px 14px;
                  margin-bottom:16px;border-radius:0 6px 6px 0;font-size:12px;color:#92400E">
        &#128274; Quantity is locked at <strong><?= $tx->getSignedQuantity() ?> units</strong>.
        Changing quantity would corrupt the stock audit trail. To correct stock,
        record a new ADJUSTMENT transaction instead.
      </div>

      <form method="POST">

        <!-- Transaction type — editable by owner -->
        <div class="fg" style="margin-bottom:14px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Transaction type
          </label>
          <select name="type" class="fc">
            <option value="IN"         <?= ($tx->getType()==="IN")?"selected":"" ?>>
              IN — Delivery received
            </option>
            <option value="OUT"        <?= ($tx->getType()==="OUT")?"selected":"" ?>>
              OUT — Item sold / removed
            </option>
            <option value="ADJUSTMENT" <?= ($tx->getType()==="ADJUSTMENT")?"selected":"" ?>>
              ADJUSTMENT — Manual correction
            </option>
          </select>
          <small style="color:var(--t2);margin-top:4px;display:block">
            Only change the type if it was recorded incorrectly.
          </small>
        </div>

        <!-- Notes — fully editable -->
        <div class="fg" style="margin-bottom:18px">
          <label style="font-size:12px;color:var(--t2);display:block;margin-bottom:4px">
            Notes
            <span style="color:var(--t3)">(max 500 characters)</span>
          </label>
          <input type="text" name="notes" maxlength="500" class="fc"
                 value="<?= h($_POST["notes"] ?? $tx->getNotes()) ?>"
                 placeholder="e.g. Delivery batch #42, damaged items removed">
          <small id="notes-count" style="color:var(--t2);margin-top:4px;display:block">
            <?= strlen($tx->getNotes()) ?>/500 characters
          </small>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end">
          <a href="view.php?id=<?= $pid ?>" class="btn btn-outline">Cancel</a>
          <button class="btn btn-primary">Save changes &rarr;</button>
        </div>

      </form>
    </div>
  </div>

  <!-- RIGHT: What can be edited -->
  <div class="card">
    <div class="card-hdr"><span class="card-title">Editing guidelines</span></div>
    <div class="card-body" style="font-size:12px">

      <div style="margin-bottom:16px">
        <div style="font-weight:600;margin-bottom:6px;color:var(--t1)">&#10003; Can be changed</div>
        <ul style="padding-left:16px;line-height:2;color:var(--t2)">
          <li><strong>Notes</strong> — correct spelling, add reference numbers, add context</li>
          <li><strong>Type</strong> — if IN/OUT/ADJUSTMENT was recorded incorrectly</li>
        </ul>
      </div>

      <div style="margin-bottom:16px">
        <div style="font-weight:600;margin-bottom:6px;color:var(--t1)">&#128274; Cannot be changed</div>
        <ul style="padding-left:16px;line-height:2;color:var(--t2)">
          <li><strong>Quantity</strong> — locked to preserve the audit trail</li>
          <li><strong>Date</strong> — original timestamp preserved for compliance</li>
          <li><strong>Recorded by</strong> — linked to the original user session</li>
        </ul>
      </div>

      <div style="background:#E1F5EE;border-radius:6px;padding:10px 14px;color:#085041">
        <strong>Need to fix the quantity?</strong><br>
        Delete this transaction and record a new one, or record a corrective
        ADJUSTMENT transaction to bring the total to the correct level.
      </div>

      <!-- Availability status explanation -->
      <div style="margin-top:14px;background:var(--bg2);border-radius:6px;padding:10px 14px">
        <div style="font-weight:600;margin-bottom:6px">Availability (BOOLEAN)</div>
        <div style="color:var(--t2);line-height:1.8">
          The <code>is_available</code> column is stored as
          <strong>TINYINT(1)</strong> in the database:<br>
          <span class="badge b-green" style="font-size:10px">1 = In Stock</span>
          — quantity was &gt; 0 after this movement<br>
          <span class="badge b-red" style="font-size:10px">0 = Out of Stock</span>
          — quantity reached 0 after this movement
        </div>
        <div style="margin-top:8px;font-size:11px;color:var(--t3)">
          This transaction: is_available =
          <strong><?= $tx->getIsAvailable() ?></strong>
          (<?= $tx->getAvailabilityLabel() ?>)
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Character counter for notes field
document.querySelector("[name='notes']").addEventListener("input", function() {
  document.getElementById("notes-count").textContent =
    this.value.length + "/500 characters";
  this.style.borderColor = this.value.length > 500 ? "var(--danger)" : "";
});
</script>

<?php require_once "../includes/footer.php"; ?>