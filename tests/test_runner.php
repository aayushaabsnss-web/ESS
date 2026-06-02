<?php
/**
 * test_runner.html.php — Shared HTML output template
 * Included at the bottom of every individual test file.
 * Displays test results as a styled HTML page.
 * Variables expected: $tests, $pass, $fail, $total (calculated here)
 */
$total = $pass + $fail;
$rate  = $total > 0 ? round($pass / $total * 100) : 0;

// Work out the component name and author from the test file
$file     = basename($_SERVER['PHP_SELF']);
$meta     = [
    'test_order.php'   => ['Order Management',              'Aayusha Katuwal',   'P2893828'],
    'test_stock.php'   => ['Stock Management',              'Dikshya Kafle',     ''],
    'test_product.php' => ['Product Management',            'Mahendra Singh',    ''],
    'test_alert.php'   => ['Alert / Monitoring',            'Dowrin Anjum Prity',''],
    'test_user.php'    => ['User Management & Auth',        'Sapana Tamang',     ''],
];
[$component, $author, $pnum] = $meta[$file] ?? ['ESS System', 'Team', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ESS Tests — <?= $component ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:13px;background:#F0F4F8;padding:16px;color:#1B2631}
.wrap{max-width:1140px;margin:0 auto}

/* Header */
.hdr{background:linear-gradient(135deg,#1A5276,#2E86C1);color:#fff;padding:18px 22px;border-radius:8px;margin-bottom:16px}
.hdr h1{font-size:18px;margin-bottom:5px}
.hdr p{font-size:12px;opacity:.85;line-height:1.8}

/* Summary cards */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.card{background:#fff;border-radius:8px;padding:14px;text-align:center;border:1px solid #DDE2EA}
.card .num{font-size:30px;font-weight:700;font-family:monospace}
.card .lbl{font-size:11px;color:#666;margin-top:3px}
.blue .num{color:#1A5276}
.green .num{color:#085041}
.red .num{color:#791F1F}
.rate .num{color:<?= $fail===0 ?'#085041':'#791F1F' ?>}

/* Progress bar */
.bar-wrap{height:8px;background:#E0E6EE;border-radius:20px;overflow:hidden;margin-bottom:16px}
.bar-fill{height:8px;border-radius:20px;background:<?= $fail===0 ?'linear-gradient(90deg,#1D9E75,#27AE60)':'linear-gradient(90deg,#C0392B,#E74C3C)' ?>;width:<?= $rate ?>%}

/* Group block */
.grp{margin-bottom:14px;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.grp-hd{background:#1A5276;color:#fff;padding:9px 14px;font-size:12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.grp-hd .pills{display:flex;gap:6px}
.pill{font-size:10px;padding:2px 9px;border-radius:12px;font-weight:600}
.p-pass{background:rgba(255,255,255,.2)}
.p-fail{background:#C0392B}

table{width:100%;border-collapse:collapse;background:#fff}
th{background:#EBF5FB;padding:8px 11px;text-align:left;font-size:11px;color:#1A5276;font-weight:700;border-bottom:2px solid #2471A3}
td{padding:7px 11px;font-size:12px;border-bottom:1px solid #EEE;vertical-align:top;line-height:1.5}
tr:nth-child(even){background:#FAFBFC}
tr:last-child td{border-bottom:none}

.bp{display:inline-block;background:#E1F5EE;color:#085041;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600}
.bf{display:inline-block;background:#FCEBEB;color:#791F1F;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600}
.mono{font-family:monospace;font-size:11px;background:#F0F0F0;padding:1px 5px;border-radius:3px;word-break:break-all}
.desc{color:#666;font-style:italic}

.foot{text-align:center;margin-top:14px;font-size:11px;color:#888;padding:10px}
</style>
</head>
<body>
<div class="wrap">

<div class="hdr">
  <h1>&#10003; ESS — <?= $component ?> Test Suite</h1>
  <p>
    <strong><?= $author ?></strong><?= $pnum?" ($pnum)":'' ?>
    &nbsp;|&nbsp; CTEC2713 Agile Development
    &nbsp;|&nbsp; File: <?= $file ?>
    &nbsp;|&nbsp; Run: <?= date('d M Y H:i:s') ?>
    &nbsp;|&nbsp; PHP <?= PHP_VERSION ?>
  </p>
</div>

<div class="cards">
  <div class="card blue"><div class="num"><?= $total ?></div><div class="lbl">Total Tests</div></div>
  <div class="card green"><div class="num"><?= $pass ?></div><div class="lbl">Passed</div></div>
  <div class="card red"><div class="num"><?= $fail ?></div><div class="lbl">Failed</div></div>
  <div class="card rate"><div class="num"><?= $rate ?>%</div><div class="lbl">Pass Rate</div></div>
</div>

<div class="bar-wrap"><div class="bar-fill"></div></div>

<?php
$groups=[];
foreach($tests as $t) $groups[$t['g']][]=$t;
foreach($groups as $gname=>$gt):
  $gp=count(array_filter($gt,fn($x)=>$x['ok']));
  $gf=count($gt)-$gp;
?>
<div class="grp">
  <div class="grp-hd">
    <span><?= htmlspecialchars($gname) ?></span>
    <span class="pills">
      <span class="pill p-pass"><?= $gp ?>/<?= count($gt) ?> PASS</span>
      <?php if($gf>0):?><span class="pill p-fail"><?= $gf ?> FAIL</span><?php endif;?>
    </span>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:30px">#</th>
        <th style="width:36%">Test</th>
        <th style="width:70px">Result</th>
        <th style="width:13%">Expected</th>
        <th style="width:13%">Actual</th>
        <th>Description</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($gt as $i=>$row):?>
    <tr>
      <td style="color:#999;font-size:11px"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($row['n']) ?></td>
      <td><?= $row['ok']?'<span class="bp">&#10003; PASS</span>':'<span class="bf">&#10007; FAIL</span>' ?></td>
      <td><span class="mono"><?= htmlspecialchars($row['e']) ?></span></td>
      <td><span class="mono"><?= htmlspecialchars($row['a']) ?></span></td>
      <td class="desc"><?= htmlspecialchars($row['d']) ?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endforeach;?>

<div class="foot">
  ElectroStock Solutions (ESS) &nbsp;|&nbsp;
  <?= $component ?> — <?= $author ?> &nbsp;|&nbsp;
  <?= $pass ?> passed / <?= $fail ?> failed / <?= $total ?> total
</div>
</div>
</body>
</html>