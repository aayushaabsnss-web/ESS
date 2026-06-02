<?php
/**
 * test_alert.php — Alert/Monitoring Component Test Suite
 * Author:    Dowrin Anjum Prity
 * Component: Alert / Monitoring
 * Module:    CTEC2713 Agile Development
 * Tests:     Alert getters, isActive() boolean, getShortfall(), threshold boundary tests
 */
require_once "../classes/Alert.php";

$tests=[];$pass=0;$fail=0;$group='';
function grp($n){global $group;$group=$n;}
function t($name,$actual,$expected,$desc=''){
    global $tests,$pass,$fail,$group;
    $ok=$actual===$expected;$ok?$pass++:$fail++;
    $tests[]=['g'=>$group,'n'=>$name,'ok'=>$ok,'a'=>var_export($actual,true),'e'=>var_export($expected,true),'d'=>$desc];
}

$mkA=fn($cur,$thresh,$status='active',$resolved='')=>new Alert([
    'id'=>1,'product_id'=>1,'product_name'=>'iPhone 16 Pro',
    'sku'=>'IPH-16-PRO','current_qty'=>$cur,'threshold'=>$thresh,
    'shortfall'=>max(0,$thresh-$cur),'alert_status'=>$status,
    'alerted_at'=>'2026-05-01 09:00:00','resolved_at'=>$resolved,
    'resolved_by_name'=>$resolved?'Admin':''
]);

// ── GROUP 1: Getter Methods ──────────────────────────────
grp('GROUP 1 — Getter Methods (Alert class core properties)');
$a=$mkA(3,10);
t('getId() returns int',$a->getId(),1,'Primary key getter');
t('getProductId() returns int',$a->getProductId(),1,'Product FK getter');
t('getProductName() returns string',$a->getProductName(),'iPhone 16 Pro','Product name getter');
t('getSku() returns string',$a->getSku(),'IPH-16-PRO','SKU getter');
t('getCurrentQty() returns int 3',$a->getCurrentQty(),3,'Current qty getter');
t('getThreshold() returns int 10',$a->getThreshold(),10,'Threshold getter');
t('getShortfall() returns int 7',$a->getShortfall(),7,'Shortfall = threshold - current');
t('getAlertStatus() returns string',$a->getAlertStatus(),'active','Status getter');

// ── GROUP 2: isActive() Boolean ──────────────────────────
grp('GROUP 2 — isActive() Boolean  [alert_status: "active"=true | "resolved"=false]');
$active=$mkA(3,10,'active');
$resolved=$mkA(10,10,'resolved','2026-05-02 10:00:00');
t('isActive() — status=active → true',$active->isActive(),true,'Active alert = true');
t('getStatusBadge() — active → b-red',$active->getStatusBadge(),'b-red','Active alert badge red');
t('getStatusLabel() — active → Active',$active->getStatusLabel(),'Active','Active label');
t('isActive() — status=resolved → false',$resolved->isActive(),false,'Resolved alert = false');
t('getStatusBadge() — resolved → b-green',$resolved->getStatusBadge(),'b-green','Resolved badge green');
t('getStatusLabel() — resolved → Resolved',$resolved->getStatusLabel(),'Resolved','Resolved label');
$def=new Alert(['id'=>1,'product_id'=>1,'product_name'=>'T','sku'=>'T','current_qty'=>0,'threshold'=>5,'shortfall'=>5,'alerted_at'=>'','resolved_at'=>'','resolved_by_name'=>'']);
t('isActive() — no status → default true',$def->isActive(),true,'Missing status defaults to active');

// ── GROUP 3: Shortfall Boundary Tests ────────────────────
grp('GROUP 3 — Shortfall Calculation  [shortfall = threshold - current_qty]');
t('shortfall — qty=0 thresh=10 → 10',$mkA(0,10)->getShortfall(),10,'All stock depleted');
t('shortfall — qty=1 thresh=10 → 9',$mkA(1,10)->getShortfall(),9,'One unit remaining');
t('shortfall — qty=5 thresh=10 → 5',$mkA(5,10)->getShortfall(),5,'Half threshold remaining');
t('shortfall — qty=9 thresh=10 → 1',$mkA(9,10)->getShortfall(),1,'Just below threshold');
t('shortfall — qty=10 thresh=10 → 0',$mkA(10,10)->getShortfall(),0,'Exactly at threshold');
t('shortfall — qty=11 thresh=10 → 0',$mkA(11,10,0)->getShortfall(),0,'Above threshold no shortfall');
t('shortfall — qty=0 thresh=1 → 1',$mkA(0,1)->getShortfall(),1,'Minimum threshold');
t('shortfall — qty=0 thresh=999 → 999',$mkA(0,999)->getShortfall(),999,'Large threshold shortfall');
t('shortfall — qty=100 thresh=5 → 0',$mkA(100,5,0)->getShortfall(),0,'High stock no shortfall');

// ── GROUP 4: Threshold Boundary Tests ────────────────────
grp('GROUP 4 — Threshold Values  [when should alert trigger?]');
t('thresh=1 qty=0 — alert triggers',$mkA(0,1)->isActive(),true,'Zero qty below thresh=1');
t('thresh=1 qty=1 — at threshold',$mkA(1,1)->getCurrentQty()<=$mkA(1,1)->getThreshold(),true,'At threshold = alert active');
t('thresh=5 qty=4 — below threshold',$mkA(4,5)->getCurrentQty()<$mkA(4,5)->getThreshold(),true,'Below threshold triggers');
t('thresh=5 qty=5 — at threshold',$mkA(5,5)->getCurrentQty()<=$mkA(5,5)->getThreshold(),true,'At threshold triggers');
t('thresh=5 qty=6 — above threshold',$mkA(6,5)->getCurrentQty()>$mkA(6,5)->getThreshold(),true,'Above threshold no trigger');
t('thresh=10 qty=0 — empty stock',$mkA(0,10)->getShortfall(),10,'Completely out of stock');
t('thresh=100 qty=50 — mid range',$mkA(50,100)->getShortfall(),50,'50% shortfall');
t('thresh=999 qty=998 — near max',$mkA(998,999)->getShortfall(),1,'One unit short of threshold');

// ── GROUP 5: Formatted Date Methods ─────────────────────
grp('GROUP 5 — Date & Resolved By Formatting');
$ar=$mkA(10,10,'resolved','2026-05-02 10:00:00');
t('getFormattedAlertedAt() — valid date',$ar->getFormattedAlertedAt(),'01 May 2026 09:00','Alerted date formatted');
t('getFormattedResolvedAt() — resolved date',$ar->getFormattedResolvedAt(),'02 May 2026 10:00','Resolved date formatted');
t('getResolvedBy() — resolved by Admin',$ar->getResolvedByName(),'Admin','Resolved by name');
$aa=$mkA(3,10,'active');
t('getFormattedResolvedAt() — not resolved → —',$aa->getFormattedResolvedAt(),'—','Unresolved = em dash');
t('getResolvedBy() — active alert → empty',$aa->getResolvedByName(),'','No resolver for active alert');

include "test_runner.php";