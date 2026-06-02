<?php
/**
 * test_order.php — Order Management Test Suite
 * Author:    Aayusha Katuwal (P2893828)
 * Component: Order Management
 * Module:    CTEC2713 Agile Development
 * Run at:    http://localhost/ESS/tests/test_order.php
 */
require_once "../classes/Order.php";

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
        'g' => $group,
        'n' => $name,
        'ok'=> $ok,
        'a' => var_export($actual, true),
        'e' => var_export($expected, true),
        'd' => $desc,
    ];
}

// ── Helper: one valid item ───────────────────────────────
$item = [['product_id' => 1, 'quantity' => 1, 'price' => 999.00]];
$cust = 'John Smith';

// ════════════════════════════════════════════════════════
// GROUP 1 — Customer Name   min:2  max:120
// ════════════════════════════════════════════════════════
grp('GROUP 1 — Customer Name  Order::validate()  [Min: 2 | Max: 120 characters]');

t('Extreme Min — empty ""',
    !empty(Order::validate('', $item)),
    true, 'Empty string → required error');

t('Min-1 — 1 char "A"',
    !empty(Order::validate('A', $item)),
    true, '1 char < 2 → must fail');

t('Min Boundary — 2 chars "Jo"',
    empty(Order::validate('Jo', $item)),
    true, '2 chars = min → must pass');

t('Min+1 — 3 chars "Tim"',
    empty(Order::validate('Tim', $item)),
    true, '3 chars > min → must pass');

t('Mid — 28 chars "Apple Business Solutions Ltd"',
    empty(Order::validate('Apple Business Solutions Ltd', $item)),
    true, '28 chars in range → must pass');

t('Max-1 — 119 chars',
    empty(Order::validate(str_repeat('A', 119), $item)),
    true, '119 < 120 → must pass');

t('Max Boundary — 120 chars',
    empty(Order::validate(str_repeat('B', 120), $item)),
    true, '120 = max → must pass');

t('Max+1 — 121 chars',
    !empty(Order::validate(str_repeat('C', 121), $item)),
    true, '121 > 120 → must fail');

t('Extreme Max — 300 chars',
    !empty(Order::validate(str_repeat('D', 300), $item)),
    true, '300 >> 120 → must fail');

t('Invalid data type — numbers only "12345"',
    empty(Order::validate('12345', $item)),
    true, 'Numeric string is a valid name → pass');

t('Other — whitespace only "   "',
    !empty(Order::validate('   ', $item)),
    true, 'Trim to empty → must fail');

// ════════════════════════════════════════════════════════
// GROUP 2 — Item Quantity   min:1  max:999
// ════════════════════════════════════════════════════════
grp('GROUP 2 — Item Quantity  Order::validate()  [Min: 1 | Max: 999]');

t('Extreme Min — qty 0',
    !empty(Order::validate($cust, [['product_id'=>1,'quantity'=>0,'price'=>9.99]])),
    true, '0 < 1 → must fail');

t('Min-1 — qty 0  (min is 1)',
    !empty(Order::validate($cust, [['product_id'=>1,'quantity'=>0,'price'=>9.99]])),
    true, 'Below minimum → fail');

t('Min Boundary — qty 1',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>1,'price'=>9.99]])),
    true, '1 = min → must pass');

t('Min+1 — qty 2',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>2,'price'=>9.99]])),
    true, '2 > min → must pass');

t('Mid — qty 500',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>500,'price'=>9.99]])),
    true, '500 in range → must pass');

t('Max-1 — qty 998',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>998,'price'=>9.99]])),
    true, '998 < 999 → must pass');

t('Max Boundary — qty 999',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>999,'price'=>9.99]])),
    true, '999 = max → must pass');

t('Max+1 — qty 1000',
    !empty(Order::validate($cust, [['product_id'=>1,'quantity'=>1000,'price'=>9.99]])),
    true, '1000 > 999 → must fail');

t('Extreme Max — qty 9999',
    !empty(Order::validate($cust, [['product_id'=>1,'quantity'=>9999,'price'=>9.99]])),
    true, '9999 >> max → must fail');

t('Invalid — qty -1 (negative)',
    !empty(Order::validate($cust, [['product_id'=>1,'quantity'=>-1,'price'=>9.99]])),
    true, 'Negative quantity < 1 → must fail');

t('Other — qty 1 with zero price',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>1,'price'=>0.00]])),
    true, 'Price not validated by Order::validate() → pass');

// ════════════════════════════════════════════════════════
// GROUP 3 — Number of Items   min:1  max:20
// ════════════════════════════════════════════════════════
grp('GROUP 3 — Number of Items  Order::validate()  [Min: 1 item | Max: 20 items]');

t('Extreme Min — 0 items (empty array)',
    !empty(Order::validate($cust, [])),
    true, 'No items → "Please add at least one product."');

t('Min-1 — 0 items  (min is 1)',
    !empty(Order::validate($cust, [])),
    true, '0 below minimum → fail');

t('Min Boundary — 1 item',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>1,'price'=>9.99]])),
    true, '1 item = min → pass');

$two = [
    ['product_id'=>1,'quantity'=>1,'price'=>9.99],
    ['product_id'=>2,'quantity'=>1,'price'=>5.99],
];
t('Min+1 — 2 items',
    empty(Order::validate($cust, $two)),
    true, '2 items > min → pass');

$ten = [];
for ($i = 1; $i <= 10; $i++) {
    $ten[] = ['product_id' => $i, 'quantity' => 1, 'price' => 9.99];
}
t('Mid — 10 items',
    empty(Order::validate($cust, $ten)),
    true, '10 items in range → pass');

$nineteen = [];
for ($i = 1; $i <= 19; $i++) {
    $nineteen[] = ['product_id' => $i, 'quantity' => 1, 'price' => 9.99];
}
t('Max-1 — 19 items',
    empty(Order::validate($cust, $nineteen)),
    true, '19 < 20 → pass');

$twenty = [];
for ($i = 1; $i <= 20; $i++) {
    $twenty[] = ['product_id' => $i, 'quantity' => 1, 'price' => 9.99];
}
t('Max Boundary — 20 items',
    empty(Order::validate($cust, $twenty)),
    true, '20 = max → pass');

$twentyone = [];
for ($i = 1; $i <= 21; $i++) {
    $twentyone[] = ['product_id' => $i, 'quantity' => 1, 'price' => 9.99];
}
t('Max+1 — 21 items',
    !empty(Order::validate($cust, $twentyone)),
    true, '21 > 20 → fail');

$fifty = [];
for ($i = 1; $i <= 50; $i++) {
    $fifty[] = ['product_id' => $i, 'quantity' => 1, 'price' => 9.99];
}
t('Extreme Max — 50 items',
    !empty(Order::validate($cust, $fifty)),
    true, '50 >> 20 → fail');

$dup = [
    ['product_id' => 1, 'quantity' => 1, 'price' => 9.99],
    ['product_id' => 1, 'quantity' => 2, 'price' => 9.99],
];
t('Invalid — duplicate product_id in same order',
    !empty(Order::validate($cust, $dup)),
    true, 'Two rows with same product_id → "Duplicate products detected."');

t('Other — 1 item with null price',
    empty(Order::validate($cust, [['product_id'=>1,'quantity'=>1,'price'=>null]])),
    true, 'Price not validated here → pass');

// ════════════════════════════════════════════════════════
// GROUP 4 — isPaid() Boolean   TINYINT(1)
// ════════════════════════════════════════════════════════
grp('GROUP 4 — isPaid() Boolean Method  [TINYINT(1): 0 = Unpaid | 1 = Paid]');

$rowPaid = [
    'id' => 1, 'order_number' => 'ORD-TEST', 'customer' => 'Test Customer',
    'status' => 'pending', 'total' => 999.00, 'is_paid' => 1, 'notes' => '',
    'created_by_name' => 'Admin', 'created_at' => '2026-05-01 10:00:00', 'item_count' => 1,
];
$rowUnpaid = array_merge($rowPaid, ['is_paid' => 0]);

$paid   = new Order($rowPaid);
$unpaid = new Order($rowUnpaid);

t('isPaid() — is_paid=1 → true',
    $paid->isPaid(), true, 'TINYINT(1) value 1 → PHP bool true');

t('getIsPaid() — is_paid=1 → returns int 1',
    $paid->getIsPaid(), 1, 'Raw getter returns the int stored in DB');

t('getPaidLabel() — is_paid=1 → "Paid"',
    $paid->getPaidLabel(), 'Paid', 'Label shown when paid');

t('getPaidBadge() — is_paid=1 → "b-green"',
    $paid->getPaidBadge(), 'b-green', 'Green CSS badge class when paid');

t('isPaid() — is_paid=0 → false',
    $unpaid->isPaid(), false, 'TINYINT(1) value 0 → PHP bool false');

t('getIsPaid() — is_paid=0 → returns int 0',
    $unpaid->getIsPaid(), 0, 'Raw getter returns int 0');

t('getPaidLabel() — is_paid=0 → "Unpaid"',
    $unpaid->getPaidLabel(), 'Unpaid', 'Label shown when unpaid');

t('getPaidBadge() — is_paid=0 → "b-amber"',
    $unpaid->getPaidBadge(), 'b-amber', 'Amber CSS badge class when unpaid');

$noKey = new Order([
    'id' => 1, 'order_number' => 'X', 'customer' => 'T',
    'status' => 'pending', 'total' => 0, 'notes' => '',
    'created_by_name' => '', 'created_at' => '', 'item_count' => 0,
]);
t('isPaid() — no is_paid key → defaults false',
    $noKey->isPaid(), false, 'Constructor ?? 0 fallback = false');

// ════════════════════════════════════════════════════════
// GROUP 5 — isEditable() & isCancellable()
// ════════════════════════════════════════════════════════
grp('GROUP 5 — isEditable() & isCancellable() Business Logic');

$mkOrder = function(string $status) {
    return new Order([
        'id' => 1, 'order_number' => 'X', 'customer' => 'T',
        'status' => $status, 'total' => 0, 'is_paid' => 0, 'notes' => '',
        'created_by_name' => '', 'created_at' => '', 'item_count' => 0,
    ]);
};

t('isEditable() — status=pending → true',
    $mkOrder('pending')->isEditable(), true, 'Pending orders can be edited');

t('isEditable() — status=processing → true',
    $mkOrder('processing')->isEditable(), true, 'Processing orders can be edited');

t('isEditable() — status=completed → false',
    $mkOrder('completed')->isEditable(), false, 'Completed orders are locked');

t('isEditable() — status=cancelled → false',
    $mkOrder('cancelled')->isEditable(), false, 'Cancelled orders are locked');

t('isCancellable() — status=pending → true',
    $mkOrder('pending')->isCancellable(), true, 'Pending can be cancelled');

t('isCancellable() — status=processing → true',
    $mkOrder('processing')->isCancellable(), true, 'Processing can be cancelled');

t('isCancellable() — status=cancelled → true',
    $mkOrder('cancelled')->isCancellable(), true, 'Already cancelled = re-cancellable');

t('isCancellable() — status=completed → false',
    $mkOrder('completed')->isCancellable(), false, 'Completed cannot be cancelled');

// ════════════════════════════════════════════════════════
// GROUP 6 — Formatting methods
// ════════════════════════════════════════════════════════
grp('GROUP 6 — Formatting & Badge Methods');

$ord = new Order([
    'id' => 5, 'order_number' => 'ORD-20260501-AB12',
    'customer' => 'Apple Store', 'status' => 'pending',
    'total' => 1999.00, 'is_paid' => 0, 'notes' => '',
    'created_by_name' => 'Admin', 'created_at' => '2026-05-01 10:00:00', 'item_count' => 3,
]);

t('getFormattedTotal() — 1999.00 → "$1,999.00"',
    $ord->getFormattedTotal(), '$1,999.00', 'Currency formatted with comma separator');

t('getStatusBadge() — pending → b-amber',
    $ord->getStatusBadge(), 'b-amber', 'Pending uses amber badge class');

t('getStatusBadge() — completed → b-green',
    $mkOrder('completed')->getStatusBadge(), 'b-green', 'Completed uses green badge class');

t('getStatusBadge() — cancelled → b-gray',
    $mkOrder('cancelled')->getStatusBadge(), 'b-gray', 'Cancelled uses gray badge class');

$noDate = new Order([
    'id' => 1, 'order_number' => 'X', 'customer' => 'T',
    'status' => 'pending', 'total' => 0, 'is_paid' => 0, 'notes' => '',
    'created_by_name' => '', 'created_at' => '', 'item_count' => 0,
]);
t('getFormattedDate() — empty date → "—"',
    $noDate->getFormattedDate(), '—', 'Empty date returns em dash');

// ════════════════════════════════════════════════════════
// HTML output
// ════════════════════════════════════════════════════════
include "test_runner.html.php";