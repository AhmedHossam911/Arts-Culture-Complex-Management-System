<?php

$basePath = dirname(dirname(__FILE__));
require_once $basePath . '/includes/config.php';


if (!$auth->isAuth() || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}


header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dashboard_export_' . date('Y-m-d') . '.csv');


$output = fopen('php://output', 'w');


fputs($output, "\xEF\xBB\xBF");


$db = new DB();

try {
    
    $stats = [];
    
    
    $result = $db->Connection->query("SELECT COUNT(*) as count FROM users");
    $stats['Total Users'] = $result->fetch_assoc()['count'];
    
    
    $result = $db->Connection->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
    $stats['Pending Approvals'] = $result->fetch_assoc()['count'];
    
    
    $result = $db->Connection->query("SELECT COUNT(*) as count FROM reservations");
    $stats['Total Reservations'] = $result->fetch_assoc()['count'];
    
    
    $result = $db->Connection->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
    $stats['Pending Reservations'] = $result->fetch_assoc()['count'];
    
    
    $result = $db->Connection->query("SELECT COUNT(*) as count FROM theater_halls WHERE is_active = 1");
    $stats['Active Halls'] = $result->fetch_assoc()['count'];
    
    
    fputcsv($output, ['Statistic', 'Count']);
    
    
    foreach ($stats as $key => $value) {
        fputcsv($output, [$key, $value]);
    }
    
    
    fputcsv($output, []);
    fputcsv($output, ['Recent Reservations']);
    
    
    $query = "SELECT r.id, u.username, th.name as hall_name, r.event_name, r.start_datetime, r.end_datetime, r.status 
              FROM reservations r 
              JOIN users u ON r.user_id = u.id 
              JOIN theater_halls th ON r.theater_hall_id = th.id 
              ORDER BY r.created_at DESC 
              LIMIT 10";
    
    $result = $db->Connection->query($query);
    
    if ($result->num_rows > 0) {
        
        fputcsv($output, ['ID', 'User', 'Hall', 'Event', 'Start Date', 'End Date', 'Status']);
        
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['hall_name'],
                $row['event_name'],
                $row['start_datetime'],
                $row['end_datetime'],
                $row['status']
            ]);
        }
    }
    
} catch (Exception $e) {
    
    error_log("Export Error: " . $e->getMessage());
    
    
    fputcsv($output, ['Error', 'Failed to generate export. Please try again later.']);
}

fclose($output);
exit();
