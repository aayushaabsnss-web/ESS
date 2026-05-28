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

    // ── Private properties ───────────────────────────────────
    private int    $id;
    private string $order_number;
    private string $customer;
    private string $status;
    private float  $total;
    private int    $is_paid;       // BOOLEAN — stored as TINYINT(1) in MySQL (0=unpaid, 1=paid)
    private string $notes;
    private string $created_by_name;
    private string $created_at;
    private int    $item_count;

    /**
     * Constructor — maps a DB row array into an Order object.
     * Called internally by fromDB(). Never call new Order() from a page directly.
     */
    public function __construct(array $row) {
        $this->id              = (int)($row['id']          ?? 0);
        $this->order_number    = $row['order_number']       ?? '';
        $this->customer        = $row['customer']           ?? '';
        $this->status          = $row['status']             ?? 'pending';
        $this->total           = (float)($row['total']      ?? 0);
        $this->is_paid         = (int)($row['is_paid']      ?? 0); // BOOLEAN — cast DB 0/1 to int
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
    public function getIsPaid(): int         { return $this->is_paid; }   // raw 0 or 1
    public function getNotes(): string       { return $this->notes; }
    public function getCreatedBy(): string   { return $this->created_by_name; }
    public function getCreatedAt(): string   { return $this->created_at; }
    public function getItemCount(): int      { return $this->item_count; }

    // ── Business logic methods ────────────────────────────────

    /** Formats total as currency string e.g. $1,999.00 */
    public function getFormattedTotal(): string {
        return '$' . number_format($this->total, 2);
    }

    /** Formats created_at timestamp as human-readable date e.g. 20 May 2026 */
    public function getFormattedDate(): string {
        return $this->created_at ? date('d M Y', strtotime($this->created_at)) : '—';
    }

    /** Returns CSS badge class name based on current status */
    public function getStatusBadge(): string {
        return ['pending'=>'b-amber','processing'=>'b-blue','completed'=>'b-green','cancelled'=>'b-gray'][$this->status] ?? 'b-gray';
    }

    /**
     * BOOLEAN method — returns true if order can still be edited.
     * Reads is_paid (TINYINT) and status (ENUM) — no boolean column needed.
     * pending and processing orders are editable. completed and cancelled are locked.
     */
    public function isEditable(): bool {
        return in_array($this->status, ['pending', 'processing']);
    }

    /** BOOLEAN method — returns true if order can be cancelled (not already completed) */
    public function isCancellable(): bool {
        return $this->status !== 'completed';
    }

    /**
     * BOOLEAN method — returns true if payment has been received.
     * Reads is_paid TINYINT(1) from the orders table.
     * 0 in database = false (unpaid), 1 in database = true (paid).
     */
    public function isPaid(): bool {
        return $this->is_paid === 1;
    }

    /** Returns CSS badge class for payment status */
    public function getPaidBadge(): string {
        return $this->isPaid() ? 'b-green' : 'b-amber';
    }

    /** Returns human-readable payment status label */
    public function getPaidLabel(): string {
        return $this->isPaid() ? 'Paid' : 'Unpaid';
    }

    // ── Private DB helper ─────────────────────────────────────
    /**
     * Runs SQL, returns array of Order objects.
     * Uses prepared statements when $params given (user input).
     * Uses simple query when no params (safe internal SQL only).
     */
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

    // ── Static factory methods (READ) ─────────────────────────

    /** Returns all orders as Order objects, optionally filtered by status */
    public static function getAll(mysqli $conn, ?string $status=null): array {
        $where = $status ? "WHERE o.status='".mysqli_real_escape_string($conn,$status)."'" : '';
        return self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             $where ORDER BY o.created_at DESC");
    }

    /** Returns one Order object by ID, or null if not found */
    public static function getById(mysqli $conn, int $id): ?self {
        $objects = self::fromDB($conn,
            "SELECT o.*, u.full_name created_by_name,
             (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
             FROM orders o JOIN users u ON u.id=o.created_by
             WHERE o.id=$id");
        return $objects[0] ?? null;
    }

    /** Returns all line items for one order as OrderItem objects */
    public static function getItems(mysqli $conn, int $orderId): array {
        $result = mysqli_query($conn,
            "SELECT oi.*, p.name product_name, p.sku
             FROM order_items oi JOIN products p ON p.id=oi.product_id
             WHERE oi.order_id=$orderId");
        $items = [];
        if ($result) while ($row = mysqli_fetch_assoc($result)) $items[] = new OrderItem($row);
        return $items;
    }

    /** Searches orders by order number or customer name, optionally filtered by status */
    public static function search(mysqli $conn, ?string $q, ?string $status): array {
        $where = ["1=1"]; $params = []; $types = '';
        if ($q)      { $where[] = "(o.order_number LIKE ? OR o.customer LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
        if ($status) { $where[] = "o.status=?"; $params[] = $status; $types .= 's'; }
        $sql = "SELECT o.*, u.full_name created_by_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) item_count
                FROM orders o JOIN users u ON u.id=o.created_by
                WHERE ".implode(' AND ',$where)." ORDER BY o.created_at DESC";
        return self::fromDB($conn, $sql, $types, $params);
    }

    // ── Static write methods (WRITE) ──────────────────────────

    /**
     * Creates a new order in the database.
     * Returns the new order ID on success, null on failure.
     * $is_paid = 1 if payment already received, 0 if not yet paid.
     */
    public static function create(mysqli $conn, string $customer, string $notes, array $items, int $userId, int $is_paid=0): ?int {
        // Generate unique order number e.g. ORD-20260525-A7K2
        $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-4));

        // Calculate total: sum of (quantity × price) for all items
        $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));

        // Insert order header via stored procedure
        $stmt = mysqli_prepare($conn, "CALL sp_createOrder(?,?,?,?,@order_id)");
        mysqli_stmt_bind_param($stmt, 'sssi', $orderNumber, $customer, $notes, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Read back the new order ID from MySQL OUT parameter
        $r = mysqli_query($conn, "SELECT @order_id AS id");
        $orderId = (int)(mysqli_fetch_assoc($r)['id'] ?? 0);
        if (!$orderId) return null;

        // Insert each line item — unit_price copied from products at order time
        foreach ($items as $item) {
            $s2 = mysqli_prepare($conn, "INSERT INTO order_items(order_id,product_id,quantity,unit_price)VALUES(?,?,?,?)");
            mysqli_stmt_bind_param($s2, 'iiid', $orderId, $item['product_id'], $item['quantity'], $item['price']);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
        }

        // Update total and is_paid (boolean) on the order header row
        mysqli_query($conn, "UPDATE orders SET total=$total, is_paid=$is_paid WHERE id=$orderId");
        return $orderId;
    }

    /**
     * Updates order status. If status = completed, deducts stock via StockMovement.
     * Returns true on success, false on failure.
     */
    public static function updateStatus(mysqli $conn, int $orderId, string $status, int $userId): bool {
        // Whitelist — only known statuses allowed
        $allowed = ['pending','processing','completed','cancelled'];
        if (!in_array($status, $allowed)) return false;

        // Completing an order deducts stock for each line item
        if ($status === 'completed') {
            $orderNum = mysqli_fetch_assoc(mysqli_query($conn,"SELECT order_number FROM orders WHERE id=$orderId"))['order_number'];
            $items = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=$orderId");
            while ($it = mysqli_fetch_assoc($items)) {
                StockMovement::add($conn, $it['product_id'], 'OUT', $it['quantity'], $userId, "Order $orderNum fulfilment");
            }
        }

        // Update status in database via stored procedure
        $stmt = mysqli_prepare($conn, "CALL sp_updateOrderStatus(?,?)");
        mysqli_stmt_bind_param($stmt, 'is', $orderId, $status);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Clear stored procedure result buffers to prevent "commands out of sync"
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $ok;
    }

    /**
     * Updates is_paid boolean for an order.
     * $is_paid: 1 = paid, 0 = unpaid
     */
    public static function updatePaid(mysqli $conn, int $orderId, int $is_paid): bool {
        $is_paid = $is_paid ? 1 : 0; // enforce 0 or 1 only
        return (bool)mysqli_query($conn, "UPDATE orders SET is_paid=$is_paid WHERE id=$orderId");
    }

    /**
     * Updates editable order details — customer name, notes, is_paid boolean.
     * Called from edit.php when the Save changes form is submitted.
     * Returns true on success, false on failure.
     */
    public static function update(mysqli $conn, int $orderId, string $customer, string $notes, int $is_paid): bool {
        $is_paid = $is_paid ? 1 : 0; // enforce boolean — only 0 or 1 allowed
        $stmt = mysqli_prepare($conn, "UPDATE orders SET customer=?, notes=?, is_paid=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssii', $customer, $notes, $is_paid, $orderId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /**
     * Adds new line items to an existing order.
     * Called from edit.php when customer wants to add more products.
     * After inserting items, recalculates and updates the order total.
     * Returns true on success, false if no valid items given.
     */
    public static function addItems(mysqli $conn, int $orderId, array $newItems): bool {
        if (empty($newItems)) return false;

        foreach ($newItems as $item) {
            $pid   = (int)$item['product_id'];
            $qty   = (int)$item['quantity'];
            $price = (float)$item['price'];
            if ($pid <= 0 || $qty <= 0) continue;

            // Insert new line item — price frozen at current product price
            $stmt = mysqli_prepare($conn,
                "INSERT INTO order_items(order_id, product_id, quantity, unit_price)
                 VALUES(?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiid', $orderId, $pid, $qty, $price);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Recalculate total from all items including newly added ones
        $r = mysqli_query($conn,
            "SELECT SUM(quantity * unit_price) AS total
             FROM order_items WHERE order_id=$orderId");
        $newTotal = (float)(mysqli_fetch_assoc($r)['total'] ?? 0);
        mysqli_query($conn, "UPDATE orders SET total=$newTotal WHERE id=$orderId");

        return true;
    }

    /** Validates order form input. Returns array of error messages, empty if valid. */
    public static function validate(string $customer, array $items): array {
        $e = [];
        if (empty(trim($customer))) $e[] = 'Customer name is required.';
        if (empty($items))          $e[] = 'Please add at least one product.';
        return $e;
    }
}