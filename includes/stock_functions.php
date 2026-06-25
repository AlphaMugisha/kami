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
    int $product_id,
    int $quantity,
    float $buying_price,
    float $selling_price,
    ?string $purchased_at,
    ?string $notes,
    int $user_id
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
            (product_id, quantity_bought, quantity_remaining, buying_price, selling_price, purchased_at, notes, created_by)
         VALUES
            (:pid, :qty, :qty2, :buy, :sell, :purchased_at, :notes, :uid)"
    );
    $stmt->execute([
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
function deduct_stock_fifo(int $product_id, int $quantity, PDO $pdo): array
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Deduction quantity must be greater than zero.');
    }

    $stmt = $pdo->prepare(
        "SELECT id, quantity_remaining, buying_price
           FROM stock_batches
          WHERE product_id = :pid AND quantity_remaining > 0
          ORDER BY purchased_at ASC, id ASC"
    );
    $stmt->execute(['pid' => $product_id]);
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

    // Graceful fallback for stock not represented by any batch.
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
