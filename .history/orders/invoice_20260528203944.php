<?php
/**
 * orders/invoice.php — Print Invoice View (Presentation Layer)
 * Clean print-friendly invoice page for one order.
 * No sidebar — standalone page with @media print CSS.
 * Access: All authenticated users.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
requireLogin();

$id = (int)($_GET["id"] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

$order = Order::getById($conn, $id);
if (!$order) { header("Location: index.php"); exit; }

$items = Order::getItems($conn, $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice — <?= h($order->getOrderNumber()) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #f5f5f5; }

  .page { max-width: 750px; margin: 30px auto; background: #fff; padding: 40px;
          box-shadow: 0 2px 12px rgba(0,0,0,.1); border-radius: 6px; }

  /* Header */
  .inv-hdr { display: flex; justify-content: space-between; align-items: flex-start;
             margin-bottom: 32px; padding-bottom: 20px; border-bottom: 2px solid #1a6fa3; }
  .company-name { font-size: 22px; font-weight: 700; color: #1a6fa3; }
  .company-sub  { font-size: 11px; color: #888; margin-top: 4px; }
  .inv-label    { text-align: right; }
  .inv-label h2 { font-size: 28px; color: #1a6fa3; font-weight: 700; letter-spacing: 2px; }
  .inv-label .inv-num { font-size: 13px; color: #555; margin-top: 4px; }

  /* Meta grid */
  .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
  .meta-box { background: #f8f9fa; border-radius: 4px; padding: 14px; }
  .meta-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: .08em;
                 color: #888; margin-bottom: 8px; }
  .meta-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
  .meta-row .label { color: #666; }
  .meta-row .value { font-weight: 500; }

  /* Items table */
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  thead tr { background: #1a6fa3; color: #fff; }
  thead th { padding: 10px 12px; text-align: left; font-size: 11px;
             text-transform: uppercase; letter-spacing: .06em; }
  thead th:last-child { text-align: right; }
  tbody tr:nth-child(even) { background: #f8f9fa; }
  tbody td { padding: 10px 12px; font-size: 12px; border-bottom: 1px solid #eee; }
  tbody td:last-child { text-align: right; font-weight: 500; }
  tfoot td { padding: 10px 12px; font-size: 13px; }

  /* Totals */
  .totals { display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
            margin-bottom: 28px; }
  .total-row { display: flex; gap: 40px; font-size: 13px; }
  .total-row .tl { color: #666; }
  .total-row .tv { font-weight: 500; min-width: 80px; text-align: right; }
  .grand-row { display: flex; gap: 40px; font-size: 16px; font-weight: 700;
               border-top: 2px solid #1a6fa3; padding-top: 8px; }
  .grand-row .tl { color: #1a6fa3; }
  .grand-row .tv { min-width: 80px; text-align: right; }

  /* Status badges */
  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .b-green  { background: #e1f5ee; color: #085041; }
  .b-amber  { background: #faeeda; color: #633806; }
  .b-blue   { background: #e6f1fb; color: #0c447c; }
  .b-gray   { background: #f0f0f0; color: #555; }

  /* Footer */
  .inv-footer { border-top: 1px solid #eee; padding-top: 16px; font-size: 11px;
                color: #888; text-align: center; }

  /* Print button — hidden when printing */
  .print-bar { max-width: 750px; margin: 20px auto 0; display: flex; gap: 10px; }
  .btn { padding: 8px 18px; border-radius: 4px; font-size: 13px; cursor: pointer;
         text-decoration: none; display: inline-block; border: none; }
  .btn-primary { background: #1a6fa3; color: #fff; }
  .btn-outline { background: transparent; border: 1px solid #ccc; color: #333; }

  /* Print styles — hides everything except the invoice page */
  @media print {
    body { background: #fff; }
    .print-bar { display: none !important; }
    .page { box-shadow: none; margin: 0; padding: 20px; max-width: 100%; }
    thead tr { background: #1a6fa3 !important; -webkit-print-color-adjust: exact; }
  }
</style>
</head>
<body>

<!-- Print/Back buttons — hidden when printing -->
<div class="print-bar">
  <a href="view.php?id=<?= $order->getId() ?>" class="btn btn-outline">&larr; Back</a>
  <button class="btn btn-primary" onclick="window.print()">&#128438; Print Invoice</button>
</div>

<div class="page">

  <!-- Invoice header -->
  <div class="inv-hdr">
    <div>
      <div class="company-name">ElectroStock Solutions</div>
      <div class="company-sub">Apple Inventory Management System</div>
    </div>
    <div class="inv-label">
      <h2>INVOICE</h2>
      <div class="inv-num"><?= h($order->getOrderNumber()) ?></div>
    </div>
  </div>

  <!-- Order meta -->
  <div class="meta">
    <div class="meta-box">
      <h4>Bill to</h4>
      <div class="meta-row">
        <span class="label">Customer</span>
        <span class="value"><?= h($order->getCustomer()) ?></span>
      </div>
      <div class="meta-row">
        <span class="label">Notes</span>
        <span class="value"><?= $order->getNotes() ? h($order->getNotes()) : "—" ?></span>
      </div>
    </div>
    <div class="meta-box">
      <h4>Invoice details</h4>
      <div class="meta-row">
        <span class="label">Date</span>
        <span class="value"><?= $order->getFormattedDateTime() ?></span>
      </div>
      <div class="meta-row">
        <span class="label">Status</span>
        <span class="value">
          <span class="badge <?= $order->getStatusBadge() ?>"><?= ucfirst($order->getStatus()) ?></span>
        </span>
      </div>
      <div class="meta-row">
        <span class="label">Payment</span>
        <span class="value">
          <span class="badge <?= $order->getPaidBadge() ?>"><?= $order->getPaidLabel() ?></span>
        </span>
      </div>
      <div class="meta-row">
        <span class="label">Prepared by</span>
        <span class="value"><?= h($order->getCreatedBy()) ?></span>
      </div>
    </div>
  </div>

  <!-- Line items -->
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>SKU</th>
        <th>Qty</th>
        <th>Unit price</th>
        <th>Line total</th>
      </tr>
    </thead>
    <tbody>
    <?php $i=1; foreach($items as $item): ?>
    <tr>
      <td style="color:#888"><?= $i++ ?></td>
      <td><?= h($item->getProductName()) ?></td>
      <td style="color:#888;font-size:11px"><?= h($item->getSku()) ?></td>
      <td><?= $item->getQuantity() ?></td>
      <td><?= $item->getFormattedUnitPrice() ?></td>
      <td><?= $item->getFormattedLineTotal() ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div class="totals">
    <div class="total-row">
      <span class="tl">Items</span>
      <span class="tv"><?= $order->getItemCount() ?></span>
    </div>
    <div class="grand-row">
      <span class="tl">Total</span>
      <span class="tv"><?= $order->getFormattedTotal() ?></span>
    </div>
    <div class="total-row" style="margin-top:4px">
      <span class="tl">Payment status</span>
      <span class="tv">
        <span class="badge <?= $order->getPaidBadge() ?>"><?= $order->getPaidLabel() ?></span>
      </span>
    </div>
  </div>

  <!-- Footer -->
  <div class="inv-footer">
    ElectroStock Solutions &nbsp;|&nbsp; Apple Inventory Management
    &nbsp;|&nbsp; Printed: <?= date("d M Y H:i") ?>
  </div>

</div>
</body>
</html>