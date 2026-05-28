<?php
/**
 * Order Class — Middle Layer (OOP)
 * Represents a customer order with private properties and public getters.
 * Also includes OrderItem as a nested class for line items.
 */

// ── OrderItem Class ───────────────────────────────────────
class OrderItem {
    private int    $id;
    private int    $order_id;
    private int    $product_id;
    private string $product_name;
    private string $sku;
    private int    $quantity;
    private float  $unit_price;

    public function __construct(array $row) {
        $this->id           = (int)($row['id']           ?? 0);
        $this->order_id     = (int)($row['order_id']     ?? 0);
        $this->product_id   = (int)($row['product_id']   ?? 0);
        $this->product_name = $row['product_name']        ?? $row['pname'] ?? '';
        $this->sku          = $row['sku']                 ?? '';
        $this->quantity     = (int)($row['quantity']      ?? 0);
        $this->unit_price   = (float)($row['unit_price']  ?? 0);
    }

    public function getId(): int             { return $this->id; }
    public function getOrderId(): int        { return $this->order_id; }
    public function getProductId(): int      { return $this->product_id; }
    public function getProductName(): string { return $this->product_name; }
    public function getSku(): string         { return $this->sku; }
    public function getQuantity(): int       { return $this->quantity; }
    public function getUnitPrice(): float    { return $this->unit_price; }
    public function getLineTotal(): float    { return $this->quantity * $this->unit_price; }
    public function getFormattedUnitPrice(): string { return '$'.number_format($this->unit_price, 2); }
    public function getFormattedLineTotal(): string { return '$'.number_format($this->getLineTotal(), 2); }
}

// ── Order Class ───────────────────────────────────────────
class Order {

    private int    $id;
    private string $order_number;
    private string $customer;
    private string $status;
    private float  $total;
    private int    $is_paid;
    private string $notes;
    private string $created_by_name;
    private string $created_at;
    private int    $item_count;

    public function __construct(array $row) {
        $this->id              = (int)($row['id']          ?? 0);
        $this->order_number    = $row['order_number']       ?? '';
        $this->customer        = $row['customer']           ?? '';
        $this->status          = $row['status']             ?? 'pending';
        $this->total           = (float)($row['total']      ?? 0);
        $this->is_paid         = (int)($row['is_paid']      ?? 0);
        $this->notes           = $row['notes']              ?? '';
        $this->created_by_name = $row['created_by_name']    ?? $row['cby'] ?? '';
        $this->created_at      = $row['created_at']         ?? '';
        $this->item_count      = (int)($row['item_count']   ?? 0);
    }

    // ── Getters ──────────────────────────────────────────────
    public function getId(): int             { return $this->id; }
    public function getOrderNumber(): string { return $this->order_number; }
    public function getCustomer(): string    { return $this->customer; }
    public function getStatus(): string      { return $this->status; }
    public function getTotal(): float        { return $this->total; }
    public function getIsPaid(): int         { return $this->is_paid; }
    public function getNotes(): string       { return $this->notes; }
    public function getCreatedBy(): string   { return $this->created_by_name; }
    public function getCreatedAt(): string   { return $this->created_at; }
    public function getItemCount(): int      { return $this->item_count; }

    // ── Business logic ────────────────────────────────────────
    public function getFormattedTotal(): string {
        return '$' . number_format($this->total, 2);
    }

    public function getFormattedDate(): string {
        return $this->created_at ? date('d M Y', strtotime($this->created_at)) : '—';
    }

    public function getFormattedDateTime(): string {
        return $this->created_at ? date('d M Y H:i', strtotime($this->created_at)) : '—';
    }

    public function getStatusBadge(): string {
        return ['pending'=>'b-amber','processing'=>'b-blue',
                'completed'=>'b-green','cancelled'=>'b-gray'][$this->status] ?? 'b-gray';
    }

    public function isPaid(): bool       { return $this->is_paid === 1; }
    public function isEditable(): bool   { return in_array($this->status, ['pending','processing']); }
    public function isCancellable(): bool { return $this->status !== 'completed'; }

    public function getPaidBadge(): string  { return $this->isPaid() ? 'b-green' : 'b-amber'; }
    public function getPaidLabel(): string  { return $this->isPaid() ? 'Paid' : 'Unpaid'; }

    // ── Private DB helper ─────────────────────────────────────
    private static function fromDB(mysqli $conn, string $sql, string $types='', array $params=[]): array {
        if ($params) {
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($conn, $sql);
        }
        $objects = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $objects[] = new self($row);
            mysqli_free_result($result);
        }
        return $objects;
    }

    // ── Static factory methods ────────────────────────────────
    public static function getAll(mysqli $conn, ?string $status=null): array {
        $where = $status ? "WHERE o.status='".mysqli_real_escape_string($conn,$status)."'" : '';
        return self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             $where ORDER BY o.created_at DESC");
    }

    public static function getById(mysqli $conn, int $id): ?self {
        $objects = self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             WHERE o.id=$id");
        return $objects[0] ?? null;
    }

    public static function getItems(mysqli $conn, int $orderId): array {
        $result = mysqli_query($conn,
            "SELECT oi.*, p.name product_name, p.sku
             FROM order_items oi JOIN products p ON p.id=oi.product_id
             WHERE oi.order_id=$orderId");
        $items = [];
        if ($result) while ($row = mysqli_fetch_assoc($result)) $items[] = new OrderItem($row);
        return $items;
    }

    /**
     * Searches orders with optional text, status, date range and payment filters.
     * Returns array of Order objects sorted by date descending.
     */
    public static function search(
        mysqli  $conn,
        ?string $q        = null,
        ?string $status   = null,
        ?string $dateFrom = null,
        ?string $dateTo   = null,
        ?string $isPaid   = null
    ): array {
        $where = ["1=1"]; $params = []; $types = '';

        if ($q)      {
            $where[] = "(o.order_number LIKE ? OR o.customer LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss';
        }
        if ($status) { $where[] = "o.status=?";                  $params[] = $status;    $types .= 's'; }
        if ($dateFrom){ $where[] = "DATE(o.created_at) >= ?";    $params[] = $dateFrom;  $types .= 's'; }
        if ($dateTo)  { $where[] = "DATE(o.created_at) <= ?";    $params[] = $dateTo;    $types .= 's'; }
        if ($isPaid !== null && $isPaid !== '') {
            $where[] = "o.is_paid=?"; $params[] = (int)$isPaid;  $types .= 'i';
        }

        $sql = "SELECT o.*, u.full_name created_by_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
                FROM orders o JOIN users u ON u.id=o.created_by
                WHERE ".implode(' AND ', $where)." ORDER BY o.created_at DESC";
        return self::fromDB($conn, $sql, $types, $params);
    }

    /**
     * Calculates summary statistics from an array of Order objects.
     * Returns count, total value, paid value and unpaid value.
     */
    public static function getSummary(array $orders): array {
        $total  = array_sum(array_map(fn($o) => $o->getTotal(), $orders));
        $unpaid = array_sum(array_map(fn($o) => $o->isPaid() ? 0 : $o->getTotal(), $orders));
        return [
            'count'  => count($orders),
            'total'  => $total,
            'paid'   => $total - $unpaid,
            'unpaid' => $unpaid,
        ];
    }

    // ── Static write methods ──────────────────────────────────
    /**
     * Creates a new order. Returns new order ID or null on failure.
     * $is_paid: TINYINT(1) boolean — 1 = payment received, 0 = unpaid
     */
    public static function create(mysqli $conn, string $customer, string $notes, array $items, int $userId, int $is_paid=0): ?int {
        $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-4));
        $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));

        $stmt = mysqli_prepare($conn, "CALL sp_createOrder(?,?,?,?,@order_id)");
        mysqli_stmt_bind_param($stmt, 'sssi', $orderNumber, $customer, $notes, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $r = mysqli_query($conn, "SELECT @order_id AS id");
        $orderId = (int)(mysqli_fetch_assoc($r)['id'] ?? 0);
        if (!$orderId) return null;

        foreach ($items as $item) {
            $s2 = mysqli_prepare($conn,
                "INSERT INTO order_items(order_id,product_id,quantity,unit_price)VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($s2, 'iiid', $orderId, $item['product_id'], $item['quantity'], $item['price']);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
        }
        mysqli_query($conn, "UPDATE orders SET total=$total, is_paid=$is_paid WHERE id=$orderId");
        return $orderId;
    }

    /** Updates editable order details: customer name, notes and is_paid boolean */
    public static function update(mysqli $conn, int $orderId, string $customer, string $notes, int $is_paid): bool {
        $is_paid = $is_paid ? 1 : 0;
        $stmt = mysqli_prepare($conn, "UPDATE orders SET customer=?, notes=?, is_paid=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssii', $customer, $notes, $is_paid, $orderId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** Adds new line items to an existing order and recalculates total */
    public static function addItems(mysqli $conn, int $orderId, array $newItems): bool {
        if (empty($newItems)) return false;
        foreach ($newItems as $item) {
            $pid = (int)$item['product_id']; $qty = (int)$item['quantity']; $price = (float)$item['price'];
            if ($pid <= 0 || $qty <= 0) continue;
            $s = mysqli_prepare($conn, "INSERT INTO order_items(order_id,product_id,quantity,unit_price)VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($s, 'iiid', $orderId, $pid, $qty, $price);
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
        }
        $r = mysqli_query($conn, "SELECT SUM(quantity*unit_price) t FROM order_items WHERE order_id=$orderId");
        $newTotal = (float)(mysqli_fetch_assoc($r)['t'] ?? 0);
        mysqli_query($conn, "UPDATE orders SET total=$newTotal WHERE id=$orderId");
        return true;
    }

    /** Removes a single line item and recalculates order total */
    public static function removeItem(mysqli $conn, int $itemId, int $orderId): bool {
        $stmt = mysqli_prepare($conn, "DELETE FROM order_items WHERE id=? AND order_id=?");
        mysqli_stmt_bind_param($stmt, 'ii', $itemId, $orderId);
        $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $r  = mysqli_query($conn, "SELECT SUM(quantity*unit_price) t FROM order_items WHERE order_id=$orderId");
        $t  = (float)(mysqli_fetch_assoc($r)['t'] ?? 0);
        mysqli_query($conn, "UPDATE orders SET total=$t WHERE id=$orderId");
        return $ok;
    }

    /** Updates order status. Deducts stock when completed. */
    public static function updateStatus(mysqli $conn, int $orderId, string $status, int $userId): bool {
        if (!in_array($status, ['pending','processing','completed','cancelled'])) return false;
        if ($status === 'completed') {
            $orderNum = mysqli_fetch_assoc(
                mysqli_query($conn, "SELECT order_number FROM orders WHERE id=$orderId"))['order_number'];
            $items = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=$orderId");
            while ($it = mysqli_fetch_assoc($items)) {
                StockMovement::add($conn, $it['product_id'], 'OUT', $it['quantity'], $userId, "Order $orderNum fulfilment");
            }
        }
        $stmt = mysqli_prepare($conn, "CALL sp_updateOrderStatus(?,?)");
        mysqli_stmt_bind_param($stmt, 'is', $orderId, $status);
        $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $ok;
    }

    /** Toggles payment status for an order */
    public static function updatePaid(mysqli $conn, int $orderId, int $is_paid): bool {
        $is_paid = $is_paid ? 1 : 0;
        return (bool)mysqli_query($conn, "UPDATE orders SET is_paid=$is_paid WHERE id=$orderId");
    }

    /** Hard deletes an order and its items (CASCADE removes order_items) */
    public static function delete(mysqli $conn, int $orderId): bool {
        $stmt = mysqli_prepare($conn, "DELETE FROM orders WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        $ok = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        return $ok;
    }

    /**
     * Validates order form input. Returns array of error messages.
     * Empty array means input is valid.
     * Checks: customer name length, item count, duplicate products, quantities.
     */
    public static function validate(string $customer, array $items): array {
        $e = [];
        $customer = trim($customer);

        if (empty($customer))
            $e[] = 'Customer name is required.';
        elseif (strlen($customer) < 2)
            $e[] = 'Customer name must be at least 2 characters.';
        elseif (strlen($customer) > 120)
            $e[] = 'Customer name must not exceed 120 characters.';

        if (empty($items))
            $e[] = 'Please add at least one product.';
        elseif (count($items) > 20)
            $e[] = 'Maximum 20 products per order.';

        if (!empty($items)) {
            // Check for duplicate products in same order
            $pids = array_column($items, 'product_id');
            if (count($pids) !== count(array_unique($pids)))
                $e[] = 'Duplicate products found. Increase quantity instead of adding the same product twice.';

            // Check individual item quantities
            foreach ($items as $item) {
                if ((int)($item['quantity'] ?? 0) < 1)
                    $e[] = 'Each item must have a quantity of at least 1.';
                if ((int)($item['quantity'] ?? 0) > 999)
                    $e[] = 'Maximum quantity per item is 999.';
            }
        }
        return $e;
    }
}