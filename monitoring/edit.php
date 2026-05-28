<?php
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Alert.php";
include  "../includes/flash.php";
requireOwner();

$id = (int)($_GET["id"] ?? 0);
$al = Alert::getById($conn, $id);
if (!$al) { header("Location: index.php"); exit; }

$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $thr    = (int)$_POST["threshold"];
    $status = $_POST["alert_status"] ?? "";

    if ($thr < 1) {
        $err = "Threshold must be at least 1.";
    } elseif (!in_array($status, ["active", "resolved"])) {
        $err = "Invalid status selected.";
    } else {
        // Update threshold
        Alert::setThreshold($conn, $al->getProductId(), $thr);

        // Update status
        if ($status === "resolved" && $al->isActive()) {
            Alert::resolve($conn, $id, $_SESSION["uid"]);
        } elseif ($status === "active") {
            mysqli_query($conn, "UPDATE monitoring SET alert_status='active', resolved_at=NULL, resolved_by=NULL WHERE id=$id");
        }

        flash("success", "Alert updated for " . $al->getProductName() . ".");
        header("Location: index.php"); exit;
    }
}

$t = "Edit Alert"; $a = "monitoring";
require_once "../includes/header.php";
?>
<div class="page-hdr">
  <h1>Edit Alert</h1>
  <a href="index.php" class="btn btn-outline">&larr; Back</a>
</div>

<div class="card" style="max-width:500px">
  <div class="card-hdr"><span class="card-title"><?= h($al->getProductName()) ?></span></div>
  <div class="card-body">
    <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

    <table class="tbl" style="margin-bottom:20px">
      <tr><td class="muted">Product</td><td><?= h($al->getProductName()) ?></td></tr>
      <tr><td class="muted">Current Qty</td><td class="mono <?= $al->getQtyColor() ?>"><?= $al->getCurrentQty() ?></td></tr>
      <tr><td class="muted">Shortfall</td><td><span class="badge b-red"><?= $al->getShortfall() ?></span></td></tr>
      <tr><td class="muted">Triggered</td><td class="muted"><?= $al->getFormattedAlertedAt() ?></td></tr>
    </table>

    <form method="POST">
      <div class="fg">
        <label>Threshold (min stock level)</label>
        <input type="number" name="threshold" class="fc" min="1"
               value="<?= $al->getThreshold() ?>" required>
      </div>
      <div class="fg">
        <label>Status</label>
        <select name="alert_status" class="fc">
          <option value="active"   <?= $al->isActive()  ? "selected" : "" ?>>Active</option>
          <option value="resolved" <?= !$al->isActive() ? "selected" : "" ?>>Resolved</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button class="btn btn-primary">Save Changes</button>
        <a href="index.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once "../includes/footer.php"; ?>