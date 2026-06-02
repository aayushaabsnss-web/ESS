<?php
/**
 * test_user.php — User / Auth Component Test Suite
 * Author:    Sapana Tamang
 * Component: User Management & Authentication
 * Module:    CTEC2713 Agile Development
 * Tests:     User getters, isActive() boolean, getRoleLabel(), getStatusBadge(), getInitials()
 */
require_once __DIR__ . "/../classes/User.php";

$tests=[];$pass=0;$fail=0;$group='';
function grp($n){global $group;$group=$n;}
function t($name,$actual,$expected,$desc=''){
    global $tests,$pass,$fail,$group;
    $ok=$actual===$expected;$ok?$pass++:$fail++;
    $tests[]=['g'=>$group,'n'=>$name,'ok'=>$ok,'a'=>var_export($actual,true),'e'=>var_export($expected,true),'d'=>$desc];
}

$mkU=fn($name,$role='employee',$active=1)=>new User([
    'id'=>1,'full_name'=>$name,'email'=>'test@electrostock.com',
    'role'=>$role,'is_active'=>$active,'created_at'=>'2026-05-01 09:00:00'
]);

// ── GROUP 1: Getter Methods ──────────────────────────────
grp('GROUP 1 — Getter Methods (User class core properties)');
$u=$mkU('Sapana Tamang','employee',1);
t('getId() returns int 1',$u->getId(),1,'Primary key getter');
t('getName() returns full name',$u->getName(),'Sapana Tamang','Full name getter');
t('getEmail() returns email',$u->getEmail(),'test@electrostock.com','Email getter');
t('getRole() returns role string',$u->getRole(),'employee','Role getter');
t('getCreatedAt() returns date string',$u->getCreatedAt(),'2026-05-01 09:00:00','Created at getter');

// ── GROUP 2: isActive() Boolean (TINYINT 1) ──────────────
grp('GROUP 2 — isActive() Boolean  [TINYINT(1): 0 = Inactive | 1 = Active]');
$active=$mkU('Active User','employee',1);
$inactive=$mkU('Inactive User','employee',0);
t('isActive() — is_active=1 → true',$active->isActive(),true,'DB 1 → PHP bool true');
t('getStatusBadge() — active → b-green',$active->getStatusBadge(),'b-green','Active user green badge');
t('getStatusLabel() — active → Active',$active->getStatusLabel(),'Active','Active status label');
t('isActive() — is_active=0 → false',$inactive->isActive(),false,'DB 0 → PHP bool false');

t('getStatusBadge() — inactive → b-red',$inactive->getStatusBadge(),'b-red','Inactive user red badge');
t('getStatusLabel() — inactive → Inactive',$inactive->getStatusLabel(),'Inactive','Inactive status label');
$def=new User(['id'=>1,'full_name'=>'Test','email'=>'t@t.com','role'=>'staff','created_at'=>'']);
t('isActive() — no is_active key → default true',$def->isActive(),true,'?? 1 fallback = active');

// ── GROUP 3: getRoleLabel() ──────────────────────────────
grp('GROUP 3 — getRoleLabel() Business Logic  [store_owner → Owner | employee → Employee]');
t('role=store_owner → Owner',$mkU('Test','store_owner')->getRoleLabel(),'Owner','Owner label');
t('role=employee → Employee',$mkU('Test','employee')->getRoleLabel(),'Employee','Employee label');
t('role=admin → Admin (fallback)',$mkU('Test','admin')->getRoleLabel(),'Admin','Unknown role uses ucfirst');
t('role=manager → Manager (fallback)',$mkU('Test','manager')->getRoleLabel(),'Manager','Custom role ucfirst');

// ── GROUP 4: getRoleBadge() ──────────────────────────────
grp('GROUP 4 — getRoleBadge() CSS Badge Classes');
t('role=store_owner badge → b-blue',$mkU('Test','store_owner')->getRoleBadge(),'b-blue','Owner has blue badge');
t('role=employee badge → b-gray',$mkU('Test','employee')->getRoleBadge(),'b-gray','Employee has gray badge');
// ── GROUP 5: getInitials() ───────────────────────────────
grp('GROUP 5 — getInitials() Name Initials  [Boundary tests on name length/format]');
t('Two word name → initials',$mkU('Sapana Tamang')->getInitials(),'ST','First letters of each word');
t('Single word name → first letter',$mkU('Admin')->getInitials(),'A','Single word = one initial');
t('Three word name → first two',$mkU('John Michael Smith')->getInitials(),'JM','Three words = first two initials');
t('Lowercase name → uppercase initials',$mkU('sapana tamang')->getInitials(),'ST','Initials always uppercase');
t('Name with extra spaces',$mkU('John  Smith')->getInitials(),'JS','Extra spaces handled');
t('Single letter name "A" → A',$mkU('A')->getInitials(),'A','Single char name');
t('Two char name "Jo" → J',$mkU('Jo')->getInitials(),'J','Two char single word');

// ── GROUP 6: getFormattedDate() ──────────────────────────
grp('GROUP 6 — getFormattedDate() & Full Name Boundary Tests');
$ud=new User(['id'=>1,'full_name'=>'Test User','email'=>'t@t.com','role'=>'staff','is_active'=>1,'created_at'=>'2026-05-01 09:00:00']);
t('Valid date → formatted',$ud->getFormattedDate(),'01 May 2026','Date formatted d M Y');
$ud2=new User(['id'=>1,'full_name'=>'Test','email'=>'t@t.com','role'=>'staff','is_active'=>1,'created_at'=>'']);
t('Empty date → —',$ud2->getFormattedDate(),'—','Empty date returns em dash');

// Full name boundary
t('Name 2 chars — min boundary',strlen($mkU('Jo')->getName())>=2,true,'2 char name stored correctly');
t('Name 50 chars — mid',strlen($mkU(str_repeat('A',50))->getName()),50,'50 char name stored correctly');
t('Name 100 chars — near max',strlen($mkU(str_repeat('B',100))->getName()),100,'100 char name stored correctly');

include __DIR__ . "/test_runner.html.php";
