<?php
require_once '../includes/config.php';

header('Content-Type: application/json');


if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}


$input = json_decode(file_get_contents('php://input'), true);


if (!isset($input['reservation_id']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}


if (!verifyCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$reservationId = (int)$input['reservation_id'];
$userId = $_SESSION['user_id'];

try {
    $db = new DB();
    
    
    $db->Connection->begin_transaction();
    
    
    $stmt = $db->Connection->prepare("
        SELECT * FROM reservations 
        WHERE id = ? AND user_id = ?
        FOR UPDATE
    
    ");
    $stmt->bind_param('ii', $reservationId, $userId);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    
    if (!$reservation) {
        throw new Exception('Reservation not found or access denied');
    }
    
    
    $reservationTime = strtotime($reservation['start_datetime']);
    $currentTime = time();
    $twoDaysInSeconds = 2 * 24 * 60 * 60;
    
    if ($reservationTime - $currentTime <= $twoDaysInSeconds) {
        throw new Exception('Reservation can only be cancelled more than 2 days before the event');
    }
    
    if ($reservation['status'] === 'cancelled') {
        throw new Exception('Reservation is already cancelled');
    }
    
    if ($reservation['status'] === 'confirmed') {
        throw new Exception('Confirmed reservations cannot be cancelled');
    }
    
    
    $stmt = $db->Connection->prepare("
        UPDATE reservations 
        SET status = 'cancelled', updated_at = NOW()
        WHERE id = ? AND user_id = ?
    
    ");
    $stmt->bind_param('ii', $reservationId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel reservation');
    }
    
    
    $stmt = $db->Connection->prepare("
        INSERT INTO audit_logs 
        (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (?, ?, 'reservations', ?, ?, ?)
    
    ");
    $oldValues = json_encode($reservation);
    $newValues = json_encode(['status' => 'cancelled']);
    $action = 'cancel_reservation';
    
    $stmt->bind_param('isiss', $userId, $action, $reservationId, $oldValues, $newValues);
    $stmt->execute();
    
    
    $db->Connection->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reservation has been cancelled successfully'
    ]);
    
} catch (Exception $e) {
    
    if (isset($db) && $db->Connection) {
        $db->Connection->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
