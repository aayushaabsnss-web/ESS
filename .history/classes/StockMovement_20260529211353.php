<?php
/**
 * StockMovement Class — Middle Layer (OOP)
 * Represents a single stock transaction with private properties and public getters.
 * Static methods fetch from DB and return StockMovement objects.
 */
class StockMovement {

    // ── Private properties ───────────────────────────────────
    private int    $id;
    private int    $product_id;
    private string $product_name;
    private string $sku;
    private string $type;
    private int    $quantity;
    private int    $is_available;  // BOOLEAN — TINYINT(1) — 1=product has stock, 0=out of stock
    private string $notes;
    private string $moved_by_name;
    private string $created_at;

    /**
     * Constructor — maps a DB row into a StockMovement object.
     */
    public function __construct(array $row) {
        $this->id            = (int)($row['id']           ?? 0);
        $this->product_id    = (int)($row['product_id']   ?? 0);
        $this->product_name  = $row['product_name']       ?? $row['name'] ?? '';
        $this->sku           = $row['sku']                ?? '';
        $this->type          = $row['type']               ?? '';
        $this->quantity      = (int)($row['quantity']     ?? 0);
        $this->is_available  = (int)($row['is_available'] ?? 1); // BOOLEAN — cast DB 0/1 to int
        $this->notes         = $row['notes']              ?? '';
        $this->moved_by_name = $row['moved_by_name']      ?? $row['dby'] ?? '';
        $this->created_at    = $row['created_at']         ?? '';
    }

    // ── Getters ──────────────────────────────────────────────
    public function getId(): int             { return $this->id; }
    public function getProductId(): int      { return $this->product_id; }
    public function getProductName(): string { return $this->product_name; }
    public function getSku(): string         { return $this->sku; }
    public function getType(): string        { return $this->type; }
    public function getQuantity(): int       { return $this->quantity; }
    public function getIsAvailable(): int    { return $this->is_available; } // raw 0 or 1
    public function getNotes(): string       { return $this->notes; }
    public function getMovedBy(): string     { return $this->moved_by_name; }
    public function getCreatedAt(): string   { return $this->created_at; }

    // ── Business logic methods ────────────────────────────────

    /** Returns the quantity with + or - sign */
    public function getSignedQuantity(): string {
        return ($this->quantity > 0 ? '+' : '') . $this->quantity;
    }

    /** Returns the badge CSS class for the transaction type */
    public function getTypeBadge(): string {
        return ['IN'=>'b-green','OUT'=>'b-red','ADJUSTMENT'=>'b-amber'][$this->type] ?? 'b-gray';
    }

    /** Returns formatted date */
    public function getFormattedDate(): string {
        return $this->created_at ? date('d M Y H:i', strtotime($this->created_at)) : '—';
    }

    /** Returns short formatted date */
    public function getShortDate(): string {
        return $this->created_at ? date('d M H:i', strtotime($this->created_at)) : '—';
    }

    /**
     * BOOLEAN method — returns true if product still has stock after this movement.
     * Reads is_available TINYINT(1) from stock_movements table.
     * 1 in database = true = product has stock (quantity > 0)
     * 0 in database = false = product is out of stock (quantity = 0)
     */
    public function isAvailable(): bool {
        return $this->is_available === 1;
    }

    /** Returns human-readable stock availability label */
    public function getAvailabilityLabel(): string {
        return $this->isAvailable() ? 'In Stock' : 'Out of Stock';
    }

    /** Returns CSS badge class for availability status */
    public function getAvailabilityBadge(): string {
        return $this->isAvailable() ? 'b-green' : 'b-red';
    }

    // ── Private DB helper ────────────────────────────────────
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
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $objects;
    }

    // ── Static factory methods ───────────────────────────────

    /** Returns recent transactions as StockMovement objects */
    public static function getRecent(mysqli $conn, int $limit=20): array {
        return self::fromDB($conn,
            "SELECT sm.*, p.name product_name, p.sku, u.full_name moved_by_name
             FROM stock_movements sm
             JOIN products p ON p.id=sm.product_id
             JOIN users u ON u.id=sm.moved_by
             ORDER BY sm.created_at DESC LIMIT $limit");
    }

    /** Returns filtered history as StockMovement objects */
    public static function getHistory(mysqli $conn, ?int $pid, ?string $type, ?string $from, ?string $to): array {
        $stmt = mysqli_prepare($conn, "CALL sp_getStockHistory(?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'isss', $pid, $type, $from, $to);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $objects = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $objects[] = new self($row);
            mysqli_free_result($result);
        }
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $objects;
    }

    /** Returns all transactions for a specific product */
    public static function getByProduct(mysqli $conn, int $productId, int $limit=20): array {
        return self::fromDB($conn,
            "SELECT sm.*, p.name product_name, p.sku, u.full_name moved_by_name
             FROM stock_movements sm
             JOIN products p ON p.id=sm.product_id
             JOIN users u ON u.id=sm.moved_by
             WHERE sm.product_id=$productId
             ORDER BY sm.created_at DESC LIMIT $limit");
    }

    /**
     * Records a new stock movement and sets is_available boolean.
     * is_available = 1 if product still has stock after movement (new_qty > 0)
     * is_available = 0 if product is now out of stock (new_qty = 0)
     * Returns [bool $ok, string|null $error, int $newQty]
     */
    public static function add(mysqli $conn, int $productId, string $type, int $qty, int $userId, string $notes=''): array {
        $stmt = mysqli_prepare($conn, "CALL sp_addStockMovement(?,?,?,?,?,@new_qty,@error)");
        mysqli_stmt_bind_param($stmt, 'isiis', $productId, $type, $qty, $userId, $notes);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $r      = mysqli_query($conn, "SELECT @new_qty AS new_qty, @error AS error");
        $row    = mysqli_fetch_assoc($r);
        $newQty = (int)$row['new_qty'];

        // Set is_available based on new quantity — this is the BOOLEAN update
        // 1 = product still has stock (quantity > 0)
        // 0 = product is now out of stock (quantity = 0)
        $isAvailable = $newQty > 0 ? 1 : 0;
        mysqli_query($conn,
            "UPDATE stock_movements SET is_available=$isAvailable
             WHERE product_id=$productId
             ORDER BY created_at DESC LIMIT 1");

        return [$row['error'] === null, $row['error'], $newQty];
    }

    /** Updates the notes on a transaction */
    public static function updateNotes(mysqli $conn, int $id, string $notes): bool {
        $stmt = mysqli_prepare($conn, "UPDATE stock_movements SET notes=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $notes, $id);
        return mysqli_stmt_execute($stmt);
    }

    /** Deletes a transaction and reverses its quantity on the product */
    public static function deleteById(mysqli $conn, int $id): bool {
        $tx = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_movements WHERE id=$id"));
        if ($tx) {
            $reverse = -$tx['quantity'];
            mysqli_query($conn, "UPDATE products SET quantity=quantity+($reverse) WHERE id={$tx['product_id']}");
            mysqli_query($conn, "DELETE FROM stock_movements WHERE id=$id");
            return true;
        }
        return false;
    }

    /** Returns a product list array for dropdowns */
    public static function getProductList(mysqli $conn): array {
        $res  = mysqli_query($conn, "SELECT id, name, quantity FROM products WHERE is_active=1 ORDER BY name");
        $list = [];
        while ($row = mysqli_fetch_assoc($res)) $list[] = $row;
        return $list;
    }

    /** Validates stock form input */
    public static function validate(array $d, bool $isOwner=false): array {
        $e = [];
        if (empty($d['product_id']))   $e[] = 'Please select a product.';
        if (!in_array($d['type'] ?? '', ['IN','OUT','ADJUSTMENT'])) $e[] = 'Select a transaction type.';
        if (($d['type']??'') === 'ADJUSTMENT' && !$isOwner) $e[] = 'Only the Store Owner can record ADJUSTMENT transactions.';
        if (empty($d['quantity']) || (int)$d['quantity'] <= 0) $e[] = 'Quantity must be greater than 0.';
        return $e;
    }
}