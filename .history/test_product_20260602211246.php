<?php
/**
 * test_product.php — Product Component Test Suite
 * Author:    Mahendra Singh
 * Component: Product Management
 * Module:    CTEC2713 Agile Development
 * Tests:     Product getters, isActive() boolean, getStockStatus(), getStockBadge(), getFormattedPrice()
 */
require_once "classes/Product.php";

$tests=[];$pass=0;$fail=0;$group='';
function grp($n){global $group;$group=$n;}
function t($name,$actual,$expected,$desc=''){
    global $tests,$pass,$fail,$group;
    $ok=$actual===$expected;$ok?$pass++:$fail++;
    $tests[]=['g'=>$group,'n'=>$name,'ok'=>$ok,'a'=>var_export($actual,true),'e'=>var_export($expected,true),'d'=>$desc];
}

$mkP=fn($qty,$min,$active=1,$price=999.00)=>new Product([
    'id'=>1,'name'=>'iPhone 16 Pro','sku'=>'IPH-16-PRO',
    'category'=>'Smartphones','price'=>$price,
    'quantity'=>$qty,'min_qty'=>$min,
    'supplier'=>'Apple Inc','description'=>'Latest iPhone model',
    'is_active'=>$active,'created_at'=>'2026-05-01 09:00:00'
]);

// ── GROUP 1: Getter methods ──────────────────────────────
grp('GROUP 1 — Getter Methods (Product class core properties)');
$p=$mkP(10,5);
t('getId() returns int 1',$p->getId(),1,'Primary key getter');
t('getName() returns string',$p->getName(),'iPhone 16 Pro','Name getter');
t('getSku() returns string',$p->getSku(),'IPH-16-PRO','SKU getter');
t('getCategory() returns string',$p->getCategory(),'Smartphones','Category getter');
t('getPrice() returns float',$p->getPrice(),999.00,'Price getter');
t('getQuantity() returns int 10',$p->getQuantity(),10,'Quantity getter');
t('getMinQty() returns int 5',$p->getMinQty(),5,'Min qty getter');
t('getSupplier() returns string',$p->getSupplier(),'Apple Inc','Supplier getter');
t('getDescription() returns string',$p->getDescription(),'Latest iPhone model','Description getter');
t('getIsActive() returns int 1',$p->getIsActive(),1,'is_active raw getter');

// ── GROUP 2: isActive() Boolean (TINYINT 1) ──────────────
grp('GROUP 2 — isActive() Boolean  [TINYINT(1): 0 = Inactive | 1 = Active]');
$active=$mkP(10,5,1);$inactive=$mkP(10,5,0);
t('isActive() — is_active=1 → true',$active->isActive(),true,'DB 1 → PHP bool true');
t('getIsActive() — is_active=1 → int 1',$active->getIsActive(),1,'Raw getter int 1');
t('getStatusBadge() — active → b-green',$active->getStatusBadge(),'b-green','Active badge green');
t('getStatusLabel() — active → "Active"',$active->getStatusLabel(),'Active','Active label');
t('isActive() — is_active=0 → false',$inactive->isActive(),false,'DB 0 → PHP bool false');
t('getIsActive() — is_active=0 → int 0',$inactive->getIsActive(),0,'Raw getter int 0');
t('getStatusBadge() — inactive → b-red',$inactive->getStatusBadge(),'b-red','Inactive badge red');
t('getStatusLabel() — inactive → "Inactive"',$inactive->getStatusLabel(),'Inactive','Inactive label');
$def=new Product(['id'=>1,'name'=>'Test','sku'=>'T','category'=>'T','price'=>9.99,'quantity'=>1,'min_qty'=>1,'supplier'=>'','description'=>'','created_at'=>'']);
t('isActive() — no key → default true',$def->isActive(),true,'?? 1 fallback');

// ── GROUP 3: getStockStatus() — qty vs min_qty ───────────
grp('GROUP 3 — getStockStatus() Business Logic  [Out/Low/In Stock based on qty vs min_qty]');
t('qty=0 → Out of Stock',$mkP(0,5)->getStockStatus(),'Out of Stock','Zero qty = out of stock');
t('qty=1 min=5 → Low Stock',$mkP(1,5)->getStockStatus(),'Low Stock','Below min = low stock');
t('qty=5 min=5 → Low Stock',$mkP(5,5)->getStockStatus(),'Low Stock','Equal to min = low stock');
t('qty=6 min=5 → In Stock',$mkP(6,5)->getStockStatus(),'In Stock','Above min = in stock');
t('qty=100 min=5 → In Stock',$mkP(100,5)->getStockStatus(),'In Stock','Well above min = in stock');
t('qty=0 min=0 → Out of Stock',$mkP(0,0)->getStockStatus(),'Out of Stock','Zero qty always out');
t('qty=1 min=1 → Low Stock',$mkP(1,1)->getStockStatus(),'Low Stock','Equal to min = low');
t('qty=999 min=1 → In Stock',$mkP(999,1)->getStockStatus(),'In Stock','Large qty = in stock');

// ── GROUP 4: getStockBadge() badge classes ───────────────
grp('GROUP 4 — getStockBadge() Badge CSS Classes');
t('Out of Stock → b-red',$mkP(0,5)->getStockBadge(),'b-red','Red badge when out');
t('Low Stock → b-amber',$mkP(3,5)->getStockBadge(),'b-amber','Amber badge when low');
t('In Stock → b-green',$mkP(10,5)->getStockBadge(),'b-green','Green badge when in stock');

// ── GROUP 5: getFormattedPrice() ─────────────────────────
grp('GROUP 5 — getFormattedPrice() Currency Formatting');
t('price=999.00 → $999.00',$mkP(1,1,1,999.00)->getFormattedPrice(),'$999.00','Standard price');
t('price=1999.00 → $1,999.00',$mkP(1,1,1,1999.00)->getFormattedPrice(),'$1,999.00','Thousands separator');
t('price=0.99 → $0.99',$mkP(1,1,1,0.99)->getFormattedPrice(),'$0.99','Sub-dollar price');
t('price=9999.99 → $9,999.99',$mkP(1,1,1,9999.99)->getFormattedPrice(),'$9,999.99','Four digit price');
t('price=0.00 → $0.00',$mkP(1,1,1,0.00)->getFormattedPrice(),'$0.00','Zero price');

// ── GROUP 6: getFormattedDate() ──────────────────────────
grp('GROUP 6 — getFormattedDate() Date Formatting');
$pd=new Product(['id'=>1,'name'=>'Test','sku'=>'T','category'=>'T','price'=>9.99,'quantity'=>1,'min_qty'=>1,'supplier'=>'','description'=>'','is_active'=>1,'created_at'=>'2026-05-01 09:00:00']);
t('Valid date formats correctly',$pd->getFormattedDate(),'01 May 2026','Date formatted d M Y');
$pd2=new Product(['id'=>1,'name'=>'Test','sku'=>'T','category'=>'T','price'=>9.99,'quantity'=>1,'min_qty'=>1,'supplier'=>'','description'=>'','is_active'=>1,'created_at'=>'']);
t('Empty date returns —',$pd2->getFormattedDate(),'—','Empty date = em dash');

include "test_runner.html.php";