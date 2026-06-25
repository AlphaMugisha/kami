<?php
/* ================================================================
   OZONE · process_restock.php
   ----------------------------------------------------------------
   POST endpoint that records a restock batch ("kurungura") and the
   batch-price corrections. All writes run inside a PDO transaction.

   Two response modes:
     - AJAX  (field ajax=1, e.g. the inventory quick-restock modal)
               -> returns JSON
     - Form  (normal submit from restock.php)
               -> redirects back to restock.php?status=...&msg=...

   Actions:
     (default)            log a new restock batch
     action=edit_batch    correct buying/selling price on a batch
   ================================================================ */

declare(strict_types=1);
session_start();

require_once '../config/db.php';
require_once '../includes/stock_functions.php';

$is_ajax = (($_POST['ajax'] ?? '') === '1');

/* ---- helper: respond + stop (JSON for ajax, redirect for forms) ---- */
function respond(bool $ok, string $msg, array $extra = []): void
{
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    } else {
        $status = $ok ? 'ok' : 'error';
        header('Location: restock.php?status=' . $status . '&msg=' . urlencode($msg));
    }
    exit();
}

/* ---- auth: admin only ---- */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(false, 'Unauthorized — admin access required.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? 'restock';

/* ================================================================
   ACTION: edit_batch — correct a data-entry mistake on a batch.
   Re-syncs the product cache only if this is still the latest batch.
   ================================================================ */
if ($action === 'edit_batch') {
    $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
    $buying   = filter_input(INPUT_POST, 'buying_price', FILTER_VALIDATE_FLOAT);
    $selling  = filter_input(INPUT_POST, 'selling_price', FILTER_VALIDATE_FLOAT);

    if (!$batch_id || $buying === false || $selling === false || $buying < 0 || $selling < 0) {
        respond(false, 'Please provide a valid batch and non-negative prices.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT product_id FROM stock_batches WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $batch_id]);
        $product_id = $stmt->fetchColumn();
        if ($product_id === false) {
            throw new RuntimeException('Batch not found.');
        }
        $product_id = (int)$product_id;

        $stmt = $pdo->prepare("UPDATE stock_batches SET buying_price = :buy, selling_price = :sell WHERE id = :id");
        $stmt->execute(['buy' => $buying, 'sell' => $selling, 'id' => $batch_id]);

        // If this batch is the most recent one for the product, refresh the cache.
        $stmt = $pdo->prepare(
            "SELECT id FROM stock_batches WHERE product_id = :pid ORDER BY purchased_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute(['pid' => $product_id]);
        $latest_id = (int)$stmt->fetchColumn();

        if ($latest_id === $batch_id) {
            $stmt = $pdo->prepare(
                "UPDATE products SET buying_price = :buy, selling_price = :sell, price = :sell2 WHERE id = :pid"
            );
            $stmt->execute(['buy' => $buying, 'sell' => $selling, 'sell2' => $selling, 'pid' => $product_id]);
        }

        $pdo->commit();
        respond(true, 'Batch prices corrected successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(false, 'Could not update batch: ' . $e->getMessage());
    }
}

/* ================================================================
   ACTION: batch_save — the Excel-style grid / import.
   Accepts a JSON array of rows and commits them atomically:
     - qty > 0  -> full restock (new batch, stock +, price sync)
     - qty <= 0 -> price update only (re-price everywhere, no batch)
   Unknown product names are auto-created.
   ================================================================ */
if ($action === 'batch_save') {
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows) || count($rows) === 0) {
        respond(false, 'There are no rows to save.');
    }

    $restocked = 0; $repriced = 0; $created = 0;

    try {
        $pdo->beginTransaction();

        foreach ($rows as $i => $r) {
            $name  = trim((string)($r['product'] ?? ''));
            $pidIn = (isset($r['product_id']) && $r['product_id']) ? (int)$r['product_id'] : null;
            $qty   = (int)($r['qty'] ?? 0);
            $buy   = (float)($r['buying_price'] ?? 0);
            $sell  = (float)($r['selling_price'] ?? 0);

            // Silently skip completely blank rows (trailing grid rows).
            if ($name === '' && $qty <= 0 && $buy <= 0 && $sell <= 0) {
                continue;
            }

            $line = $i + 1;
            if ($name === '') {
                throw new RuntimeException("Row {$line}: product name is missing.");
            }
            if ($sell <= 0) {
                throw new RuntimeException("Row {$line} ({$name}): a selling price is required.");
            }
            if ($buy < 0 || $qty < 0) {
                throw new RuntimeException("Row {$line} ({$name}): prices and quantity cannot be negative.");
            }

            $was_created = false;
            $pid = resolve_or_create_product($pdo, $pidIn, $name, $sell, $buy, $was_created);
            if ($was_created) $created++;

            if ($qty > 0) {
                log_restock($pdo, $pid, $qty, $buy, $sell, null, 'Bulk grid entry', $user_id);
                $restocked++;
            } else {
                update_product_prices($pdo, $pid, $buy, $sell);
                $repriced++;
            }
        }

        if ($restocked === 0 && $repriced === 0) {
            throw new RuntimeException('No usable rows found — please fill in at least one product.');
        }

        $pdo->commit();

        $parts = [];
        if ($restocked) $parts[] = "$restocked restock" . ($restocked === 1 ? '' : 's');
        if ($repriced)  $parts[] = "$repriced price update" . ($repriced === 1 ? '' : 's');
        if ($created)   $parts[] = "$created new product" . ($created === 1 ? '' : 's');
        respond(true, 'Saved: ' . implode(', ', $parts) . '.', [
            'restocked' => $restocked, 'repriced' => $repriced, 'created' => $created,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(false, 'Nothing was saved. ' . $e->getMessage());
    }
}

/* ================================================================
   ACTION: restock (default) — log a new purchase batch.
   ================================================================ */
$product_id   = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity     = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$buying       = filter_input(INPUT_POST, 'buying_price', FILTER_VALIDATE_FLOAT);
$selling      = filter_input(INPUT_POST, 'selling_price', FILTER_VALIDATE_FLOAT);
$purchased_at = $_POST['purchased_at'] ?? null;
$notes        = $_POST['notes'] ?? null;

if (!$product_id || !$quantity || $quantity <= 0 || $buying === false || $selling === false || $buying < 0 || $selling < 0) {
    respond(false, 'Please fill in a product, a positive quantity, and valid prices.');
}

try {
    $pdo->beginTransaction();

    // Guard: product must exist.
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product_name = $stmt->fetchColumn();
    if ($product_name === false) {
        throw new RuntimeException('Selected product does not exist.');
    }

    $batch_id = log_restock($pdo, $product_id, $quantity, (float)$buying, (float)$selling, $purchased_at, $notes, $user_id);

    // Read back the fresh cached values for the UI.
    $stmt = $pdo->prepare("SELECT stock, buying_price, selling_price FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $fresh = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    respond(true, "Restock logged: +{$quantity} × {$product_name}.", [
        'batch_id'      => $batch_id,
        'product_id'    => $product_id,
        'new_stock'     => (int)$fresh['stock'],
        'buying_price'  => (float)$fresh['buying_price'],
        'selling_price' => (float)$fresh['selling_price'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(false, 'Restock failed: ' . $e->getMessage());
}
