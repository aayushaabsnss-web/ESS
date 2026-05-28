<?php
/**
 * orders/export.php — Export Orders to CSV (Presentation Layer)
 * Downloads all filtered orders as a CSV file.
 * Respects the same filters as index.php (status, paid, date range, search).
 * Access: All authenticated users.
 */
require_once "../config/db.php";
require_once "../auth/session.php";
require_once "../classes/Order.php";
requireLogin();

$q         = trim($_GET["q"]         ?? "");
$status    = trim($_GET["status"]    ?? "");
$paid      = $_GET["paid"]           ?? "";
$date_from = trim($_GET["date_from"] ?? "");
$date_to   = trim($_GET["date_to"]   ?? "");

$orders = Order::search(
    $conn,
    $q        ?: null,
    $status   ?: null,
    $date_from?: null,
    $date_to  ?: null,
    $paid !== "" ? $paid : null
);

$filename = "orders_" . date("Y-m-d") . ".csv";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$out = fopen("php://output", "w");

// CSV header row
fputcsv($out, [
    "Order #",
    "Customer",
    "Items",
    "Total ($)",
    "Status",
    "Payment",
    "Created By",
    "Date"
]);

// One CSV row per Order object — all data accessed via getter methods
foreach ($orders as $order) {
    fputcsv($out, [
        $order->getOrderNumber(),
        $order->getCustomer(),
        $order->getItemCount(),
        number_format($order->getTotal(), 2),
        ucfirst($order->getStatus()),
        $order->getPaidLabel(),
        $order->getCreatedBy(),
        $order->getFormattedDate()
    ]);
}

// Summary row at the bottom of CSV
$summary = Order::getSummary($orders);
fputcsv($out, []);
fputcsv($out, [
    "TOTAL",
    $summary['count']." orders",
    "",
    number_format($summary['total'], 2),
    "",
    "Paid: $".number_format($summary['paid'],2)." | Unpaid: $".number_format($summary['unpaid'],2),
    "",
    "Exported: ".date("d M Y H:i")
]);

fclose($out);
exit;