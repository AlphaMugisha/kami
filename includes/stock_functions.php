<?php
/* ================================================================
   OZONE · Batch Stock Tracking ("Kurungura") — SHARED LOGIC
   ----------------------------------------------------------------
   Reusable helpers for the batch-based restock / FIFO sale system.
   Centralised here so the restock endpoint AND the POS sale handler
   share one authoritative implementation.

   IMPORTANT: every function below assumes the CALLER has already
   opened a PDO transaction ($pdo->beginTransaction()) and will
   commit/rollback. They never touch transaction state themselves,
   so they compose cleanly inside larger atomic operations.
   ================================================================ */

declare(strict_types=1);

/**
 * Log a restock batch ("kurungura") for a product.
 *
 * Atomically (within the caller's transaction):
 *   1. inserts a stock_batches row with quantity_remaining = quantity_bought
 *   2. adds the bought quantity to products.stock
 *   3. syncs products.buying_price / selling_price / price to this batch
 *
 * @return int  the new batch id
 */
function log_restock(
    PDO $pdo,
    int $branch_id,
    int $product_id,
    int $quantity,
    float $buying_price,
    float $selling_price,
    ?string $purchased_at,
    ?string $notes,
    int $user_id,
    ?int $location_id = null
): int {
    if ($quantity <= 0)       throw new InvalidArgumentException('Quantity bought must be greater than zero.');
    if ($buying_price < 0)    throw new InvalidArgumentException('Buying price cannot be negative.');
    if ($selling_price < 0)   throw new InvalidArgumentException('Selling price cannot be negative.');

    // Normalise the timestamp — default to "now" when not supplied / invalid.
    $when = null;
    if ($purchased_at !== null && trim($purchased_at) !== '') {
        $ts = strtotime($purchased_at);
        if ($ts !== false) {
            $when = date('Y-m-d H:i:s', $ts);
        }
    }
    if ($when === null) {
        $when = date('Y-m-d H:i:s');
    }

    // 1. Insert the batch.
    $stmt = $pdo->prepare(
        "INSERT INTO stock_batches
            (branch_id, location_id, product_id, quantity_bought, quantity_remaining, buying_price, selling_price, purchased_at, notes, created_by)
         VALUES
            (:bid, :lid, :pid, :qty, :qty2, :buy, :sell, :purchased_at, :notes, :uid)"
    );
    $stmt->execute([
        'bid'          => $branch_id,
        'lid'          => $location_id,
        'pid'          => $product_id,
        'qty'          => $quantity,
        'qty2'         => $quantity,
        'buy'          => $buying_price,
        'sell'         => $selling_price,
        'purchased_at' => $when,
        'notes'        => ($notes !== null && trim($notes) !== '') ? trim($notes) : null,
        'uid'          => $user_id,
    ]);
    $batch_id = (int)$pdo->lastInsertId();

    // 2 + 3. Add to running stock and sync the cached latest-batch prices.
    //         `price` is kept equal to selling_price so the POS keeps working.
    //         Products are a single shared catalog now (not per-branch), so
    //         this is keyed on the product id alone.
    $stmt = $pdo->prepare(
        "UPDATE products
            SET stock         = stock + :qty,
                buying_price  = :buy,
                selling_price = :sell,
                price         = :sell2
          WHERE id = :pid"
    );
    $stmt->execute([
        'qty'   => $quantity,
        'buy'   => $buying_price,
        'sell'  => $selling_price,
        'sell2' => $selling_price,
        'pid'   => $product_id,
    ]);

    return $batch_id;
}

/**
 * Deduct `quantity` units of a product using FIFO across stock batches.
 *
 * Walks the product's batches oldest-first, decrementing
 * quantity_remaining until the demand is satisfied. Does NOT touch
 * products.stock — the caller owns that (the POS already does it) so
 * this stays a pure batch-ledger operation.
 *
 * If the live batches cannot cover the demand (legacy / drifted data),
 * the shortfall is charged against the product's cached buying_price so
 * a sale never hard-fails on a stock-count technicality.
 *
 * @return array{
 *   batch_id: int|null,           oldest batch consumed (for sale_items.batch_id)
 *   avg_buying_price: float,      weighted-average cost across consumed batches
 *   allocations: array<int,array{batch_id:int|null,qty:int,buying_price:float}>
 * }
 */
function deduct_stock_fifo(int $product_id, int $quantity, PDO $pdo, int $branch_id, ?int $location_id = null): array
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Deduction quantity must be greater than zero.');
    }

    // When $location_id is null, every batch qualifies (branches with no
    // stock_locations rows, or legacy data) — exactly today's behavior.
    $stmt = $pdo->prepare(
        "SELECT id, quantity_remaining, buying_price
           FROM stock_batches
          WHERE product_id = :pid AND branch_id = :bid AND quantity_remaining > 0
            AND (:lid1 IS NULL OR location_id = :lid2)
          ORDER BY purchased_at ASC, id ASC"
    );
    $stmt->execute(['pid' => $product_id, 'bid' => $branch_id, 'lid1' => $location_id, 'lid2' => $location_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $needed       = $quantity;
    $allocations  = [];
    $total_cost   = 0.0;

    $update = $pdo->prepare(
        "UPDATE stock_batches
            SET quantity_remaining = quantity_remaining - :take
          WHERE id = :id"
    );

    foreach ($batches as $b) {
        if ($needed <= 0) break;

        $take = min($needed, (int)$b['quantity_remaining']);
        $update->execute(['take' => $take, 'id' => $b['id']]);

        $cost = (float)$b['buying_price'];
        $allocations[] = ['batch_id' => (int)$b['id'], 'qty' => $take, 'buying_price' => $cost];
        $total_cost   += $take * $cost;
        $needed       -= $take;
    }

    // Graceful fallback for stock not represented by any batch. (Products are
    // a shared catalog, so this cached price isn't branch-scoped.)
    if ($needed > 0) {
        $p = $pdo->prepare("SELECT buying_price FROM products WHERE id = :id");
        $p->execute(['id' => $product_id]);
        $fallback_cost = (float)$p->fetchColumn();

        $allocations[] = ['batch_id' => null, 'qty' => $needed, 'buying_price' => $fallback_cost];
        $total_cost   += $needed * $fallback_cost;
        $needed        = 0;
    }

    $primary_batch = $allocations[0]['batch_id'] ?? null;
    $avg_buying    = round($total_cost / $quantity, 2);

    return [
        'batch_id'         => $primary_batch,
        'avg_buying_price' => $avg_buying,
        'allocations'      => $allocations,
    ];
}

/**
 * Look up which branch a stock location physically belongs to.
 */
function get_location_branch(PDO $pdo, int $location_id): int
{
    $s = $pdo->prepare("SELECT branch_id FROM stock_locations WHERE id = :id");
    $s->execute(['id' => $location_id]);
    $branch_id = $s->fetchColumn();
    if ($branch_id === false) {
        throw new InvalidArgumentException("Location #{$location_id} does not exist.");
    }
    return (int)$branch_id;
}

/**
 * Fetch a stock_locations row's flags (is_warehouse, is_arrival).
 */
function get_location_flags(PDO $pdo, int $location_id): array
{
    $s = $pdo->prepare("SELECT is_warehouse, is_arrival FROM stock_locations WHERE id = :id");
    $s->execute(['id' => $location_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new InvalidArgumentException("Location #{$location_id} does not exist.");
    }
    return ['is_warehouse' => (bool)$row['is_warehouse'], 'is_arrival' => (bool)$row['is_arrival']];
}

/**
 * Move stock between two named locations.
 *
 *  - SAME branch (e.g. Big Stock -> Fridge): lands instantly, exactly like
 *    before — the destination batch is created right away and the transfer
 *    is logged as already `received` (whoever moved it is right there).
 *
 *  - ACROSS branches (e.g. Main Branch's warehouse "refilling" Second
 *    Branch's shop floor, since products are one shared catalog): the
 *    stock leaves the source immediately (deducted from its batches, same
 *    FIFO ledger a sale uses), but does NOT become sellable at the
 *    destination yet. It sits as a `pending` transfer until someone at the
 *    receiving branch confirms it physically arrived, via receive_transfer().
 *    This is what stands in for a "back room holding shelf" — no separate
 *    location needed, the pending transfer itself IS the holding spot.
 *
 * @return array{transfer_id:int, status:string} the logged transfer's id
 *              and status ('received' or 'pending')
 */
function transfer_stock(
    PDO $pdo,
    int $product_id,
    int $from_location_id,
    int $to_location_id,
    int $quantity,
    int $user_id,
    ?string $notes = null
): array {
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Transfer quantity must be greater than zero.');
    }
    if ($from_location_id === $to_location_id) {
        throw new InvalidArgumentException('Source and destination locations must be different.');
    }

    $from_branch_id = get_location_branch($pdo, $from_location_id);
    $to_branch_id   = get_location_branch($pdo, $to_location_id);
    $is_cross_branch = $from_branch_id !== $to_branch_id;

    // A cross-branch refill (warehouse -> shop, or shop -> shop) must always
    // land in the destination's "Shop Arrivals" holding spot first — never
    // straight onto a sellable shelf like Hanging/Fridge. Same-branch moves
    // (e.g. classifying Shop Arrivals -> Hanging) are unrestricted.
    if ($is_cross_branch && !get_location_flags($pdo, $to_location_id)['is_arrival']) {
        throw new RuntimeException('Cross-branch transfers must be sent to the destination branch\'s Shop Arrivals location.');
    }

    // deduct_stock_fifo() has a graceful "charge the shortfall to cached cost"
    // fallback for sales (never hard-fail a sale on a stock-count technicality).
    // A transfer must NOT use that fallback — moving more than physically sits
    // at the source would fabricate phantom stock at the destination — so
    // check real availability first and fail loudly if it's short.
    $avail_stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_remaining), 0) FROM stock_batches
          WHERE product_id = :pid AND location_id = :lid"
    );
    $avail_stmt->execute(['pid' => $product_id, 'lid' => $from_location_id]);
    $available = (int)$avail_stmt->fetchColumn();
    if ($available < $quantity) {
        throw new RuntimeException("Only {$available} unit(s) available at the source location.");
    }

    $fifo = deduct_stock_fifo($product_id, $quantity, $pdo, $from_branch_id, $from_location_id);

    $s = $pdo->prepare("SELECT selling_price FROM products WHERE id = :id");
    $s->execute(['id' => $product_id]);
    $selling_price = (float)$s->fetchColumn();

    $clean_notes = ($notes !== null && trim($notes) !== '') ? trim($notes) : null;

    if (!$is_cross_branch) {
        // Same branch: land it right away, one batch per cost tier so the
        // FIFO trail and per-batch cost basis stay accurate at the destination.
        $insert = $pdo->prepare(
            "INSERT INTO stock_batches
                (branch_id, location_id, product_id, quantity_bought, quantity_remaining, buying_price, selling_price, purchased_at, notes, created_by)
             VALUES
                (:bid, :lid, :pid, :qty, :qty2, :buy, :sell, NOW(), :notes, :uid)"
        );
        foreach ($fifo['allocations'] as $alloc) {
            if ($alloc['qty'] <= 0) continue;
            $insert->execute([
                'bid'   => $to_branch_id,
                'lid'   => $to_location_id,
                'pid'   => $product_id,
                'qty'   => $alloc['qty'],
                'qty2'  => $alloc['qty'],
                'buy'   => $alloc['buying_price'],
                'sell'  => $selling_price,
                'notes' => 'Transferred in' . ($clean_notes !== null ? ' — ' . $clean_notes : ''),
                'uid'   => $user_id,
            ]);
        }

        $audit = $pdo->prepare(
            "INSERT INTO stock_transfers
                (branch_id, product_id, from_location_id, to_location_id, quantity, status, received_at, received_by, transferred_by, notes)
             VALUES (:bid, :pid, :from_loc, :to_loc, :qty, 'received', NOW(), :uid1, :uid2, :notes)"
        );
        $audit->execute([
            'bid' => $to_branch_id, 'pid' => $product_id, 'from_loc' => $from_location_id,
            'to_loc' => $to_location_id, 'qty' => $quantity, 'uid1' => $user_id, 'uid2' => $user_id, 'notes' => $clean_notes,
        ]);
        return ['transfer_id' => (int)$pdo->lastInsertId(), 'status' => 'received'];
    }

    // Cross branch: the weighted-average cost across whatever cost tiers were
    // consumed becomes the single cost basis for the batch created on receipt.
    $avg_buying_price = $fifo['avg_buying_price'];

    $audit = $pdo->prepare(
        "INSERT INTO stock_transfers
            (branch_id, product_id, from_location_id, to_location_id, quantity, status, avg_buying_price, sell_price_at_send, transferred_by, notes)
         VALUES (:bid, :pid, :from_loc, :to_loc, :qty, 'pending', :buy, :sell, :uid, :notes)"
    );
    $audit->execute([
        'bid' => $to_branch_id, 'pid' => $product_id, 'from_loc' => $from_location_id,
        'to_loc' => $to_location_id, 'qty' => $quantity, 'buy' => $avg_buying_price,
        'sell' => $selling_price, 'uid' => $user_id, 'notes' => $clean_notes,
    ]);
    return ['transfer_id' => (int)$pdo->lastInsertId(), 'status' => 'pending'];
}

/**
 * Confirm a pending cross-branch transfer actually arrived: creates the
 * batch that makes it sellable at the destination location, and marks the
 * transfer received. Only meaningful for transfers still `pending`.
 */
function receive_transfer(PDO $pdo, int $transfer_id, int $user_id): void
{
    $stmt = $pdo->prepare("SELECT * FROM stock_transfers WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $transfer_id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($t === false) {
        throw new RuntimeException('Transfer not found.');
    }
    if ($t['status'] !== 'pending') {
        throw new RuntimeException('This transfer was already received.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO stock_batches
            (branch_id, location_id, product_id, quantity_bought, quantity_remaining, buying_price, selling_price, purchased_at, notes, created_by)
         VALUES
            (:bid, :lid, :pid, :qty, :qty2, :buy, :sell, NOW(), 'Refill received', :uid)"
    );
    $insert->execute([
        'bid'  => (int)$t['branch_id'],
        'lid'  => (int)$t['to_location_id'],
        'pid'  => (int)$t['product_id'],
        'qty'  => (int)$t['quantity'],
        'qty2' => (int)$t['quantity'],
        'buy'  => (float)$t['avg_buying_price'],
        'sell' => (float)$t['sell_price_at_send'],
        'uid'  => $user_id,
    ]);

    $pdo->prepare("UPDATE stock_transfers SET status = 'received', received_at = NOW(), received_by = :uid WHERE id = :id")
        ->execute(['uid' => $user_id, 'id' => $transfer_id]);
}

/**
 * Convert a data-entry quantity ("I'm restocking/sending 3 boxes") into raw
 * units, using the product's own box size. Every stock number that actually
 * gets stored (stock_batches, products.stock, FIFO, etc.) stays unit-based —
 * this is the one place "boxes" get turned into units, right at the moment
 * they're recorded, so it can never be spoofed from the browser.
 *
 * @param string $unit_type  'unit' (default) or 'box'
 * @throws InvalidArgumentException if 'box' is requested but this product
 *         has no units_per_box set yet.
 */
function resolve_box_quantity(PDO $pdo, int $product_id, int $quantity, string $unit_type = 'unit'): int
{
    if ($unit_type !== 'box') {
        return $quantity;
    }

    $stmt = $pdo->prepare("SELECT units_per_box FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $units_per_box = $stmt->fetchColumn();

    if ($units_per_box === false || $units_per_box === null || (int)$units_per_box <= 0) {
        throw new InvalidArgumentException('This product has no box size set yet — restock it as units, or set its "units per box" in Inventory first.');
    }

    return $quantity * (int)$units_per_box;
}

/**
 * Profit margin percentage from a buying/selling pair.
 * Returns null when the buying price is zero (margin undefined).
 */
function margin_percent(float $buying_price, float $selling_price): ?float
{
    if ($buying_price <= 0) {
        return null;
    }
    return (($selling_price - $buying_price) / $buying_price) * 100;
}

/**
 * Generate a unique SKU from a product name (for products created on import).
 * Products are one shared catalog now, so uniqueness is checked globally.
 */
function generate_sku(PDO $pdo, string $name): string
{
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 6)));
    if ($base === '') $base = 'PROD';
    $check = $pdo->prepare("SELECT 1 FROM products WHERE sku = :s");
    do {
        $sku = $base . '-' . mt_rand(1000, 9999);
        $check->execute(['s' => $sku]);
    } while ($check->fetchColumn() !== false);
    return $sku;
}

/**
 * Resolve a product by id or name; create it if it does not exist yet.
 * Used by the Excel-style bulk importer so a pasted/imported price list
 * "just works" even when it contains brand-new products. Products are a
 * single shared catalog, so lookups are global (not scoped to a branch).
 *
 * @param int  $created_under_branch_id  branch to stamp on a brand-new row
 *                                       (informational only — new products
 *                                       default to `shared = 1`, sellable
 *                                       at either branch, unless an admin
 *                                       later marks them exclusive)
 * @param bool $was_created  set by-reference to true if a new product was made
 * @return int the resolved product id
 */
function resolve_or_create_product(
    PDO $pdo,
    int $created_under_branch_id,
    ?int $product_id,
    string $name,
    float $selling_price,
    float $buying_price,
    bool &$was_created = false
): int {
    $was_created = false;

    // 1. Explicit id wins (the grid sends it when she picks a known product).
    if ($product_id) {
        $s = $pdo->prepare("SELECT id FROM products WHERE id = :id");
        $s->execute(['id' => $product_id]);
        $found = $s->fetchColumn();
        if ($found !== false) return (int)$found;
    }

    // 2. Match by name (case-insensitive) across the whole shared catalog.
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Product name is required.');
    }
    $s = $pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(:n) LIMIT 1");
    $s->execute(['n' => $name]);
    $found = $s->fetchColumn();
    if ($found !== false) return (int)$found;

    // 3. Create it (no stock yet — log_restock / price update handles the rest).
    $sku = generate_sku($pdo, $name);
    $ins = $pdo->prepare(
        "INSERT INTO products (branch_id, shared, sku, name, category, price, buying_price, selling_price, stock)
         VALUES (:bid, 1, :sku, :name, 'Imported', :price, :buy, :sell, 0)"
    );
    $ins->execute([
        'bid'   => $created_under_branch_id,
        'sku'   => $sku,
        'name'  => $name,
        'price' => $selling_price,
        'buy'   => $buying_price,
        'sell'  => $selling_price,
    ]);
    $was_created = true;
    return (int)$pdo->lastInsertId();
}

/**
 * Re-price a product everywhere WITHOUT adding stock (no batch).
 * Keeps products.price == selling_price so the POS register, inventory
 * and dashboards all reflect the new price immediately.
 */
function update_product_prices(PDO $pdo, int $product_id, float $buying_price, float $selling_price): void
{
    $s = $pdo->prepare(
        "UPDATE products
            SET buying_price = :buy, selling_price = :sell, price = :sell2
          WHERE id = :id"
    );
    $s->execute(['buy' => $buying_price, 'sell' => $selling_price, 'sell2' => $selling_price, 'id' => $product_id]);
}
