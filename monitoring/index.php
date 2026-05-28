<?php
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Alert.php";
include "../includes/flash.php";

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["resolve_id"])){
    requireOwner();
    Alert::resolve($conn, (int)$_POST["resolve_id"], $_SESSION["uid"]);
    flash("success","Alert resolved."); header("Location: index.php"); exit;
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["add_threshold"])){
    requireOwner();
    $pid = (int)$_POST["product_id"]; $thr = (int)$_POST["threshold"];
    if($pid && $thr > 0){
        Alert::setThreshold($conn, $pid, $thr);
        flash("success","Threshold set."); header("Location: index.php"); exit;
    }
}

$t = "Inventory Alerts"; $a = "monitoring";
require_once "../includes/header.php";

$active   = Alert::getActive($conn);
$resolved = Alert::getResolved($conn, 10);
$an       = count($active);
$prods    = mysqli_query($conn,"SELECT id,name FROM products WHERE is_active=1 ORDER BY name");
?>
<div class="page-hdr"><h1>Inventory Alerts <span style="font-size:14px;color:var(--t2);font-weight:400">(<?= $an ?> active)</span></h1></div>

<?php if(isOwner()): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-hdr"><span class="card-title">Set alert threshold</span></div>
  <div class="card-body">
  <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
    <div class="fg" style="margin:0;flex:1"><label>Product</label>
      <select name="product_id" class="fc" required><option value="">Select…</option>
        <?php while($p=mysqli_fetch_assoc($prods)): ?><option value="<?= $p["id"] ?>"><?= h($p["name"]) ?></option><?php endwhile; ?>
      </select></div>
    <div class="fg" style="margin:0;width:180px"><label>Min stock level</label>
      <input type="number" name="threshold" class="fc" min="1" placeholder="5" required></div>
    <button name="add_threshold" class="btn btn-primary">Set threshold</button>
  </form>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div class="card-hdr"><span class="card-title">Active alerts</span><a href="search.php" class="btn btn-outline btn-sm">Search alerts &rarr;</a></div>
  <table class="tbl">
    <thead><tr><th>Product</th><th>Current qty</th><th>Threshold</th><th>Shortfall</th><th>Triggered</th><?php if(isOwner()): ?><th>Action</th><?php endif; ?></tr></thead>
    <tbody>
    <?php $e=true; foreach($active as $al): $e=false; ?>
    <tr>
      <td class="fw"><?= h($al->getProductName()) ?></td>
      <td class="mono <?= $al->getQtyColor() ?>"><?= $al->getCurrentQty() ?></td>
      <td class="mono"><?= $al->getThreshold() ?></td>
      <td><span class="badge b-red"><?= $al->getShortfall() ?></span></td>
      <td class="muted" style="font-size:11px"><?= $al->getFormattedAlertedAt() ?></td>
      <?php if(isOwner()): ?>
      <td><div style="display:flex;gap:5px;align-items:center">
        <a href="view.php?id=<?= $al->getId() ?>" class="icon-btn" title="View">&#128065;</a>
        <a href="edit.php?id=<?= $al->getId() ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
        <form method="POST"><input type="hidden" name="resolve_id" value="<?= $al->getId() ?>">
        <button class="btn btn-success btn-sm">&#10003; Resolve</button></form>
      </div></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; if($e): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--t3)">&#10003; No active alerts.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-hdr"><span class="card-title">Recently resolved</span></div>
  <table class="tbl">
    <thead><tr><th>Product</th><th>Resolved</th><th>Resolved by</th><?php if(isOwner()): ?><th>Action</th><?php endif; ?></tr></thead>
    <tbody>
    <?php $e=true; foreach($resolved as $al): $e=false; ?>
    <tr>
      <td class="fw"><?= h($al->getProductName()) ?></td>
      <td class="muted"><?= $al->getFormattedResolvedAt() ?></td>
      <td><?= h($al->getResolvedBy() ?: "—") ?></td>
      <?php if(isOwner()): ?>
      <td>
        <a href="delete.php?id=<?= $al->getId() ?>" class="icon-btn del"
           onclick="return confirm('Delete this alert record?')" title="Delete">&#128465;</a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; if($e): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--t3)">No resolved alerts.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once "../includes/footer.php"; ?>