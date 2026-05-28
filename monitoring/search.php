<?php
$t = "Search Alerts"; $a = "monitoring";
require_once "../includes/header.php";
require_once "../classes/Alert.php";

$q      = trim($_GET["q"]      ?? "");
$status = trim($_GET["status"] ?? "");

$rows = Alert::search($conn, $q ?: null, $status ?: null);
$n    = count($rows);
?>
<div class="page-hdr"><h1>Search Alerts</h1></div>
<div class="card" style="margin-bottom:16px">
  <div class="card-body">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="q" class="fc" placeholder="Search by product name…" value="<?= h($q) ?>" style="flex:1;min-width:180px">
    <select name="status" class="fc" style="width:160px">
      <option value="">All statuses</option>
      <option value="active"   <?= $status==="active"?"selected":"" ?>>Active</option>
      <option value="resolved" <?= $status==="resolved"?"selected":"" ?>>Resolved</option>
    </select>
    <button class="btn btn-primary">Search</button>
    <?php if($q||$status): ?><a href="search.php" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
  </div>
</div>
<div class="card">
  <div class="card-hdr"><span class="card-title"><?= $n ?> alert<?= $n!==1?"s":"" ?> found</span></div>
  <table class="tbl">
    <thead><tr><th>Product</th><th>Qty</th><th>Threshold</th><th>Status</th><th>Triggered</th><th>Resolved by</th><th>Actions</th></tr></thead>
    <tbody>
    <?php $e=true; foreach($rows as $al): $e=false; ?>
    <tr>
      <td class="fw"><?= h($al->getProductName()) ?></td>
      <td class="mono <?= $al->getQtyColor() ?>"><?= $al->getCurrentQty() ?></td>
      <td class="mono"><?= $al->getThreshold() ?></td>
      <td><span class="badge <?= $al->isActive()?"b-red":"b-green" ?>"><?= ucfirst($al->getStatus()) ?></span></td>
      <td class="muted" style="font-size:11px"><?= date("d M Y", strtotime($al->getAlertedAt())) ?></td>
      <td><?= h($al->getResolvedBy() ?: "—") ?></td>
      <td><div style="display:flex;gap:5px">
        <a href="view.php?id=<?= $al->getId() ?>" class="icon-btn" title="View">&#128065;</a>
        <?php if(isOwner()): ?>
        <a href="edit.php?id=<?= $al->getId() ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
        <?php endif; ?>
        <?php if(isOwner() && !$al->isActive()): ?>
        <a href="delete.php?id=<?= $al->getId() ?>" class="icon-btn del"
           onclick="return confirm('Delete this alert record?')" title="Delete">&#128465;</a>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endforeach; if($e): ?>
    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--t3)">No alerts found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once "../includes/footer.php"; ?>