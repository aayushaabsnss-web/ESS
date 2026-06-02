<?php
/**
 * test_stock.php — Stock Component Test Suite
 * Author:    Dikshya Kafle
 * Component: Stock Management
 * Module:    CTEC2713 Agile Development
 * Tests:     Stock::validate() boundaries, isAvailable() boolean, getSignedQuantity(), getTypeBadge()
 */
require_once "classes/Stock.php";
require_once "classes/StockMovement.php";

$tests=[];$pass=0;$fail=0;$group='';
function grp($n){global $group;$group=$n;}
function t($name,$actual,$expected,$desc=''){
    global $tests,$pass,$fail,$group;
    $ok=$actual===$expected;$ok?$pass++:$fail++;
    $tests[]=['g'=>$group,'n'=>$name,'ok'=>$ok,'a'=>var_export($actual,true),'e'=>var_export($expected,true),'d'=>$desc];
}

// ── GROUP 1: Product ID (required field) ─────────────────
grp('GROUP 1 — Product ID  Stock::validate()  [Required: must not be empty]');
t('Extreme Min — empty product_id ""',!empty(Stock::validate(['product_id'=>'','type'=>'IN','quantity'=>5])),true,'No product selected → must fail');
t('Min (valid) — product_id = 1',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>5])),true,'Valid product id → must pass');
t('Mid — product_id = 50',empty(Stock::validate(['product_id'=>50,'type'=>'IN','quantity'=>5])),true,'Mid range product id → must pass');
t('Large ID — product_id = 9999',empty(Stock::validate(['product_id'=>9999,'type'=>'IN','quantity'=>5])),true,'Large product id → must pass');
t('Invalid — product_id = 0',!empty(Stock::validate(['product_id'=>0,'type'=>'IN','quantity'=>5])),true,'Zero product id = empty → must fail');
t('Invalid type — product_id = "abc"',!empty(Stock::validate(['product_id'=>'','type'=>'IN','quantity'=>5])),true,'String that is empty → must fail');

// ── GROUP 2: Transaction Type (IN/OUT/ADJUSTMENT) ─────────
grp('GROUP 2 — Transaction Type  Stock::validate()  [Valid: IN | OUT | ADJUSTMENT]');
t('Valid type — "IN"',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>5])),true,'IN is a valid type → pass');
t('Valid type — "OUT"',empty(Stock::validate(['product_id'=>1,'type'=>'OUT','quantity'=>5])),true,'OUT is a valid type → pass');
t('Valid type — "ADJUSTMENT" as owner',empty(Stock::validate(['product_id'=>1,'type'=>'ADJUSTMENT','quantity'=>5],true)),true,'ADJUSTMENT + owner → pass');
t('Invalid — ADJUSTMENT without owner',!empty(Stock::validate(['product_id'=>1,'type'=>'ADJUSTMENT','quantity'=>5],false)),true,'ADJUSTMENT requires owner → fail');
t('Invalid type — "TRANSFER"',!empty(Stock::validate(['product_id'=>1,'type'=>'TRANSFER','quantity'=>5])),true,'TRANSFER not valid → fail');
t('Invalid type — empty ""',!empty(Stock::validate(['product_id'=>1,'type'=>'','quantity'=>5])),true,'Empty type → fail');
t('Invalid type — lowercase "in"',!empty(Stock::validate(['product_id'=>1,'type'=>'in','quantity'=>5])),true,'Lowercase not valid → fail');
t('Invalid type — null',!empty(Stock::validate(['product_id'=>1,'type'=>null,'quantity'=>5])),true,'Null type → fail');

// ── GROUP 3: Quantity (min:1, no defined max) ─────────────
grp('GROUP 3 — Quantity  Stock::validate()  [Min: 1 (must be greater than 0)]');
t('Extreme Min — qty 0',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>0])),true,'0 not > 0 → fail');
t('Min−1 — qty 0',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>0])),true,'0 below minimum → fail');
t('Min Boundary — qty 1',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>1])),true,'1 = minimum → pass');
t('Min+1 — qty 2',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>2])),true,'2 > min → pass');
t('Mid — qty 50',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>50])),true,'50 in range → pass');
t('Large qty — qty 999',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>999])),true,'Large quantity → pass');
t('Very large — qty 9999',empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>9999])),true,'Very large qty → pass');
t('Invalid — qty −1',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>-1])),true,'Negative → not > 0 → fail');
t('Invalid — qty −999',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>-999])),true,'Large negative → fail');
t('Invalid — qty empty',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>''])),true,'Empty string → fail');
t('Invalid — qty "abc"',!empty(Stock::validate(['product_id'=>1,'type'=>'IN','quantity'=>'abc'])),true,'"abc" cast to 0 → fail');

// ── GROUP 4: isAvailable() Boolean (TINYINT 1) ───────────
grp('GROUP 4 — isAvailable() Boolean  [TINYINT(1): 0 = Out of Stock | 1 = In Stock]');
$r=fn($a)=>['id'=>1,'product_id'=>1,'product_name'=>'iPhone 16 Pro','sku'=>'IPH-16-PRO','type'=>'IN','quantity'=>5,'is_available'=>$a,'notes'=>'Delivery received','moved_by_name'=>'Admin','created_at'=>'2026-05-01 09:00:00'];
$inStock=new Stock($r(1));$outStock=new Stock($r(0));
t('isAvailable() — is_available=1 → true',$inStock->isAvailable(),true,'DB 1 → PHP bool true');
t('getIsAvailable() — is_available=1 → int 1',$inStock->getIsAvailable(),1,'Raw getter returns int 1');
t('getAvailabilityLabel() — 1 → "In Stock"',$inStock->getAvailabilityLabel(),'In Stock','Label when in stock');
t('getAvailabilityBadge() — 1 → "b-green"',$inStock->getAvailabilityBadge(),'b-green','Green badge when in stock');
t('isAvailable() — is_available=0 → false',$outStock->isAvailable(),false,'DB 0 → PHP bool false');
t('getIsAvailable() — is_available=0 → int 0',$outStock->getIsAvailable(),0,'Raw getter returns int 0');
t('getAvailabilityLabel() — 0 → "Out of Stock"',$outStock->getAvailabilityLabel(),'Out of Stock','Label when out of stock');
t('getAvailabilityBadge() — 0 → "b-red"',$outStock->getAvailabilityBadge(),'b-red','Red badge when out of stock');
$def=new Stock(['id'=>1,'product_id'=>1,'product_name'=>'Test','sku'=>'T','type'=>'IN','quantity'=>1,'notes'=>'','moved_by_name'=>'','created_at'=>'']);
t('isAvailable() — no key → default true',$def->isAvailable(),true,'?? 1 fallback = assume in stock');

// ── GROUP 5: Business Logic Methods ─────────────────────
grp('GROUP 5 — Business Logic: getSignedQuantity() & getTypeBadge()');
$mkStock=fn($type,$qty,$avail=1)=>new Stock(['id'=>1,'product_id'=>1,'product_name'=>'iPhone','sku'=>'IPH','type'=>$type,'quantity'=>$qty,'is_available'=>$avail,'notes'=>'','moved_by_name'=>'Admin','created_at'=>'2026-05-01 09:00:00']);
t('getSignedQuantity() — IN qty 50 → "+50"',$mkStock('IN',50)->getSignedQuantity(),'+50','IN shows positive sign');
t('getSignedQuantity() — IN qty 1 → "+1"',$mkStock('IN',1)->getSignedQuantity(),'+1','IN min quantity positive');
t('getSignedQuantity() — OUT qty 5 → "+5"',$mkStock('OUT',5)->getSignedQuantity(),'+5','Quantity stored positive, type indicates direction');
t('getTypeBadge() — IN → "b-green"',$mkStock('IN',1)->getTypeBadge(),'b-green','IN badge is green');
t('getTypeBadge() — OUT → "b-red"',$mkStock('OUT',1)->getTypeBadge(),'b-red','OUT badge is red');
t('getTypeBadge() — ADJUSTMENT → "b-amber"',$mkStock('ADJUSTMENT',1)->getTypeBadge(),'b-amber','ADJUSTMENT badge is amber');
t('getTypeBadge() — unknown → "b-gray"',$mkStock('UNKNOWN',1)->getTypeBadge(),'b-gray','Unknown type defaults to gray');

// ── GROUP 6: StockMovement class (same tests) ─────────────
grp('GROUP 6 — StockMovement Class isAvailable() Boolean  [Same boolean behaviour]');
$sm=fn($a)=>new StockMovement(['id'=>1,'product_id'=>1,'product_name'=>'MacBook Pro','sku'=>'MBP-001','type'=>'OUT','quantity'=>2,'is_available'=>$a,'notes'=>'Order fulfilment','moved_by_name'=>'Admin','created_at'=>'2026-05-01 10:00:00']);
t('StockMovement isAvailable() — 1 → true',$sm(1)->isAvailable(),true,'TINYINT 1 → true');
t('StockMovement isAvailable() — 0 → false',$sm(0)->isAvailable(),false,'TINYINT 0 → false');
t('StockMovement getAvailabilityLabel() — 1 → "In Stock"',$sm(1)->getAvailabilityLabel(),'In Stock','In stock label');
t('StockMovement getAvailabilityLabel() — 0 → "Out of Stock"',$sm(0)->getAvailabilityLabel(),'Out of Stock','Out of stock label');
t('StockMovement getTypeBadge() — OUT → "b-red"',$sm(1)->getTypeBadge(),'b-red','OUT badge');
t('StockMovement getFormattedDate()',$sm(1)->getFormattedDate(),'01 May 2026 10:00','Date formatted correctly');

include "test_runner.html.php";