<?php
/**
 * stock/edit_tx.php — Edit Transaction Notes (Presentation Layer)
 * Only notes can be edited. Quantity is locked for audit integrity.
 */
$t = "Edit Transaction"; $a = "stock";
require_once "../includes/header.php";
require_once "../classes/Stock.php"; // Middle layer — Stock class
requireOwner();

$id  = (int)($_GET["id"]  ?? 0);
$pid = (int)($_GET["pid"] ?? 0);
$tx  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT sm.*,p.name pname FROM stock_movements sm
     JOIN products p ON p.id=sm.product_id WHERE sm.id=$id"));
if(!$tx){ flash("error","Transaction not found."); header("Location: index.php"); exit; }

if($_SERVER["REQUEST_METHOD"]==="POST"){
    $notes = trim($_POST["notes"] ?? "");
    // Uses Stock class method — three-layer architecture
    Stock::updateNotes($conn, $id, $notes);
    flash("success","Transaction notes updated.");
    header("Location: view.php?id=$pid"); exit;
}
$tc=["IN"=>"b-green","OUT"=>"b-red","ADJUSTMENT"=>"b-amber"];
$sign = $tx["type"] === "OUT" ? "-" : "+";
?>
<div class="page-hdr">
  <a href="view.php?id=<?= $pid ?>" class="btn btn-outline btn-sm">&larr; Back</a>
  <h1>Edit Transaction</h1>
</div>
<div class="card" style="max-width:500px">
  <div class="card-hdr"><span class="card-title"><?= h($tx["pname"]) ?></span></div>
  <div class="card-body">
  <div style="background:var(--bg3);border-radius:8px;padding:12px 14px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
    <div><span style="color:var(--t2)">Type</span><div style="margin-top:2px"><span class="badge <?= $tc[$tx["type"]] ?>"><?= $tx["type"] ?></span></div></div>
    <div><span style="color:var(--t2)">Quantity</span><div class="mono fw" style="margin-top:2px"><?= $sign.$tx["quantity"] ?></div></div>
    <div><span style="color:var(--t2)">Date</span><div style="margin-top:2px;color:var(--t2)"><?= date("d M Y H:i",strtotime($tx["created_at"])) ?></div></div>
  </div>
  <div class="alert alert-info" style="font-size:11px;margin-bottom:14px">&#8505; Only notes can be edited. Quantity and type are locked for audit integrity.</div>
  <form method="POST">
    <div class="fg">
      <label>Notes</label>
      <input type="text" name="notes" class="fc" value="<?= h($_POST["notes"]??$tx["notes"]) ?>" placeholder="Optional notes">
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <a href="view.php?id=<?= $pid ?>" class="btn btn-outline">Cancel</a>
      <button class="btn btn-primary">Save notes &rarr;</button>
    </div>
  </form>
  </div>
</div>
<?php require_once "../includes/footer.php"; ?>