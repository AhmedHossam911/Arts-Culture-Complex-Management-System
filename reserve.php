<?php
require_once 'includes/config.php';


if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('login.php');
}


$userId = $_SESSION['user_id'];
$pageTitle = 'Reserve Theater Hall';
$error = '';
$success = '';


$db = new DB();
$halls = [];
$result = $db->Connection->query("SELECT id, name, capacity FROM theater_halls WHERE is_active = 1");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $halls[] = $row;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $eventName = trim($_POST['event_name'] ?? '');
        $eventDescription = trim($_POST['event_description'] ?? '');
        $hallId = (int)($_POST['hall_id'] ?? 0);
        $eventDate = trim($_POST['event_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');

        
        if (empty($eventName) || empty($eventDate) || empty($startTime) || empty($endTime) || $hallId <= 0) {
            $error = 'All fields are required.';
        } else {
            $startDatetime = date('Y-m-d H:i:s', strtotime("$eventDate $startTime"));
            $endDatetime = date('Y-m-d H:i:s', strtotime("$eventDate $endTime"));

            
            if (strtotime($endDatetime) <= strtotime($startDatetime)) {
                $error = 'End time must be after start time.';
            } else {
                
                $stmt = $db->Connection->prepare("
                    SELECT id FROM reservations 
                    WHERE theater_hall_id = ? 
                    AND status IN ('pending', 'reserved', 'confirmed')
                    AND (
                        (start_datetime < ? AND end_datetime > ?)
                        OR (start_datetime < ? AND end_datetime > ?)
                        OR (start_datetime >= ? AND end_datetime <= ?)
                    )
                    LIMIT 1
                ");

                $stmt->bind_param(
                    'issssss',
                    $hallId,
                    $endDatetime,
                    $startDatetime,
                    $startDatetime,
                    $endDatetime,
                    $startDatetime,
                    $endDatetime
                );
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'The selected time slot is not available. Please choose a different time or hall.';
                } else {
                    
                    $stmt = $db->Connection->prepare("
                        INSERT INTO reservations 
                        (user_id, theater_hall_id, event_name, event_description, start_datetime, end_datetime, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->bind_param(
                        'iissss',
                        $_SESSION['user_id'],
                        $hallId,
                        $eventName,
                        $eventDescription,
                        $startDatetime,
                        $endDatetime
                    );

                    if ($stmt->execute()) {
                        $success = 'Your reservation request has been submitted successfully and is pending approval.';
                        
                        $_POST = [];
                    } else {
                        $error = 'Failed to create reservation. Please try again.';
                    }
                }
            }
        }
    }
}


require_once 'includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card rounded-3 mb-4">
                <div class="card-header bg-primary text-white rounded-top">
                    <h2 class="h4 mb-0">Reserve Theater Hall</h2>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form action="reserve.php" method="POST" id="reservationForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-4">
                            <h4 class="mb-3">Event Details</h4>

                            <div class="mb-3">
                                <label for="event_name" class="form-label">Event Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="event_name" name="event_name"
                                    value="<?php echo htmlspecialchars($_POST['event_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="event_description" class="form-label">Event Description</label>
                                <textarea class="form-control" id="event_description" name="event_description"
                                    rows="3"><?php echo htmlspecialchars($_POST['event_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="event_date" name="event_date"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                        value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time"
                                        min="08:00" max="22:00" step="1800"
                                        value="<?php echo htmlspecialchars($_POST['start_time'] ?? '09:00'); ?>" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time"
                                        min="08:00" max="23:00" step="1800"
                                        value="<?php echo htmlspecialchars($_POST['end_time'] ?? '12:00'); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="hall_id" class="form-label">Select Hall <span class="text-danger">*</span></label>
                                <select class="form-select" id="hall_id" name="hall_id" required>
                                    <option value="">-- Select a hall --</option>
                                    <?php foreach ($halls as $hall): ?>
                                        <option value="<?php echo $hall['id']; ?>"
                                            <?php echo (isset($_POST['hall_id']) && $_POST['hall_id'] == $hall['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hall['name']); ?> (Capacity: <?php echo $hall['capacity']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">Reset</button>
                            <button type="submit" class="btn btn-primary">Submit Reservation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active Reservations -->
<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card rounded-3">
                <div class="card-header bg-primary text-white rounded-top">
                    <h4 class="h5 mb-0">Your Active Reservations</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Hall</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                
                                $query = "
                                SELECT r.*, th.name as hall_name 
                                FROM reservations r
                                JOIN theater_halls th ON r.theater_hall_id = th.id
                                WHERE r.user_id = ? 
                                AND r.status != 'cancelled'
                                AND r.end_datetime >= NOW()
                                ORDER BY r.start_datetime ASC
                            ";

                                
                                error_log("Active Reservations Query: " . $query);
                                error_log("User ID: " . $userId);

                                $stmt = $db->Connection->prepare($query);
                                if ($stmt === false) {
                                    error_log("Prepare failed: " . $db->Connection->error);
                                }

                                $bindResult = $stmt->bind_param('i', $userId);
                                if ($bindResult === false) {
                                    error_log("Bind param failed: " . $stmt->error);
                                }

                                $executeResult = $stmt->execute();
                                if ($executeResult === false) {
                                    error_log("Execute failed: " . $stmt->error);
                                }

                                $result = $stmt->get_result();
                                if ($result === false) {
                                    error_log("Get result failed: " . $stmt->error);
                                }

                                
                                error_log("Number of active reservations found: " . $result->num_rows);

                                if ($result->num_rows > 0) {
                                    while ($reservation = $result->fetch_assoc()) {
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'reserved' => 'info',
                                            'confirmed' => 'success'
                                        ][$reservation['status']] ?? 'secondary';
                                ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reservation['event_name']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($reservation['start_datetime'])); ?></td>
                                            <td>
                                                <?php
                                                echo date('g:i A', strtotime($reservation['start_datetime'])) . ' - ' .
                                                    date('g:i A', strtotime($reservation['end_datetime']));
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($reservation['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($reservation['status'] !== 'confirmed'): ?>
                                                    <button class="btn btn-sm btn-outline-primary edit-reservation"
                                                        data-id="<?php echo $reservation['id']; ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger cancel-reservation"
                                                    data-id="<?php echo $reservation['id']; ?>">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            </td>
                                        </tr>
                                <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">You have no active reservations.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Canceled Reservations -->
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card rounded-3">
                    <div class="card-header bg-secondary text-white rounded-top">
                        <h5 class="mb-0">Your Canceled Reservations</h5>
                    </div>
                    <div class="card-body p-0">

                        <div class="table-responsive p-3">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Hall</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $userId = $_SESSION['user_id'];
                                    
                                    $stmt = $db->Connection->prepare("\n                                SELECT r.*, th.name as hall_name \n                                FROM reservations r\n                                JOIN theater_halls th ON r.theater_hall_id = th.id\n                                WHERE r.user_id = ? \n                                AND r.status = 'cancelled'\n                                ORDER BY r.start_datetime ASC\n                            ");
                                    $stmt->bind_param('i', $userId);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result->num_rows > 0) {
                                        while ($reservation = $result->fetch_assoc()) {
                                            $canEdit = strtotime($reservation['start_datetime']) > (time() + (2 * 24 * 60 * 60));
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'reserved' => 'info',
                                                'confirmed' => 'success',
                                                'cancelled' => 'secondary'
                                            ][$reservation['status']] ?? 'secondary';
                                    ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reservation['event_name']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($reservation['start_datetime'])); ?></td>
                                                <td>
                                                    <?php
                                                    echo date('g:i A', strtotime($reservation['start_datetime'])) . ' - ' .
                                                        date('g:i A', strtotime($reservation['end_datetime']));
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($reservation['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">You have no upcoming reservations.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Reservations -->
        <div class="container my-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card rounded-3">
                        <div class="card-header bg-primary text-white rounded-top">
                            <h5 class="mb-0">Completed Reservations</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            
                            $completedQuery = "
                                SELECT r.*, th.name as hall_name, 
                                       DATE_FORMAT(r.start_datetime, '%Y-%m-%d') as event_date,
                                       DATE_FORMAT(r.start_datetime, '%H:%i') as start_time,
                                       DATE_FORMAT(r.end_datetime, '%H:%i') as end_time
                                FROM reservations r
                                JOIN theater_halls th ON r.theater_hall_id = th.id
                                WHERE r.user_id = ? 
                                AND r.end_datetime < NOW()
                                ORDER BY r.start_datetime DESC
                                LIMIT 10
                            ";
                            $stmt = $db->Connection->prepare($completedQuery);
                            $stmt->bind_param('i', $userId);
                            $stmt->execute();
                            $completedReservations = $stmt->get_result();

                            if ($completedReservations->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Hall</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($reservation = $completedReservations->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($reservation['event_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['event_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['start_time'] . ' - ' . $reservation['end_time']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php
                                                                                echo $reservation['status'] === 'confirmed' ? 'success' : ($reservation['status'] === 'cancelled' ? 'danger' : 'warning');
                                                                                ?>">
                                                            <?php echo ucfirst(htmlspecialchars($reservation['status'])); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($completedReservations->num_rows == 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Hall</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <td colspan="6" class="text-center">
                                            No completed reservations found.
                                        </td>
                                    </table>
                                <?php endif; ?>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Reservation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Reservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this reservation? This action cannot be undone.</p>
                    <input type="hidden" id="cancelReservationId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel Reservation</button>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.getElementById('reservationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const startTime = document.getElementById('start_time').value;
                    const endTime = document.getElementById('end_time').value;

                    if (startTime >= endTime) {
                        e.preventDefault();
                        alert('End time must be after start time.');
                        return false;
                    }

                    const startHour = parseInt(startTime.split(':')[0]);
                    const endHour = parseInt(endTime.split(':')[0]);

                    if (startHour < 8 || endHour > 22) {
                        e.preventDefault();
                        alert('Reservations are only allowed between 8:00 AM and 10:00 PM.');
                        return false;
                    }

                    return true;
                });
            }

            // Handle cancel reservation
            const cancelButtons = document.querySelectorAll('.cancel-reservation');
            const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
            const cancelReservationId = document.getElementById('cancelReservationId');

            cancelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-id');
                    cancelReservationId.value = reservationId;
                    cancelModal.show();
                });
            });

            document.getElementById('confirmCancel').addEventListener('click', function() {
                const reservationId = cancelReservationId.value;
                if (!reservationId) return;

                fetch('api/cancel_reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            reservation_id: reservationId,
                            csrf_token: document.querySelector('input[name="csrf_token"]').value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to cancel reservation');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request.');
                    })
                    .finally(() => {
                        cancelModal.hide();
                    });
            });

            // Handle edit reservation
            const editButtons = document.querySelectorAll('.edit-reservation');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Implementation for edit would be similar to cancel
                    alert('Edit functionality will be implemented in the next update.');
                });
            });
        });
    </script>