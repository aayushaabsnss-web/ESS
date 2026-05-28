<?php
/**
 * stock/edit.php — Edit Stock Settings (Presentation Layer)
 * Allows Store Owner to update min stock level, adjust quantity, and add notes.
 */
$t = "Edit Stock"; $a = "stock";
require_once "../includes/header.php";
require_once "../classes/Stock.php";
requireOwner();

$id = (int)($_GET["id"] ?? 0);
$p  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM products WHERE id=$id AND is_active=1"));
if(!$p){ flash("error","Product not found."); header("Location: index.php"); exit; }

$err = "";
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $min   = (int)$_POST["min_qty"];
    $qty   = (int)($_POST["new_qty"] ?? 0);
    $notes = trim($_POST["notes"] ?? "");

    if($min < 1){
        $err = "Minimum stock level must be at least 1.";
    } else {
        // Update min stock level
        Stock::updateMinQty($conn, $id, $min);

        // If quantity adjustment requested
        if($qty > 0 && $qty !== (int)$p["quantity"]){
            [$ok, $error] = Stock::add($conn, $id, "ADJUSTMENT", $qty, $_SESSION["uid"], $notes ?: "Manual stock adjustment");
            if(!$ok){ $err = $error; }
        }

        if(!$err){
            flash("success", "Stock settings updated successfully.");
            header("Location: view.php?id=$id"); exit;
        }
    }
}
?>
<?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<div class="page-hdr">
  <a href="view.php?id=<?= $id ?>" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit Stock Settings</h1>
</div>
<div class="card" style="max-width:500px">
  <div class="card-hdr"><span class="card-title"><?= h($p["name"]) ?></span></div>
  <div class="card-body">
  <form method="POST">

    <!-- Min Stock Level -->
    <div class="fg">
      <label>Minimum stock level (alert threshold)</label>
      <input type="number" name="min_qty" min="1" class="fc"
             value="<?= h($_POST["min_qty"] ?? $p["min_qty"]) ?>" required>
      <p style="font-size:11px;color:var(--t2);margin-top:6px">An alert fires automatically when quantity drops to or below this level.</p>
    </div>

    <!-- Quantity Adjustment -->
    <div class="fg">
      <label>Adjust quantity (current: <strong><?= $p["quantity"] ?> units</strong>)</label>
      <input type="number" name="new_qty" min="0" class="fc"
             value="<?= h($_POST["new_qty"] ?? $p["quantity"]) ?>">
      <p style="font-size:11px;color:var(--t2);margin-top:6px">Enter the new total quantity. Leave unchanged to keep current stock.</p>
    </div>

    <!-- Notes -->
    <div class="fg">
      <label>Notes (optional)</label>
      <input type="text" name="notes" class="fc"
             value="<?= h($_POST["notes"] ?? "") ?>"
             placeholder="e.g. Reason for adjustment">
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end">
      <a href="view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
      <button class="btn btn-primary">Save changes &rarr;</button>
    </div>
  </form>
  </div>
</div>
<?php require_once "../includes/footer.php"; ?>
