<?php
/**
 * stock/index.php — Stock Levels (Presentation Layer)
 * Fetches product stock data and accesses via object getters.
 */
$t = "Stock Management"; $a = "stock";
require_once "../includes/header.php";
require_once "../classes/Product.php"; 
include  "../includes/flash.php";

$q = trim($_GET["q"] ?? ""); $cat = $_GET["cat"] ?? ""; $status = $_GET["status"] ?? "";

// Get Product objects — then filter/display stock info from each object
$products = Product::search($conn, $q ?: null, $cat ?: null, $status ?: null);
$total    = count($products);
?>
<div class="page-hdr">
  <h1>Stock Management</h1>
  <a href="add.php" class="btn btn-primary">+ Add Stock Transaction</a>
</div>
<div class="card">
  <form method="GET" class="filter-bar">
    <input type="text" name="q" class="fc" placeholder="Search name or SKU..." value="<?= h($q) ?>" style="width:200px">
    <select name="cat" class="fc">
      <option value="">All categories</option>
      <?php foreach(["iPhone","Mac","iPad","Watch","Accessory"] as $c): ?>
      <option <?= $cat===$c?"selected":"" ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="fc">
      <option value="">All statuses</option>
      <option value="in"  <?= $status==="in" ?"selected":"" ?>>In Stock</option>
      <option value="low" <?= $status==="low"?"selected":"" ?>>Low Stock</option>
      <option value="out" <?= $status==="out"?"selected":"" ?>>Out of Stock</option>
    </select>
    <button class="btn btn-outline btn-sm">Filter</button>
    <?php if($q||$cat||$status): ?><a href="index.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
    <span style="margin-left:auto;font-size:12px;color:var(--t2)"><?= $total ?> product<?= $total!==1?"s":"" ?></span>
  </form>
  <table class="tbl">
    <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Quantity</th><th>Min level</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($products)): ?>
    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--t3)">No products found.</td></tr>
    <?php else: ?>
    <?php foreach($products as $product): // Each $product is a Product object ?>
    <tr>
      <td class="fw"><?= h($product->getName()) ?></td>
      <td class="mono muted"><?= h($product->getSku()) ?></td>
      <td><span class="badge b-blue"><?= h($product->getCategory()) ?></span></td>
      <td class="mono fw" style="color:<?= ["In Stock"=>"var(--green)","Low Stock"=>"var(--amber)","Out of Stock"=>"var(--red)"][$product->getStockStatus()] ?>"><?= $product->getQuantity() ?></td>
      <td class="mono"><?= $product->getMinQty() ?></td>
      <td><span class="badge <?= $product->getStockBadge() ?>"><?= $product->getStockStatus() ?></span></td>
      <td><div style="display:flex;gap:5px">
        <a href="view.php?id=<?= $product->getId() ?>" class="icon-btn" title="View">&#128065;</a>
        <a href="edit.php?id=<?= $product->getId() ?>" class="icon-btn" title="Edit">&#9998;</a>
        <?php if(isOwner()): ?>
        <a href="delete.php?id=<?= $product->getId() ?>" class="icon-btn del" title="Delete"
           onclick="return confirm('Delete all stock for <?= h(addslashes($product->getName())) ?>?')">&#128465;</a>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once "../includes/footer.php"; ?>
