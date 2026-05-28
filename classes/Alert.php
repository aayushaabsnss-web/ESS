<?php
/**
 * Alert Class — Middle Layer (OOP)
 * Represents a single monitoring alert with private properties and public getters.
 * Static methods fetch from DB and return Alert objects.
 */
class Alert {

    // ── Private properties ───────────────────────────────────
    private int    $id;
    private int    $product_id;
    private string $product_name;
    private string $sku;
    private int    $current_qty;
    private int    $threshold;
    private int    $shortfall;
    private string $alert_status;
    private string $alerted_at;
    private string $resolved_at;
    private string $resolved_by_name;

    /**
     * Constructor — maps a DB row into an Alert object.
     */
    public function __construct(array $row) {
        $this->id               = (int)($row['id']           ?? 0);
        $this->product_id       = (int)($row['product_id']   ?? 0);
        $this->product_name     = $row['product_name']        ?? '';
        $this->sku              = $row['sku']                 ?? '';
        $this->current_qty      = (int)($row['current_qty']  ?? $row['quantity'] ?? 0);
        $this->threshold        = (int)($row['threshold']    ?? 0);
        $this->shortfall        = max(0, $this->threshold - $this->current_qty);
        $this->alert_status     = $row['alert_status']        ?? 'active';
        $this->alerted_at       = $row['alerted_at']          ?? '';
        $this->resolved_at      = $row['resolved_at']         ?? '';
        $this->resolved_by_name = $row['resolved_by_name']    ?? '';
    }

    // ── Getters ──────────────────────────────────────────────
    public function getId(): int              { return $this->id; }
    public function getProductId(): int       { return $this->product_id; }
    public function getProductName(): string  { return $this->product_name; }
    public function getSku(): string          { return $this->sku; }
    public function getCurrentQty(): int      { return $this->current_qty; }
    public function getThreshold(): int       { return $this->threshold; }
    public function getShortfall(): int       { return $this->shortfall; }
    public function getStatus(): string       { return $this->alert_status; }
    public function getAlertedAt(): string    { return $this->alerted_at; }
    public function getResolvedAt(): string   { return $this->resolved_at; }
    public function getResolvedBy(): string   { return $this->resolved_by_name; }

    // ── Business logic methods ────────────────────────────────
    public function isActive(): bool { return $this->alert_status === 'active'; }

    public function getQtyColor(): string {
        return $this->current_qty === 0 ? 'c-red' : 'c-amber';
    }

    public function getFormattedAlertedAt(): string {
        return $this->alerted_at ? date('d M Y H:i', strtotime($this->alerted_at)) : '—';
    }

    public function getFormattedResolvedAt(): string {
        return $this->resolved_at ? date('d M Y H:i', strtotime($this->resolved_at)) : 'Not resolved';
    }

    // ── Private DB helper ────────────────────────────────────
    private static function fromDB(mysqli $conn, string $sql): array {
        $result  = mysqli_query($conn, $sql);
        $objects = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $objects[] = new self($row);
            mysqli_free_result($result);
        }
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $objects;
    }

    // ── Static factory methods ───────────────────────────────
    /** Returns all active alerts as Alert objects */
    public static function getActive(mysqli $conn): array {
        return self::fromDB($conn, "CALL sp_getActiveAlerts()");
    }

    /** Returns recently resolved alerts as Alert objects */
    public static function getResolved(mysqli $conn, int $limit=10): array {
        $result  = mysqli_query($conn,
            "SELECT m.*, p.name product_name, p.sku, p.quantity current_qty,
                    u.full_name resolved_by_name
             FROM monitoring m
             JOIN products p ON p.id=m.product_id
             LEFT JOIN users u ON u.id=m.resolved_by
             WHERE m.alert_status='resolved'
             ORDER BY m.resolved_at DESC LIMIT $limit");
        $objects = [];
        if ($result) while ($row = mysqli_fetch_assoc($result)) $objects[] = new self($row);
        return $objects;
    }

    /** Returns a single Alert object by ID */
    public static function getById(mysqli $conn, int $id): ?self {
        $result = mysqli_query($conn,
            "SELECT m.*, p.name product_name, p.sku, p.quantity current_qty,
                    p.price, p.category, u.full_name resolved_by_name
             FROM monitoring m
             JOIN products p ON p.id=m.product_id
             LEFT JOIN users u ON u.id=m.resolved_by
             WHERE m.id=$id");
        if (!$result) return null;
        $row = mysqli_fetch_assoc($result);
        return $row ? new self($row) : null;
    }

    /** Searches alerts by product name with optional status filter */
    public static function search(mysqli $conn, ?string $q, ?string $status): array {
        $where = ["1=1"];
        if ($q) {
            $safe    = mysqli_real_escape_string($conn, $q);
            $where[] = "p.name LIKE '%$safe%'";
        }
        if ($status) $where[] = "m.alert_status='".mysqli_real_escape_string($conn,$status)."'";
        $sql = "SELECT m.*, p.name product_name, p.sku, p.quantity current_qty,
                       u.full_name resolved_by_name
                FROM monitoring m
                JOIN products p ON p.id=m.product_id
                LEFT JOIN users u ON u.id=m.resolved_by
                WHERE ".implode(" AND ",$where)." ORDER BY m.alerted_at DESC";
        $result  = mysqli_query($conn, $sql);
        $objects = [];
        if ($result) while ($row = mysqli_fetch_assoc($result)) $objects[] = new self($row);
        return $objects;
    }

    /** Returns count of active alerts */
    public static function countActive(mysqli $conn): int {
        $r = mysqli_query($conn, "SELECT COUNT(*) c FROM monitoring WHERE alert_status='active'");
        return (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
    }

    /** Resolves an alert */
    public static function resolve(mysqli $conn, int $alertId, int $userId): bool {
        $stmt = mysqli_prepare($conn, "CALL sp_resolveAlert(?,?)");
        mysqli_stmt_bind_param($stmt, 'ii', $alertId, $userId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        while (mysqli_more_results($conn)) mysqli_next_result($conn);
        return $ok;
    }

    /** Sets or updates threshold for a product */
    public static function setThreshold(mysqli $conn, int $productId, int $threshold): bool {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO monitoring(product_id,threshold) VALUES(?,?)
             ON DUPLICATE KEY UPDATE threshold=?");
        mysqli_stmt_bind_param($stmt, 'iii', $productId, $threshold, $threshold);
        return mysqli_stmt_execute($stmt);
    }

    /** Deletes a resolved alert */
    public static function deleteById(mysqli $conn, int $id): bool {
        $al = mysqli_fetch_assoc(mysqli_query($conn,"SELECT alert_status FROM monitoring WHERE id=$id"));
        if ($al && $al['alert_status'] === 'resolved') {
            mysqli_query($conn, "DELETE FROM monitoring WHERE id=$id");
            return true;
        }
        return false;
    }
}