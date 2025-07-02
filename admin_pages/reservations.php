<?php

$basePath = dirname(dirname(__FILE__));
require_once $basePath . '/includes/config.php';


$auth = new Auth();


if (!$auth->isAuth() || !isAdmin()) {
    redirect('index.php');
}

$pageTitle = 'Manage Reservations';
$db = new DB();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reservation_id'])) {
    $reservationId = (int)$_POST['reservation_id'];
    $action = $_POST['action'];

    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('admin_pages/reservations.php');
    }

    $allowedActions = ['confirm', 'cancel'];
    if (!in_array($action, $allowedActions)) {
        $_SESSION['error'] = 'Invalid action.';
        redirect('admin_pages/reservations.php');
    }

    try {
        
        $stmt = $db->Connection->prepare("SELECT status FROM reservations WHERE id = ?");
        $stmt->bind_param('i', $reservationId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Reservation not found.');
        }

        $reservation = $result->fetch_assoc();

        
        if ($reservation['status'] === 'cancelled') {
            throw new Exception('Cannot modify a cancelled reservation.');
        }

        
        $newStatus = $action === 'confirm' ? 'confirmed' : 'cancelled';

        $stmt = $db->Connection->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $reservationId);

        if ($stmt->execute()) {
            
            $logMessage = $action === 'confirm'
                ? "Reservation #$reservationId confirmed by admin"
                : "Reservation #$reservationId cancelled by admin";

            $stmt = $db->Connection->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
                VALUES (?, ?, 'reservations', ?, ?, ?)
            
            ");
            $userId = $_SESSION['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $actionType = $action === 'confirm' ? 'reservation_confirmed' : 'reservation_cancelled';

            $stmt->bind_param('isiss', $userId, $actionType, $reservationId, $ip, $userAgent);
            $stmt->execute();

            $_SESSION['success'] = "Reservation has been " . ($action === 'confirm' ? 'confirmed' : 'cancelled') . " successfully.";
        } else {
            throw new Exception('Failed to update reservation status.');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    redirect('admin_pages/reservations.php');
}


$status = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$hall_id = $_GET['hall_id'] ?? 'all';


$query = "
    SELECT r.*, u.username, u.full_name, u.email, th.name as hall_name, th.id as hall_id 
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN theater_halls th ON r.theater_hall_id = th.id
    WHERE 1=1
";

$params = [];
$types = '';


if ($status !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status;
    $types .= 's';
}


if (!empty($start_date)) {
    $query .= " AND DATE(r.start_datetime) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $query .= " AND DATE(r.end_datetime) <= ?";
    $params[] = $end_date;
    $types .= 's';
}


if ($hall_id !== 'all') {
    $query .= " AND th.id = ?";
    $params[] = $hall_id;
    $types .= 'i';
}


$query .= " ORDER BY r.start_datetime DESC";


$halls = [];
$hallsQuery = "SELECT id, name FROM theater_halls WHERE is_active = 1 ORDER BY name";
$hallsResult = $db->Connection->query($hallsQuery);
if ($hallsResult) {
    while ($row = $hallsResult->fetch_assoc()) {
        $halls[] = $row;
    }
}


$reservations = [];
$stmt = $db->Connection->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
}


require_once $basePath . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Manage Reservations</h1>
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
            <i class="bi bi-funnel"></i> Filters
        </button>
    </div>

    <!-- Filters Section -->
    <div class="collapse mb-4" id="filtersCollapse">
        <div class="card">
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="hall_id" class="form-label">Hall</label>
                        <select class="form-select" id="hall_id" name="hall_id">
                            <option value="all">All Halls</option>
                            <?php foreach ($halls as $hall): ?>
                                <option value="<?php echo $hall['id']; ?>" <?php echo (isset($hall_id) && $hall_id == $hall['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hall['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">To Date</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Apply
                            </button>
                            <?php if ($status !== 'all' || !empty($start_date) || !empty($end_date) || $hall_id !== 'all'): ?>
                                <a href="reservations.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            echo htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php
            echo htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Reservations</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event</th>
                            <th>User</th>
                            <th>Hall</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No reservations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation):
                                $statusClass = [
                                    'pending' => 'warning',
                                    'reserved' => 'info',
                                    'confirmed' => 'success',
                                    'cancelled' => 'secondary'
                                ][$reservation['status']] ?? 'secondary';

                                $isPast = strtotime($reservation['end_datetime']) < time();
                            ?>
                                <tr class="<?php echo $isPast ? 'table-secondary' : ''; ?>">
                                    <td>#<?php echo $reservation['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($reservation['event_name']); ?></strong>
                                        <?php if (!empty($reservation['event_description'])): ?>
                                            <div class="text-muted small"><?php echo nl2br(htmlspecialchars(substr($reservation['event_description'], 0, 50) . (strlen($reservation['event_description']) > 50 ? '...' : ''))); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($reservation['full_name']); ?>
                                        <div class="text-muted small">@<?php echo htmlspecialchars($reservation['username']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($reservation['start_datetime'])); ?></div>
                                        <div class="text-muted small">
                                            <?php echo date('g:i A', strtotime($reservation['start_datetime'])); ?> -
                                            <?php echo date('g:i A', strtotime($reservation['end_datetime'])); ?>
                                        </div>
                                        <?php if ($isPast): ?>
                                            <span class="badge bg-secondary">Past Event</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($reservation['status'] !== 'cancelled'): ?>
                                                <?php if ($reservation['status'] !== 'confirmed'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to confirm this reservation?');">
                                                        <input type="hidden" name="action" value="confirm">
                                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success"
                                                            <?php echo $isPast ? 'disabled title="Cannot modify past events"' : ''; ?>>
                                                            <i class="bi bi-check-lg"></i> Confirm
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger ms-1"
                                                        <?php echo $isPast ? 'disabled title="Cannot modify past events"' : ''; ?>>
                                                        <i class="bi bi-x-lg"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php

require_once $basePath . '/includes/footer.php';
?>