<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define root path
$root = dirname(__DIR__);

// Include configuration and database connection
require_once $root . '/includes/config.php';
require_once $root . '/includes/Database.php';

// Initialize database connection
$db = new DB();
$conn = $db->Connection;

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Include Auth class and initialize
require_once $root . '/includes/Auth.php';
$auth = new Auth($pdo);

// Check if user is logged in and is admin
if (!$auth->isAuth() || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . $root . '/index.php');
    exit;
}

// Include common functions
require_once $root . '/includes/functions.php';

// Helper function to set alert messages
function set_alert($type, $message) {
    if (!isset($_SESSION['alerts'])) {
        $_SESSION['alerts'] = [];
    }
    $_SESSION['alerts'][] = [
        'type' => $type,
        'message' => $message
    ];
}

// Helper function to display alerts
function display_alerts() {
    if (!empty($_SESSION['alerts'])) {
        foreach ($_SESSION['alerts'] as $alert) {
            echo '<div class="alert alert-' . htmlspecialchars($alert['type']) . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($alert['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        // Clear the alerts after displaying them
        unset($_SESSION['alerts']);
    }
}

// Helper function to redirect
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_alert('danger', 'Invalid user ID.');
    redirect('users.php');
}

$user_id = (int)$_GET['id'];
$page_title = 'View User';

// Get user details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        set_alert('danger', 'User not found.');
        redirect('users.php');
    }
    
    // Get user's recent reservations
    $stmt = $conn->prepare("
        SELECT r.*, th.name as hall_name 
        FROM reservations r
        JOIN theater_halls th ON r.hall_id = th.id
        WHERE r.user_id = ?
        ORDER BY r.start_datetime DESC
        LIMIT 5
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    
    // Get reservation statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM reservations 
        WHERE user_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation_stats = $result->fetch_assoc();
    
} catch (Exception $e) {
    error_log('Error in user view: ' . $e->getMessage());
    set_alert('danger', 'An error occurred while loading user data.');
    redirect('users.php');
}

// Handle admin notes update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notes') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
            throw new Exception('Invalid user ID');
        }
        
        $user_id = (int)$_POST['user_id'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        // Verify the current user is an admin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            throw new Exception('Unauthorized access');
        }
        
        // Update the notes
        $stmt = $conn->prepare("UPDATE users SET admin_notes = ? WHERE id = ?");
        $stmt->bind_param('si', $admin_notes, $user_id);
        $success = $stmt->execute();
        
        if ($success) {
            $response['success'] = true;
            $response['message'] = 'Notes updated successfully';
            // Update the user data in the session if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['admin_notes'] = $admin_notes;
            }
        } else {
            throw new Exception('Failed to update notes');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        http_response_code(400);
    }
    
    // If it's an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // For non-AJAX requests, set session message
    if ($response['success']) {
        set_alert('success', $response['message']);
    } else {
        set_alert('danger', $response['message']);
    }
    
    // Redirect back to the same page
    redirect('user-view.php?id=' . $user_id);
    exit;
}

// Include header
require_once $root . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">User Details</h1>
    
    <?php display_alerts(); ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            User Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%">ID</th>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%">Status</th>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td><?php echo ucfirst($user['role']); ?></td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Last Login</th>
                            <td>
                                <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                <?php if ($user['last_ip']): ?>
                                    <small class="text-muted">(<?php echo htmlspecialchars($user['last_ip']); ?>)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Statistics -->
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Reservations</h5>
                    <h2 class="mb-0"><?php echo $reservation_stats['total'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Confirmed</h5>
                    <h2 class="mb-0"><?php echo $reservation_stats['confirmed'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Pending</h5>
                    <h2 class="mb-0"><?php echo $reservation_stats['pending'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reservations -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-alt me-1"></i>
            Recent Reservations
        </div>
        <div class="card-body">
            <?php if (!empty($reservations)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="reservationsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hall</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo $reservation['id']; ?></td>
                                    <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($reservation['start_datetime'])); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($reservation['end_datetime'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $reservation['status'] === 'confirmed' ? 'success' : 
                                                ($reservation['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="reservation-view.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No reservations found for this user.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Notes -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-sticky-note me-1"></i>
            Admin Notes
        </div>
        <div class="card-body">
            <form id="adminNotesForm" method="post">
                <input type="hidden" name="action" value="update_notes">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <div class="mb-3">
                    <label for="admin_notes" class="form-label">Private notes about this user (visible to admins only)</label>
                    <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($user['admin_notes'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Notes</button>
            </form>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php require_once $root . '/includes/footer.php'; ?>

<!-- JavaScript for Admin Notes Form -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adminNotesForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            fetch('user-view.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.role = 'alert';
                    alert.innerHTML = `
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    
                    // Insert the alert before the form
                    form.parentNode.insertBefore(alert, form);
                    
                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 3000);
                } else {
                    throw new Error(data.message || 'Failed to save notes');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving notes. Please try again.');
            });
        });
    }
});
</script>
