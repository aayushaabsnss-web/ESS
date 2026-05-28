<?php
/**
 * Stock Class — Middle Layer (OOP)
 * Represents a single stock transaction with private properties and public getters.
 * Static methods fetch from DB and return Stock objects.
 */
class Stock {

    // ── Private properties ───────────────────────────────────
    private int    $id;
    private int    $product_id;
    private string $product_name;
    private string $sku;
    private string $type;
    private int    $quantity;
    private string $notes;
    private string $moved_by_name;
    private string $created_at;

    /**
     * Constructor — maps a DB row into a Stock object.
     */
    public function __construct(array $row) {
        $this->id            = (int)($row['id']           ?? 0);
        $this->product_id    = (int)($row['product_id']   ?? 0);
        $this->product_name  = $row['product_name']       ?? $row['name'] ?? '';
        $this->sku           = $row['sku']                ?? '';
        $this->type          = $row['type']               ?? '';
        $this->quantity      = (int)($row['quantity']     ?? 0);
        $this->notes         = $row['notes']              ?? '';
        $this->moved_by_name = $row['moved_by_name']      ?? $row['dby'] ?? '';
        $this->created_at    = $row['created_at']         ?? '';
    }

    // ── Getters ──────────────────────────────────────────────
    public function getId(): int           { return $this->id; }
    public function getProductId(): int    { return $this->product_id; }
    public function getProductName(): string { return $this->product_name; }
    public function getSku(): string       { return $this->sku; }
    public function getType(): string      { return $this->type; }
    public function getQuantity(): int     { return $this->quantity; }
    public function getNotes(): string     { return $this->notes; }
    public function getMovedBy(): string   { return $this->moved_by_name; }
    public function getCreatedAt(): string { return $this->created_at; }

    // ── Business logic methods ────────────────────────────────
    /** Returns the quantity with + or - sign */
    public function getSignedQuantity(): string {
        $sign = $this->type === 'OUT' ? '-' : '+';
return $sign . $this->quantity;
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
    /** Returns recent transactions as Stock objects */
    public static function getRecent(mysqli $conn, int $limit=20): array {
        return self::fromDB($conn,
            "SELECT sm.*, p.name product_name, p.sku, u.full_name moved_by_name
             FROM stock_movements sm
             JOIN products p ON p.id=sm.product_id
             JOIN users u ON u.id=sm.moved_by
             ORDER BY sm.created_at DESC LIMIT $limit");
    }

    /** Returns filtered history as Stock objects */
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

    /** Records a new stock movement. Returns [bool $ok, string|null $error, int $newQty] */
    public static function add(mysqli $conn, int $productId, string $type, int $qty, int $userId, string $notes=''): array {
        $stmt = mysqli_prepare($conn, "CALL sp_addStockMovement(?,?,?,?,?,@new_qty,@error)");
        mysqli_stmt_bind_param($stmt, 'isiis', $productId, $type, $qty, $userId, $notes);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $r   = mysqli_query($conn, "SELECT @new_qty AS new_qty, @error AS error");
        $row = mysqli_fetch_assoc($r);
        return [$row['error'] === null, $row['error'], (int)$row['new_qty']];
    }

    /** Updates the minimum stock level for a product */
    public static function updateMinQty(mysqli $conn, int $productId, int $minQty): bool {
        $stmt = mysqli_prepare($conn, "UPDATE products SET min_qty=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ii", $minQty, $productId);
        return mysqli_stmt_execute($stmt);
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
            if ($tx['type'] === 'OUT')        { $reverse = +$tx['quantity']; }
            elseif ($tx['type'] === 'IN')     { $reverse = -$tx['quantity']; }
            else                              { $reverse = 0; }
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
    
    //** Deletes all stock movements for a product and resets quantity to 0 *//
        public static function deleteAllByProduct(mysqli $conn, int $productId): bool {
         mysqli_query($conn, "DELETE FROM stock_movements WHERE product_id=$productId");
         mysqli_query($conn, "UPDATE products SET quantity=0 WHERE id=$productId");
         return true;
        }
}