<?php
/**
 * Stock operations. Every quantity change goes through these functions so that
 * batches and the movement log always stay consistent. Call inside a transaction.
 */
function stock_in(PDO $pdo, int $productId, int $qty, ?string $expiry, int $userId, string $reason, ?int $poItemId = null, string $type = 'IN'): int {
    if ($qty <= 0) throw new InvalidArgumentException('Quantity must be greater than zero.');
    $st = $pdo->prepare('INSERT INTO stock_batches (product_id, po_item_id, quantity_on_hand, date_received, expiry_date) VALUES (?,?,?,CURDATE(),?)');
    $st->execute([$productId, $poItemId, $qty, $expiry ?: null]);
    $batchId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, type, quantity, reason) VALUES (?,?,?,?,?,?)')
        ->execute([$productId, $batchId, $userId, $type, $qty, $reason ?: null]);
    return $batchId;
}

/** Remove stock first-expiry-first-out. Throws if there is not enough stock. */
function stock_out(PDO $pdo, int $productId, int $qty, string $type, int $userId, string $reason, ?string $movedAt = null): void {
    if ($qty <= 0) throw new InvalidArgumentException('Quantity must be greater than zero.');
    $st = $pdo->prepare('SELECT batch_id, quantity_on_hand FROM stock_batches
                         WHERE product_id = ? AND quantity_on_hand > 0
                         ORDER BY (expiry_date IS NULL), expiry_date, date_received, batch_id FOR UPDATE');
    $st->execute([$productId]);
    $batches = $st->fetchAll();
    $available = array_sum(array_column($batches, 'quantity_on_hand'));
    if ($available < $qty) throw new RuntimeException("Not enough stock. Only $available available.");
    $remaining = $qty;
    $upd = $pdo->prepare('UPDATE stock_batches SET quantity_on_hand = quantity_on_hand - ? WHERE batch_id = ?');
    $mov = $pdo->prepare('INSERT INTO stock_movements (product_id, batch_id, user_id, type, quantity, reason, moved_at) VALUES (?,?,?,?,?,?,COALESCE(?, NOW()))');
    foreach ($batches as $b) {
        if ($remaining <= 0) break;
        $take = min($remaining, (int)$b['quantity_on_hand']);
        $upd->execute([$take, $b['batch_id']]);
        $mov->execute([$productId, $b['batch_id'], $userId, $type, -$take, $reason ?: null, $movedAt]);
        $remaining -= $take;
    }
}
