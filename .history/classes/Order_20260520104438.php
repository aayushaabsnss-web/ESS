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

    public function getId(): int            { return $this->id; }
    public function getOrderId(): int       { return $this->order_id; }
    public function getProductId(): int     { return $this->product_id; }
    public function getProductName(): string{ return $this->product_name; }
    public function getSku(): string        { return $this->sku; }
    public function getQuantity(): int      { return $this->quantity; }
    public function getUnitPrice(): float   { return $this->unit_price; }
    public function getLineTotal(): float   { return $this->quantity * $this->unit_price; }
    public function getFormattedUnitPrice(): string { return '$'.number_format($this->unit_price, 2); }
    public function getFormattedLineTotal(): string { return '$'.number_format($this->getLineTotal(), 2); }
}

// ── Order Class ───────────────────────────────────────────
class Order {

    // ── Private properties ───────────────────────────────────
    private int    $id;
    private string $order_number;
    private string $customer;
    private string $status;
    private float  $total;
    private string $notes;
    private string $created_by_name;
    private string $created_at;
    private int    $item_count;

    /**
     * Constructor — maps a DB row into an Order object.
     */
    public function __construct(array $row) {
        $this->id              = (int)($row['id']          ?? 0);
        $this->order_number    = $row['order_number']       ?? '';
        $this->customer        = $row['customer']           ?? '';
        $this->status          = $row['status']             ?? 'pending';
        $this->total           = (float)($row['total']      ?? 0);
        $this->notes           = $row['notes']              ?? '';
        $this->created_by_name = $row['created_by_name']    ?? $row['cby'] ?? '';
        $this->created_at      = $row['created_at']         ?? '';
        $this->item_count      = (int)($row['item_count']   ?? 0);
    }

    // ── Getters ──────────────────────────────────────────────
    public function getId(): int              { return $this->id; }
    public function getOrderNumber(): string  { return $this->order_number; }
    public function getCustomer(): string     { return $this->customer; }
    public function getStatus(): string       { return $this->status; }
    public function getTotal(): float         { return $this->total; }
    public function getNotes(): string        { return $this->notes; }
    public function getCreatedBy(): string    { return $this->created_by_name; }
    public function getCreatedAt(): string    { return $this->created_at; }
    public function getItemCount(): int       { return $this->item_count; }

    // ── Business logic methods ────────────────────────────────
    public function getFormattedTotal(): string {
        return '$' . number_format($this->total, 2);
    }

    public function getFormattedDate(): string {
        return $this->created_at ? date('d M Y', strtotime($this->created_at)) : '—';
    }

    public function getStatusBadge(): string {
        return ['pending'=>'b-amber','processing'=>'b-blue','completed'=>'b-green','cancelled'=>'b-gray'][$this->status] ?? 'b-gray';
    }

    public function isEditable(): bool {
        return in_array($this->status, ['pending','processing']);
    }

    public function isCancellable(): bool {
        return $this->status !== 'completed';
    }

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
    /** Returns all orders (optionally filtered by status) as Order objects */
    public static function getAll(mysqli $conn, ?string $status=null): array {
        $where = $status ? "WHERE o.status='".mysqli_real_escape_string($conn,$status)."'" : '';
        return self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             $where ORDER BY o.created_at DESC");
    }

    /** Returns a single Order object by ID */
    public static function getById(mysqli $conn, int $id): ?self {
        $objects = self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             WHERE o.id=$id");
        return $objects[0] ?? null;
    }

    /** Returns line items for an order as OrderItem objects */
    public static function getItems(mysqli $conn, int $orderId): array {
        $result = mysqli_query($conn,
            "SELECT oi.*, p.name product_name, p.sku
             FROM order_items oi JOIN products p ON p.id=oi.product_id
             WHERE oi.order_id=$orderId");
        $items = [];
        if ($result) while ($row = mysqli_fetch_assoc($result)) $items[] = new OrderItem($row);
        return $items;
    }

    /** Searches orders and returns Order objects */
    public static function search(mysqli $conn, ?string $q, ?string $status): array {
        $where = ["1=1"]; $params = []; $types = '';
        if ($q)      { $where[] = "(o.order_number LIKE ? OR o.customer LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
        if ($status) { $where[] = "o.status=?"; $params[] = $status; $types .= 's'; }
        $sql = "SELECT o.*, u.full_name created_by_name FROM orders o
                JOIN users u ON u.id=o.created_by
                WHERE ".implode(' AND ',$where)." ORDER BY o.created_at DESC";
        return self::fromDB($conn, $sql, $types, $params);
    }

    /** Creates a new order. Returns the new order ID or null on failure. */
    public static function create(mysqli $conn, string $customer, string $notes, array $items, int $userId): ?int {
        $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-4));
        $total = array_sum(array_map(fn($i)=>$i['quantity']*$i['price'], $items));
        $stmt = mysqli_prepare($conn, "CALL sp_createOrder(?,?,?,?,@order_id)");
        mysqli_stmt_bind_param($stmt, 'sssi', $orderNumber, $customer, $notes, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $r = mysqli_query($conn, "SELECT @order_id AS id");
        $orderId = (int)(mysqli_fetch_assoc($r)['id'] ?? 0);
        if (!$orderId) return null;
        foreach ($items as $item) {
            $s2 = mysqli_prepare($conn, "INSERT INTO order_items(order_id,product_id,quantity,unit_price)VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($s2, 'iiid', $orderId, $item['product_id'], $item['quantity'], $item['price']);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
        }
        mysqli_query($conn, "UPDATE orders SET total=$total WHERE id=$orderId");
        return $orderId;
    }

    /** Updates order status. Deducts stock if status = completed. */
    public static function updateStatus(mysqli $conn, int $orderId, string $status, int $userId): bool {
        $allowed = ['pending','processing','completed','cancelled'];
        if (!in_array($status, $allowed)) return false;
        if ($status === 'completed') {
            $orderNum = mysqli_fetch_assoc(mysqli_query($conn,"SELECT order_number FROM orders WHERE id=$orderId"))['order_number'];
            $items = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=$orderId");
            while ($it = mysqli_fetch_assoc($items)) {
                StockMovement::add($conn, $it['product_id'], 'OUT', $it['quantity'], $userId, "Order $orderNum fulfilment");
            }
        }
        $stmt = mysqli_prepare($conn, "CALL sp_updateOrderStatus(?,?)");
        mysqli_stmt_bind_param($stmt, 'is', $orderId, $status);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $ok;
    }

    /** Validates order input */
    public static function validate(string $customer, array $items): array {
        $e = [];
        if (empty(trim($customer))) $e[] = 'Customer name is required.';
        if (empty($items))          $e[] = 'Please add at least one product.';
        return $e;
    }
}
