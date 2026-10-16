<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/rating-common.php';

ensureUserRatingsTable($pdo);

$reviewerId = requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'POST request required.', [], 405);
}

$purchaseId = (int) ($_POST['purchase_id'] ?? 0);
$revieweeId = (int) ($_POST['reviewee_id'] ?? 0);
$ratingInput = (string) ($_POST['rating'] ?? '');

if ($purchaseId <= 0 || $revieweeId <= 0 || !preg_match('/^[1-5]$/', $ratingInput)) {
    jsonResponse(false, 'Choose a purchase and a rating from 1 to 5.', [], 422);
}
if ($revieweeId === $reviewerId) {
    jsonResponse(false, 'You cannot rate your own profile.', [], 403);
}
try {
    $pdo->beginTransaction();

    $purchaseStmt = $pdo->prepare(
        'SELECT buyer_id, seller_id, listing_id
         FROM purchases WHERE id = ? FOR UPDATE'
    );
    $purchaseStmt->execute([$purchaseId]);
    $purchase = $purchaseStmt->fetch();

    if (
        !$purchase ||
        (int) $purchase['buyer_id'] !== $reviewerId ||
        (int) $purchase['seller_id'] !== $revieweeId
    ) {
        $pdo->rollBack();
        jsonResponse(false, 'Only the buyer can rate the seller for a completed exchange.', [], 403);
    }

    $existingStmt = $pdo->prepare(
        'SELECT id FROM user_ratings WHERE purchase_id = ? AND reviewer_id = ? LIMIT 1'
    );
    $existingStmt->execute([$purchaseId, $reviewerId]);
    if ($existingStmt->fetchColumn()) {
        $pdo->rollBack();
        jsonResponse(false, 'You have already rated this purchase.', [], 409);
    }

    $promptStmt = $pdo->prepare(
        "SELECT id FROM messages
         WHERE sender_id = ? AND receiver_id = ? AND listing_id = ? AND message = ?
         LIMIT 1 FOR UPDATE"
    );
    $promptStmt->execute([
        $revieweeId,
        $reviewerId,
        (int) $purchase['listing_id'],
        '[[RESOURCE_RATE_SELLER:' . $purchaseId . ']]',
    ]);
    $promptMessageId = $promptStmt->fetchColumn();
    if (!$promptMessageId) {
        $pdo->rollBack();
        jsonResponse(false, 'Rate the seller from the completed exchange message.', [], 403);
    }

    $insertStmt = $pdo->prepare(
           'INSERT INTO user_ratings (purchase_id, reviewer_id, reviewee_id, rating)
            VALUES (?, ?, ?, ?)'
    );
    $insertStmt->execute([
        $purchaseId,
        $reviewerId,
        $revieweeId,
        (int) $ratingInput,
    ]);

    $ratedMessage = $pdo->prepare('UPDATE messages SET message = ? WHERE id = ?');
    $ratedMessage->execute([
        '[[RESOURCE_SELLER_RATED:' . $purchaseId . ']]',
        (int) $promptMessageId,
    ]);

    $pdo->commit();
    jsonResponse(true, 'Rating submitted.');
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error->getCode() === '23000') {
        jsonResponse(false, 'You have already rated this purchase.', [], 409);
    }
    error_log('Rating submission failed: ' . $error->getMessage());
    jsonResponse(false, 'Unable to submit rating right now.', [], 500);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Rating submission failed: ' . $error->getMessage());
    jsonResponse(false, 'Unable to submit rating right now.', [], 500);
}