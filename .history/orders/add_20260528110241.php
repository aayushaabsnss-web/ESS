<?php
/**
 * orders/add.php — Create New Order (Presentation Layer)
 * Multi-product order form with live total calculator.
 * Uses Order class: validate() + create() which calls sp_createOrder.
 * Stock is NOT deducted on creation — only on completion.
 * Access: All authenticated users.
 */
$t = "New Order"; $a = "orders";
require_once "../includes/header.php";
require_once "../classes/Order.php";

// FIX: Order uses static methods only — do NOT instantiate with $conn.
$err = "";

if($_SERVER["REQUEST_METHOD"]==="POST"){
    $customer = trim($_POST["customer"] ?? "");
    $notes    = trim($_POST["notes"]    ?? "");
    $pids     = $_POST["pids"]  ?? [];
    $qtys     = $_POST["qtys"]  ?? [];

    // Build items array from parallel POST arrays
    $items = [];
    foreach($pids as $i => $pid){
        $pid = (int)$pid; $qty = (int)($qtys[$i] ?? 1);
        if($pid <= 0 || $qty <= 0) continue;
        // Verify product exists and has sufficient stock
        $p = mysqli_fetch_assoc(mysqli_query($conn,"SELECT id,price,name,quantity FROM products WHERE id=$pid AND is_active=1"));
        if(!$p){ $err = "Product not found."; break; }
        if($p["quantity"] < $qty){ $err = h($p["name"])." — only {$p["quantity"]} in stock."; break; }
        $items[] = ["product_id"=>$pid,"quantity"=>$qty,"price"=>(float)$p["price"]];
    }

    if(!$err){
        // FIX: validate() is static — call as Order::validate()
        $errors = Order::validate($customer, $items);
        if(!$errors){
            // FIX: create() is static — call as Order::create()
            $oid = Order::create($conn, $customer, $notes, $items, $_SESSION["uid"]);
            if($oid){ flash("success","Order created successfully."); header("Location: index.php"); exit; }
            else $err = "Failed to create order.";
        } else { $err = implode(" ", $errors); }
    }
}

// Get products in stock for the line item dropdowns
$products = []; $r = mysqli_query($conn,"SELECT id,name,price,quantity,category FROM products WHERE is_active=1 AND quantity>0 ORDER BY name");
while($p=mysqli_fetch_assoc($r)) $products[]=$p;
?>
<?php if($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
<div class="page-hdr"><a href="index.php" class="btn btn-outline btn-sm">&larr; Back</a><h1>New Order</h1></div>
<div class="card" style="max-width:800px">
  <div class="card-hdr"><span class="card-title">Order details</span></div>
  <div class="card-body">
  <form method="POST" id="order-form">
    <div class="form2">
      <div class="fg"><label>Customer name *</label><input type="text" name="customer" class="fc" value="<?= h($_POST["customer"]??"") ?>" required></div>
      <div class="fg"><label>Notes</label><input type="text" name="notes" class="fc" value="<?= h($_POST["notes"]??"") ?>" placeholder="Optional notes"></div>
    </div>
    <!-- Order line items -->
    <div style="border:1px solid var(--b);border-radius:var(--r);overflow:hidden;margin-bottom:14px">
      <div class="card-hdr" style="background:var(--bg3)"><span class="card-title">Products</span>
        <button type="button" class="btn btn-outline btn-sm" onclick="addRow()">+ Add row</button></div>
      <div id="rows" style="padding:12px;display:flex;flex-direction:column;gap:8px">
        <div class="oi-row" style="display:grid;grid-template-columns:1fr 100px 80px;gap:10px;align-items:center">
          <select name="pids[]" class="fc oi-pid" required onchange="updatePrice(this);calcTotal()">
            <option value="">Select product…</option>
            <?php foreach($products as $p): ?>
            <option value="<?= $p["id"] ?>" data-price="<?= $p["price"] ?>">
              <?= h($p["name"]) ?> — $<?= number_format($p["price"],2) ?> (<?= $p["quantity"] ?> left)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="qtys[]" class="fc oi-qty" min="1" value="1" required oninput="calcTotal()">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button>
        </div>
      </div>
    </div>
    <!-- Live order total -->
    <div style="text-align:right;font-size:14px;font-weight:600;margin-bottom:14px">Order total: <span id="order-total">$0.00</span></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <a href="index.php" class="btn btn-outline">Cancel</a>
      <button class="btn btn-primary">Create order &rarr;</button>
    </div>
  </form>
  </div>
</div>
<script>
function addRow(){
  const tmpl = document.querySelector(".oi-row").cloneNode(true);
  tmpl.querySelector(".oi-pid").value = "";
  tmpl.querySelector(".oi-qty").value = 1;
  tmpl.querySelector(".oi-pid").addEventListener("change", function(){ updatePrice(this); calcTotal(); });
  tmpl.querySelector(".oi-qty").addEventListener("input", calcTotal);
  document.getElementById("rows").appendChild(tmpl);
}
function removeRow(btn){ if(document.querySelectorAll(".oi-row").length>1) btn.closest(".oi-row").remove(); calcTotal(); }
function updatePrice(sel){ sel.dataset.price = sel.options[sel.selectedIndex].dataset.price || 0; }
function calcTotal(){
  let t = 0;
  document.querySelectorAll(".oi-row").forEach(row=>{
    t += parseInt(row.querySelector(".oi-qty")?.value||0) * parseFloat(row.querySelector(".oi-pid")?.dataset.price||0);
  });
  document.getElementById("order-total").textContent = "$"+t.toFixed(2);
}
document.querySelectorAll(".oi-pid").forEach(s=>{ s.addEventListener("change",function(){ updatePrice(this); calcTotal(); }); });
document.querySelectorAll(".oi-qty").forEach(i=>i.addEventListener("input",calcTotal));
</script>
<?php require_once "../includes/footer.php"; ?>
