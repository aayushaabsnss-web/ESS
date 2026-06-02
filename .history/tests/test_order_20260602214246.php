<?php
/**
 * test_order.php — Order Management Test Suite
 * Author:    Aayusha Katuwal (P2893828)
 * Component: Order Management
 * Module:    CTEC2713 Agile Development
 * Tests:     Order::validate() boundaries, isPaid() boolean, isEditable(), isCancellable()
 */
require_once "classes/Order.php";

$tests=[];$pass=0;$fail=0;$group='';
function grp($n){global $group;$group=$n;}
function t($name,$actual,$expected,$desc=''){
    global $tests,$pass,$fail,$group;
    $ok=$actual===$expected;$ok?$pass++:$fail++;
    $tests[]=['g'=>$group,'n'=>$name,'ok'=>$ok,'a'=>var_export($actual,true),'e'=>var_export($expected,true),'d'=>$desc];
}

$item=[['product_id'=>1,'quantity'=>1,'price'=>999.00]];

// ── GROUP 1: Customer Name (min:2, max:120) ─────────────
grp('GROUP 1 — Customer Name  Order::validate()  [Min: 2 | Max: 120 characters]');
t('Extreme Min — empty ""',!empty(Order::validate('',$item)),true,'Empty → required error');
t('Min−1 — 1 char "A"',!empty(Order::validate('A',$item)),true,'1 char < 2 → must fail');
t('Min Boundary — 2 chars "Jo"',empty(Order::validate('Jo',$item)),true,'2 chars = min → must pass');
t('Min+1 — 3 chars "Tim"',empty(Order::validate('Tim',$item)),true,'3 chars > min → must pass');
t('Mid — 30 chars "Apple Business Solutions Ltd"',empty(Order::validate('Apple Business Solutions Ltd',$item)),true,'30 chars → must pass');
t('Max−1 — 119 chars',empty(Order::validate(str_repeat('A',119),$item)),true,'119 < 120 → must pass');
t('Max Boundary — 120 chars',empty(Order::validate(str_repeat('B',120),$item)),true,'120 = max → must pass');
t('Max+1 — 121 chars',!empty(Order::validate(str_repeat('C',121),$item)),true,'121 > 120 → must fail');
t('Extreme Max — 300 chars',!empty(Order::validate(str_repeat('D',300),$item)),true,'300 >> max → must fail');
t('Invalid type — numbers "12345"',empty(Order::validate('12345',$item)),true,'Numeric string valid as name → pass');
t('Other — whitespace only "   "',!empty(Order::validate('   ',$item)),true,'Trim → empty → must fail');

// ── GROUP 2: Item Quantity (min:1, max:999) ──────────────
grp('GROUP 2 — Item Quantity  Order::validate()  [Min: 1 | Max: 999]');
$c='John Smith';
t('Extreme Min — qty 0',!empty(Order::validate($c,[['product_id'=>1,'quantity'=>0,'price'=>9.99]])),true,'0 < 1 → must fail');
t('Min−1 — qty 0 (min is 1)',!empty(Order::validate($c,[['product_id'=>1,'quantity'=>0,'price'=>9.99]])),true,'0 below min → fail');
t('Min Boundary — qty 1',empty(Order::validate($c,[['product_id'=>1,'quantity'=>1,'price'=>9.99]])),true,'1 = min → pass');
t('Min+1 — qty 2',empty(Order::validate($c,[['product_id'=>1,'quantity'=>2,'price'=>9.99]])),true,'2 > min → pass');
t('Mid — qty 500',empty(Order::validate($c,[['product_id'=>1,'quantity'=>500,'price'=>9.99]])),true,'500 in range → pass');
t('Max−1 — qty 998',empty(Order::validate($c,[['product_id'=>1,'quantity'=>998,'price'=>9.99]])),true,'998 < 999 → pass');
t('Max Boundary — qty 999',empty(Order::validate($c,[['product_id'=>1,'quantity'=>999,'price'=>9.99]])),true,'999 = max → pass');
t('Max+1 — qty 1000',!empty(Order::validate($c,[['product_id'=>1,'quantity'=>1000,'price'=>9.99]])),true,'1000 > 999 → fail');
t('Extreme Max — qty 9999',!empty(Order::validate($c,[['product_id'=>1,'quantity'=>9999,'price'=>9.99]])),true,'9999 >> max → fail');
t('Invalid — qty −1',!empty(Order::validate($c,[['product_id'=>1,'quantity'=>-1,'price'=>9.99]])),true,'Negative < min → fail');
t('Other — qty 1 price zero',empty(Order::validate($c,[['product_id'=>1,'quantity'=>1,'price'=>0.00]])),true,'Price not validated here → pass');

// ── GROUP 3: Number of Items (min:1, max:20) ─────────────
grp('GROUP 3 — Number of Items  Order::validate()  [Min: 1 item | Max: 20 items]');
t('Extreme Min — 0 items',!empty(Order::validate($c,[])),true,'0 items → at least one required');
t('Min−1 — 0 items',!empty(Order::validate($c,[])),true,'0 below min → fail');
t('Min Boundary — 1 item',empty(Order::validate($c,[['product_id'=>1,'quantity'=>1,'price'=>9.99]])),true,'1 item = min → pass');
t('Min+1 — 2 items',empty(Order::validate($c,[['product_id'=>1,'quantity'=>1,'price'=>9.99],['product_id'=>2,'quantity'=>1,'price'=>5.99]])),true,'2 items → pass');
t('Mid — 10 items',empty(Order::validate($c,array_map(fn($i)=>['product_id'=>$i,'quantity'=>1,'price'=>9.99],range(1,10)))),true,'10 items → pass');
t('Max−1 — 19 items',empty(Order::validate($c,array_map(fn($i)=>['product_id'=>$i,'quantity'=>1,'price'=>9.99],range(1,19)))),true,'19 < 20 → pass');
t('Max Boundary — 20 items',empty(Order::validate($c,array_map(fn($i)=>['product_id'=>$i,'quantity'=>1,'price'=>9.99],range(1,20)))),true,'20 = max → pass');
t('Max+1 — 21 items',!empty(Order::validate($c,array_map(fn($i)=>['product_id'=>$i,'quantity'=>1,'price'=>9.99],range(1,21)))),true,'21 > 20 → fail');
t('Extreme Max — 50 items',!empty(Order::validate($c,array_map(fn($i)=>['product_id'=>$i,'quantity'=>1,'price'=>9.99],range(1,50)))),true,'50 >> 20 → fail');
t('Invalid — duplicate products',(function()use($c){$dup=[['product_id'=>1,'quantity'=>1,'price'=>9.99],['product_id'=>1,'quantity'=>2,'price'=>9.99]];return !empty(Order::validate($c,$dup));})()"
,true,'Duplicate product_ids → fail');
t('Other — 1 item with null price',empty(Order::validate($c,[['product_id'=>1,'quantity'=>1,'price'=>null]])),true,'Price not validated → pass');

// ── GROUP 4: isPaid() Boolean (TINYINT 1) ────────────────
grp('GROUP 4 — isPaid() Boolean Method  [TINYINT(1): 0 = Unpaid | 1 = Paid]');
$r=fn($p)=>['id'=>1,'order_number'=>'ORD-TEST','customer'=>'Test','status'=>'pending','total'=>999.00,'is_paid'=>$p,'notes'=>'','created_by_name'=>'Admin','created_at'=>'2026-05-01 10:00:00','item_count'=>1];
$paid=new Order($r(1));$unpaid=new Order($r(0));
t('isPaid() — is_paid=1 → true',$paid->isPaid(),true,'DB 1 → PHP bool true');
t('getIsPaid() — is_paid=1 → int 1',$paid->getIsPaid(),1,'Raw getter returns int 1');
t('getPaidLabel() — is_paid=1 → Paid',$paid->getPaidLabel(),'Paid','Label when paid');
t('getPaidBadge() — is_paid=1 → b-green',$paid->getPaidBadge(),'b-green','Green badge when paid');
t('isPaid() — is_paid=0 → false',$unpaid->isPaid(),false,'DB 0 → PHP bool false');
t('getIsPaid() — is_paid=0 → int 0',$unpaid->getIsPaid(),0,'Raw getter returns int 0');
t('getPaidLabel() — is_paid=0 → Unpaid',$unpaid->getPaidLabel(),'Unpaid','Label when unpaid');
t('getPaidBadge() — is_paid=0 → b-amber',$unpaid->getPaidBadge(),'b-amber','Amber badge when unpaid');
$def=new Order(['id'=>1,'order_number'=>'X','customer'=>'T','status'=>'pending','total'=>0,'notes'=>'','created_by_name'=>'','created_at'=>'','item_count'=>0]);
t('isPaid() — no is_paid key → default false',$def->isPaid(),false,'?? 0 fallback = false');

// ── GROUP 5: isEditable() & isCancellable() ──────────────
grp('GROUP 5 — isEditable() & isCancellable() Business Logic');
$o=fn($s)=>new Order(['id'=>1,'order_number'=>'X','customer'=>'T','status'=>$s,'total'=>0,'is_paid'=>0,'notes'=>'','created_by_name'=>'','created_at'=>'','item_count'=>0]);
t('isEditable() — pending → true',$o('pending')->isEditable(),true,'Pending orders can be edited');
t('isEditable() — processing → true',$o('processing')->isEditable(),true,'Processing orders can be edited');
t('isEditable() — completed → false',$o('completed')->isEditable(),false,'Completed orders are locked');
t('isEditable() — cancelled → false',$o('cancelled')->isEditable(),false,'Cancelled orders are locked');
t('isCancellable() — pending → true',$o('pending')->isCancellable(),true,'Pending can be cancelled');
t('isCancellable() — processing → true',$o('processing')->isCancellable(),true,'Processing can be cancelled');
t('isCancellable() — cancelled → true',$o('cancelled')->isCancellable(),true,'Already cancelled = still cancellable');
t('isCancellable() — completed → false',$o('completed')->isCancellable(),false,'Completed cannot be cancelled');

// ── GROUP 6: Formatting methods ──────────────────────────
grp('GROUP 6 — Formatting & Badge Methods');
$ord=new Order(['id'=>5,'order_number'=>'ORD-20260501-AB12','customer'=>'Apple Store','status'=>'pending','total'=>1999.00,'is_paid'=>0,'notes'=>'','created_by_name'=>'Admin','created_at'=>'2026-05-01 10:00:00','item_count'=>3]);
t('getFormattedTotal() — $1999.00',$ord->getFormattedTotal(),'$1,999.00','Currency formatted correctly');
t('getStatusBadge() — pending → b-amber',$ord->getStatusBadge(),'b-amber','Pending badge class');
t('getStatusBadge() — completed → b-green',(new Order(['id'=>1,'order_number'=>'X','customer'=>'T','status'=>'completed','total'=>0,'is_paid'=>0,'notes'=>'','created_by_name'=>'','created_at'=>'','item_count'=>0]))->getStatusBadge(),'b-green','Completed badge class');
t('getFormattedDate() — empty → em dash',(new Order(['id'=>1,'order_number'=>'X','customer'=>'T','status'=>'pending','total'=>0,'is_paid'=>0,'notes'=>'','created_by_name'=>'','created_at'=>'','item_count'=>0]))->getFormattedDate(),'—','Empty date returns —');

include "test_runner.html.php";