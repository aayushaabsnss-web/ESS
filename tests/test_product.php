<?php
/**
 * test_product.php — Product Management Test Suite
 * Author:    Mahendra Singh
 * Component: Product Management
 * Module:    CTEC2713 Agile Development
 * Run at:    http://localhost/ESS/tests/test_product.php
 *
 * Tests based on actual Product.php class:
 *   Group 1 — Getter methods
 *   Group 2 — Product::validate() — name (required)
 *   Group 3 — Product::validate() — price (> 0)
 *   Group 4 — Product::validate() — category ENUM
 *   Group 5 — getStockStatus() boundary (qty vs min_qty)
 *   Group 6 — getStockBadge() CSS classes
 *   Group 7 — getFormattedPrice() currency formatting
 */
require_once "../classes/Product.php";

// ── Test runner ───────────────────────────────────────────
$tests = [];
$pass  = 0;
$fail  = 0;
$group = '';

function grp(string $name): void {
    global $group;
    $group = $name;
}
function t(string $name, $actual, $expected, string $desc = ''): void {
    global $tests, $pass, $fail, $group;
    $ok = $actual === $expected;
    $ok ? $pass++ : $fail++;
    $tests[] = [
        'g'  => $group,
        'n'  => $name,
        'ok' => $ok,
        'a'  => var_export($actual, true),
        'e'  => var_export($expected, true),
        'd'  => $desc,
    ];
}

// ── Helper: make a Product object from mock data ──────────
$mkP = function(int $qty, int $min, float $price = 999.00, string $cat = 'iPhone') {
    return new Product([
        'id'          => 1,
        'name'        => 'iPhone 16 Pro',
        'sku'         => 'IPH-16-PRO',
        'category'    => $cat,
        'price'       => $price,
        'quantity'    => $qty,
        'min_qty'     => $min,
        'supplier'    => 'Apple Inc.',
        'description' => 'Latest iPhone model with A18 Pro chip.',
        'created_at'  => '2026-05-01 09:00:00',
    ]);
};

// ── Helper: valid product data for validate() tests ───────
$validData = [
    'name'     => 'iPhone 16 Pro',
    'sku'      => 'IPH-16-PRO',
    'category' => 'iPhone',
    'price'    => '999.00',
];

// ════════════════════════════════════════════════════════
//  GROUP 1 — Getter Methods
// ════════════════════════════════════════════════════════
grp('GROUP 1 — Getter Methods  (Product class — all private properties)');

$p = $mkP(10, 5);
t('getId() returns int 1',
    $p->getId(), 1, 'Primary key getter');

t('getName() returns product name',
    $p->getName(), 'iPhone 16 Pro', 'Name getter');

t('getSku() returns SKU string',
    $p->getSku(), 'IPH-16-PRO', 'SKU getter');

t('getCategory() returns category',
    $p->getCategory(), 'iPhone', 'Category getter');

t('getPrice() returns float',
    $p->getPrice(), 999.00, 'Price getter');

t('getQuantity() returns int 10',
    $p->getQuantity(), 10, 'Quantity getter');

t('getMinQty() returns int 5',
    $p->getMinQty(), 5, 'Min qty getter');

t('getSupplier() returns string',
    $p->getSupplier(), 'Apple Inc.', 'Supplier getter');

t('getDescription() returns string',
    $p->getDescription(), 'Latest iPhone model with A18 Pro chip.', 'Description getter');

t('getCreatedAt() returns date string',
    $p->getCreatedAt(), '2026-05-01 09:00:00', 'Created at getter');

// ════════════════════════════════════════════════════════
//  GROUP 2 — Product::validate() — name field
//  Rule: required — must not be empty after trim()
// ════════════════════════════════════════════════════════
grp('GROUP 2 — Product name  Product::validate()  [Required — must not be empty]');

$d = fn($name) => array_merge($validData, ['name' => $name]);

t('Extreme Min — empty ""',
    !empty(Product::validate($d(''))),
    true, 'Empty name → "Product name is required."');

t('Min-1 — whitespace only "   "',
    !empty(Product::validate($d('   '))),
    true, 'trim() → empty → required error');

t('Min Boundary — 1 character "A"',
    empty(Product::validate($d('A'))),
    true, '1 non-empty char → passes required check');

t('Min+1 — 2 characters "AB"',
    empty(Product::validate($d('AB'))),
    true, '2 chars → passes');

t('Mid — "iPhone 16 Pro"',
    empty(Product::validate($d('iPhone 16 Pro'))),
    true, 'Normal product name → passes');

t('Max-1 — 99 characters',
    empty(Product::validate($d(str_repeat('A', 99)))),
    true, '99 chars → passes');

t('Max Boundary — 100 characters',
    empty(Product::validate($d(str_repeat('B', 100)))),
    true, '100 chars → passes');

t('Max+1 — 101 characters',
    empty(Product::validate($d(str_repeat('C', 101)))),
    true, 'validate() has no max-length rule for name → still passes');

t('Extreme Max — 300 characters',
    empty(Product::validate($d(str_repeat('D', 300)))),
    true, 'No max-length rule in validate() — DB/column would reject');

t('Invalid data type — null treated as empty',
    !empty(Product::validate(array_merge($validData, ['name' => null]))),
    true, 'null → trim() → empty → required error');

t('Other — numeric name "12345"',
    empty(Product::validate($d('12345'))),
    true, 'Numeric string is a valid non-empty name');

// ════════════════════════════════════════════════════════
//  GROUP 3 — Product::validate() — price field
//  Rule: must be numeric and greater than 0
// ════════════════════════════════════════════════════════
grp('GROUP 3 — Product price  Product::validate()  [Required — must be > £0.00]');

$dp = fn($price) => array_merge($validData, ['price' => $price]);

t('Extreme Min — price = 0',
    !empty(Product::validate($dp(0))),
    true, '0 is not > 0 → "Price must be greater than $0."');

t('Min-1 — price = 0',
    !empty(Product::validate($dp(0))),
    true, '0 below minimum → fail');

t('Min Boundary — price = 0.01',
    empty(Product::validate($dp(0.01))),
    true, '0.01 > 0 → minimum valid price → pass');

t('Min+1 — price = 0.02',
    empty(Product::validate($dp(0.02))),
    true, '0.02 > 0 → pass');

t('Mid — price = 499.99',
    empty(Product::validate($dp(499.99))),
    true, 'Mid-range price → pass');

t('Max-1 — price = 9999.99',
    empty(Product::validate($dp(9999.99))),
    true, 'Large price → pass');

t('Max Boundary — price = 9999.99',
    empty(Product::validate($dp(9999.99))),
    true, 'No upper limit in validate() → pass');

t('Max+1 — price = 99999.99',
    empty(Product::validate($dp(99999.99))),
    true, 'No upper price limit → pass');

t('Extreme Max — price = 999999',
    empty(Product::validate($dp(999999))),
    true, 'Very large price → passes validate()');

t('Invalid data type — price = "free"',
    !empty(Product::validate($dp('free'))),
    true, 'Non-numeric string → (float)"free" = 0 → not > 0 → fail');

t('Other — price = -1 (negative)',
    !empty(Product::validate($dp(-1))),
    true, '-1 is not > 0 → fail');

// ════════════════════════════════════════════════════════
//  GROUP 4 — Product::validate() — category ENUM
//  Valid values: iPhone, Mac, iPad, Watch, Accessory
// ════════════════════════════════════════════════════════
grp('GROUP 4 — category ENUM  Product::validate()  [Valid: iPhone|Mac|iPad|Watch|Accessory]');

$dc = fn($cat) => array_merge($validData, ['category' => $cat]);

t('Valid — "iPhone"',
    empty(Product::validate($dc('iPhone'))),
    true, 'iPhone is a valid category');

t('Valid — "Mac"',
    empty(Product::validate($dc('Mac'))),
    true, 'Mac is a valid category');

t('Valid — "iPad"',
    empty(Product::validate($dc('iPad'))),
    true, 'iPad is a valid category');

t('Valid — "Watch"',
    empty(Product::validate($dc('Watch'))),
    true, 'Watch is a valid category');

t('Valid — "Accessory"',
    empty(Product::validate($dc('Accessory'))),
    true, 'Accessory is a valid category');

t('Invalid — "Laptop"',
    !empty(Product::validate($dc('Laptop'))),
    true, 'Laptop not in category list → fail');

t('Invalid — "iphone" (lowercase)',
    !empty(Product::validate($dc('iphone'))),
    true, 'Category check is case-sensitive → fail');

t('Invalid — "" (empty)',
    !empty(Product::validate($dc(''))),
    true, 'Empty category → fail');

t('Invalid — "TV"',
    !empty(Product::validate($dc('TV'))),
    true, 'TV not in category list → fail');

t('Other — null category',
    !empty(Product::validate($dc(null))),
    true, 'null → not in_array → fail');

// ════════════════════════════════════════════════════════
//  GROUP 5 — getStockStatus() boundary tests
//  Out of Stock: qty = 0
//  Low Stock:    0 < qty <= min_qty
//  In Stock:     qty > min_qty
// ════════════════════════════════════════════════════════
grp('GROUP 5 — getStockStatus()  [qty=0→Out | qty<=min→Low | qty>min→In Stock]');

t('Extreme Min — qty=0 → Out of Stock',
    $mkP(0, 5)->getStockStatus(), 'Out of Stock', 'Zero quantity = out of stock');

t('Min-1 — qty=0 (min_qty boundary is qty=1)',
    $mkP(0, 5)->getStockStatus(), 'Out of Stock', 'qty 0 always out of stock');

t('Min Boundary — qty=1 min=5 → Low Stock',
    $mkP(1, 5)->getStockStatus(), 'Low Stock', '1 unit below threshold of 5 = low stock');

t('Min+1 — qty=2 min=5 → Low Stock',
    $mkP(2, 5)->getStockStatus(), 'Low Stock', '2 units still below min of 5');

t('Below threshold — qty=5 min=5 → Low Stock',
    $mkP(5, 5)->getStockStatus(), 'Low Stock', 'Equal to min_qty = still Low Stock');

t('Above threshold — qty=6 min=5 → In Stock',
    $mkP(6, 5)->getStockStatus(), 'In Stock', 'One above min_qty = In Stock');

t('Max-1 — qty=998 min=5 → In Stock',
    $mkP(998, 5)->getStockStatus(), 'In Stock', 'Large quantity = In Stock');

t('Max Boundary — qty=999 min=5 → In Stock',
    $mkP(999, 5)->getStockStatus(), 'In Stock', '999 units = In Stock');

t('Max+1 — qty=1000 min=5 → In Stock',
    $mkP(1000, 5)->getStockStatus(), 'In Stock', '1000 units = In Stock');

t('Mid — qty=50 min=5 → In Stock',
    $mkP(50, 5)->getStockStatus(), 'In Stock', '50 units well above min = In Stock');

t('Other — qty=0 min=0 → Out of Stock',
    $mkP(0, 0)->getStockStatus(), 'Out of Stock', 'Zero qty always out regardless of min');

// ════════════════════════════════════════════════════════
//  GROUP 6 — getStockBadge() CSS badge classes
// ════════════════════════════════════════════════════════
grp('GROUP 6 — getStockBadge()  [Out of Stock→b-red | Low Stock→b-amber | In Stock→b-green]');

t('qty=0 → b-red',
    $mkP(0, 5)->getStockBadge(), 'b-red', 'Out of Stock = red badge');

t('qty=3 min=5 → b-amber',
    $mkP(3, 5)->getStockBadge(), 'b-amber', 'Low Stock = amber badge');

t('qty=10 min=5 → b-green',
    $mkP(10, 5)->getStockBadge(), 'b-green', 'In Stock = green badge');

t('qty=1 min=1 → b-amber',
    $mkP(1, 1)->getStockBadge(), 'b-amber', 'Equal to min = Low Stock = amber');

t('qty=2 min=1 → b-green',
    $mkP(2, 1)->getStockBadge(), 'b-green', 'Above min = In Stock = green');

// ════════════════════════════════════════════════════════
//  GROUP 7 — getFormattedPrice() currency formatting
// ════════════════════════════════════════════════════════
grp('GROUP 7 — getFormattedPrice()  [$X,XXX.XX currency format]');

t('price=0.99 → "$0.99"',
    $mkP(1, 1, 0.99)->getFormattedPrice(), '$0.99', 'Sub-dollar price');

t('price=9.99 → "$9.99"',
    $mkP(1, 1, 9.99)->getFormattedPrice(), '$9.99', 'Single digit price');

t('price=99.00 → "$99.00"',
    $mkP(1, 1, 99.00)->getFormattedPrice(), '$99.00', 'Double digit price');

t('price=999.00 → "$999.00"',
    $mkP(1, 1, 999.00)->getFormattedPrice(), '$999.00', 'Standard Apple price');

t('price=1999.00 → "$1,999.00"',
    $mkP(1, 1, 1999.00)->getFormattedPrice(), '$1,999.00', 'Thousands separator');

t('price=9999.99 → "$9,999.99"',
    $mkP(1, 1, 9999.99)->getFormattedPrice(), '$9,999.99', 'Four digit price');

t('price=0.00 → "$0.00"',
    $mkP(1, 1, 0.00)->getFormattedPrice(), '$0.00', 'Zero price formats correctly');

// ════════════════════════════════════════════════════════
//  HTML OUTPUT
// ════════════════════════════════════════════════════════
$total = $pass + $fail;
$rate  = $total > 0 ? round($pass / $total * 100) : 0;
$groups = [];
foreach ($tests as $t2) { $groups[$t2['g']][] = $t2; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Management Tests — Mahendra Singh</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:13px;background:#F0F4F8;padding:16px;color:#1B2631}
.wrap{max-width:1140px;margin:0 auto}
.hdr{background:linear-gradient(135deg,#1A5276,#2E86C1);color:#fff;padding:18px 22px;border-radius:8px;margin-bottom:14px}
.hdr h1{font-size:18px;margin-bottom:5px}
.hdr p{font-size:12px;opacity:.85;line-height:1.8}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px}
.card{background:#fff;border-radius:8px;padding:14px;text-align:center;border:1px solid #DDE2EA}
.card .num{font-size:30px;font-weight:700;font-family:monospace}
.card .lbl{font-size:11px;color:#666;margin-top:3px}
.c-blue .num{color:#1A5276}.c-green .num{color:#085041}.c-red .num{color:#791F1F}
.bar-wrap{height:8px;background:#E0E6EE;border-radius:20px;overflow:hidden;margin-bottom:14px}
.bar-fill{height:8px;border-radius:20px;width:<?= $rate ?>%;
  background:<?= $fail===0?'linear-gradient(90deg,#1D9E75,#27AE60)':'linear-gradient(90deg,#C0392B,#E74C3C)' ?>}
.grp{margin-bottom:12px;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.grp-hd{background:#1A5276;color:#fff;padding:9px 14px;font-size:12px;font-weight:700;
  display:flex;justify-content:space-between;align-items:center}
.pill{font-size:10px;padding:2px 9px;border-radius:12px;font-weight:600;background:rgba(255,255,255,.2)}
.pill-fail{background:#C0392B;margin-left:5px}
table{width:100%;border-collapse:collapse;background:#fff}
th{background:#EBF5FB;padding:8px 11px;text-align:left;font-size:11px;color:#1A5276;
  font-weight:700;border-bottom:2px solid #2471A3}
td{padding:7px 11px;font-size:12px;border-bottom:1px solid #EEE;vertical-align:top;line-height:1.5}
tr:nth-child(even){background:#FAFBFC}
tr:last-child td{border-bottom:none}
.bp{background:#E1F5EE;color:#085041;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600}
.bf{background:#FCEBEB;color:#791F1F;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600}
.mono{font-family:monospace;font-size:11px;background:#F0F0F0;padding:1px 5px;border-radius:3px}
.desc{color:#666;font-style:italic}
.foot{text-align:center;margin-top:14px;font-size:11px;color:#888;padding:10px}
</style>
</head>
<body>
<div class="wrap">
<div class="hdr">
  <h1>&#10003; ESS — Product Management Test Suite</h1>
  <p>
    <strong>Mahendra Singh</strong>
    &nbsp;|&nbsp; CTEC2713 Agile Development
    &nbsp;|&nbsp; test_product.php
    &nbsp;|&nbsp; Run: <?= date('d M Y H:i:s') ?>
    &nbsp;|&nbsp; PHP <?= PHP_VERSION ?>
  </p>
  <p style="margin-top:6px;opacity:.75;font-size:11px">
    Covers: name (required) &nbsp;|&nbsp;
    price (> 0) &nbsp;|&nbsp;
    category ENUM &nbsp;|&nbsp;
    getStockStatus() qty vs min_qty &nbsp;|&nbsp;
    getStockBadge() &nbsp;|&nbsp;
    getFormattedPrice()
  </p>
</div>
<div class="cards">
  <div class="card c-blue"><div class="num"><?= $total ?></div><div class="lbl">Total Tests</div></div>
  <div class="card c-green"><div class="num"><?= $pass ?></div><div class="lbl">Passed</div></div>
  <div class="card c-red"><div class="num"><?= $fail ?></div><div class="lbl">Failed</div></div>
  <div class="card <?= $fail===0?'c-green':'c-red' ?>">
    <div class="num"><?= $rate ?>%</div><div class="lbl">Pass Rate</div>
  </div>
</div>
<div class="bar-wrap"><div class="bar-fill"></div></div>
<?php foreach ($groups as $gname => $gt):
  $gp = count(array_filter($gt, fn($x) => $x['ok']));
  $gf = count($gt) - $gp; ?>
<div class="grp">
  <div class="grp-hd">
    <span><?= htmlspecialchars($gname) ?></span>
    <span>
      <span class="pill"><?= $gp ?>/<?= count($gt) ?> PASS</span>
      <?php if ($gf > 0): ?>
      <span class="pill pill-fail"><?= $gf ?> FAIL</span>
      <?php endif ?>
    </span>
  </div>
  <table>
    <thead><tr>
      <th style="width:28px">#</th>
      <th style="width:36%">Test</th>
      <th style="width:68px">Result</th>
      <th style="width:12%">Expected</th>
      <th style="width:12%">Actual</th>
      <th>Description</th>
    </tr></thead>
    <tbody>
    <?php foreach ($gt as $i => $row): ?>
    <tr>
      <td style="color:#999;font-size:11px"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($row['n']) ?></td>
      <td><?= $row['ok']
            ? '<span class="bp">&#10003; PASS</span>'
            : '<span class="bf">&#10007; FAIL</span>' ?></td>
      <td><span class="mono"><?= htmlspecialchars($row['e']) ?></span></td>
      <td><span class="mono"><?= htmlspecialchars($row['a']) ?></span></td>
      <td class="desc"><?= htmlspecialchars($row['d']) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endforeach ?>
<div class="foot">
  ElectroStock Solutions (ESS) &nbsp;|&nbsp;
  Product Management — Mahendra Singh &nbsp;|&nbsp;
  <?= $pass ?> passed / <?= $fail ?> failed / <?= $total ?> total
</div>
</div>
</body>
</html>