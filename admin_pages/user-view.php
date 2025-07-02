<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$root = dirname(__DIR__);


require_once $root . '/includes/config.php';


$db = new DB();
$pdo = $db->Connection;


$auth = new Auth($pdo);


if (!$auth->isAuth() || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . $root . '/index.php');
    exit;
}


require_once $root . '/includes/functions.php';


function set_alert($type, $message) {
    if (!isset($_SESSION['alerts'])) {
        $_SESSION['alerts'] = [];
    }
    $_SESSION['alerts'][] = [
        'type' => $type,
        'message' => $message
    ];
}




if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_alert('danger', 'Invalid user ID.');
    redirect('users.php');
}

$user_id = (int)$_GET['id'];
$page_title = 'View User';


try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        set_alert('danger', 'User not found.');
        redirect('users.php');
    }
    
    
    $stmt = $pdo->prepare("
        SELECT r.*, th.name as hall_name 
        FROM reservations r
        JOIN theater_halls th ON r.hall_id = th.id
        WHERE r.user_id = ?
        ORDER BY r.start_datetime DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM reservations 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $reservation_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Error in user view: ' . $e->getMessage());
    set_alert('danger', 'An error occurred while loading user data.');
    redirect('users.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notes') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
            throw new Exception('Invalid user ID');
        }
        
        $user_id = (int)$_POST['user_id'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            throw new Exception('Unauthorized access');
        }
        
        
        $stmt = $pdo->prepare("UPDATE users SET admin_notes = ? WHERE id = ?");
        $success = $stmt->execute([$admin_notes, $user_id]);
        
        if ($success) {
            $response['success'] = true;
            $response['message'] = 'Notes updated successfully';
            
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
    
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    
    if ($response['success']) {
        set_alert('success', $response['message']);
    } else {
        set_alert('danger', $response['message']);
    }
    
    
    redirect('user-view.php?id=' . $user_id);
    exit;
}


require_once $root . '/includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid px-4">
    <h1 class="mt-4">User Details</h1>
    
    <?php display_alerts(); ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user me-1"></i>
            Profile Information
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

<?php 

require_once $root . '/includes/footer.php'; 
?>

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

            </a>
        </div>
    </div>
    
    <?php display_alerts(); ?>
    
    <div class="row">
        <!-- User Info -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="avatar-xxl mb-3">
                        <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </span>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                    <p class="text-muted mb-3">@<?php echo htmlspecialchars($user['username']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> fs-6">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <span class="badge bg-<?php 
                            echo $user['status'] === 'active' ? 'success' : 
                                 ($user['status'] === 'pending' ? 'warning' : 'secondary'); 
                        ?> fs-6">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                    
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <?php if ($user['status'] === 'pending'): ?>
                            <form method="post" action="user.php" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this user?');">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm me-2">
                                    <i class="bi bi-check-lg me-1"></i> Approve
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($user['status'] === 'active'): ?>
                            <form method="post" action="user.php" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm me-2">
                                    <i class="bi bi-pause me-1"></i> Suspend
                                </button>
                            </form>
                        <?php elseif ($user['status'] === 'suspended'): ?>
                            <form method="post" action="user.php" class="d-inline" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm me-2">
                                    <i class="bi bi-check-circle me-1"></i> Activate
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="post" action="user.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <h5 class="mb-0"><?php echo $reservation_stats['total'] ?? 0; ?></h5>
                            <small class="text-muted">Total Reservations</small>
                        </div>
                        <div class="col-6">
                            <h5 class="mb-0"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></h5>
                            <small class="text-muted">Member Since</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-envelope me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </li>
                        <?php if (!empty($user['phone'])): ?>
                            <li class="mb-2">
                                <i class="bi bi-telephone me-2"></i>
                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>">
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($user['address'])): ?>
                            <li class="mb-0">
                                <i class="bi bi-geo-alt me-2"></i>
                                <?php echo nl2br(htmlspecialchars($user['address'])); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Username:</th>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email Verified:</th>
                                <td>
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Verified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Login:</th>
                                <td>
                                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Last IP:</th>
                                <td><?php echo $user['last_ip'] ?: 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Created At:</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Updated At:</th>
                                <td><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Reservation Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-start border-success border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Confirmed</h6>
                                    <h3 class="mb-0 text-success"><?php echo $reservation_stats['confirmed'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-success p-3 rounded">
                                    <i class="bi bi-check-circle text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-start border-warning border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending</h6>
                                    <h3 class="mb-0 text-warning"><?php echo $reservation_stats['pending'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-warning p-3 rounded">
                                    <i class="bi bi-hourglass-split text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-start border-danger border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Cancelled</h6>
                                    <h3 class="mb-0 text-danger"><?php echo $reservation_stats['cancelled'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-danger p-3 rounded">
                                    <i class="bi bi-x-circle text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Confirmed</h6>
                                    <h3 class="mb-0 text-success"><?php echo $reservation_stats['confirmed'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-success p-3 rounded">
                                    <i class="bi bi-check-circle text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-start border-warning border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending</h6>
                                    <h3 class="mb-0 text-warning"><?php echo $reservation_stats['pending'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-warning p-3 rounded">
                                    <i class="bi bi-hourglass-split text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-start border-danger border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Cancelled</h6>
                                    <h3 class="mb-0 text-danger"><?php echo $reservation_stats['cancelled'] ?? 0; ?></h3>
                                </div>
                                <div class="bg-soft-danger p-3 rounded">
                                    <i class="bi bi-x-circle text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Reservations -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Reservations</h5>
                    <a href="reservations.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($reservations)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Hall</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td>#<?php echo $reservation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($reservation['start_datetime'])); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($reservation['start_datetime'])); ?> - 
                                                <?php echo date('g:i A', strtotime($reservation['end_datetime'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $reservation['status'] === 'confirmed' ? 'success' : 
                                                         ($reservation['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($reservation['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="reservation-view.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                            </div>
                            <h5>No reservations found</h5>
                            <p class="text-muted">This user hasn't made any reservations yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Admin Notes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Notes</h5>
                </div>
                <div class="card-body">
                    <form id="adminNotesForm">
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Private notes about this user (visible to admins only)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($user['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Notes</button>
                        </div>
                    </form>
                </div>
            </div>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($reservations)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Hall</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td>#<?php echo $reservation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($reservation['start_datetime'])); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($reservation['start_datetime'])); ?> - 
                                                <?php echo date('g:i A', strtotime($reservation['end_datetime'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $reservation['status'] === 'confirmed' ? 'success' : 
                                                         ($reservation['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($reservation['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="reservation-view.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                            </div>
                            <h5>No reservations found</h5>
                            <p class="text-muted">This user hasn't made any reservations yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Admin Notes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Notes</h5>
                </div>
                <div class="card-body">
                    <form id="adminNotesForm" action="user-view.php" method="post">
                        <input type="hidden" name="action" value="update_notes">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Private notes about this user (visible to admins only)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($user['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Notes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- JavaScript for Admin Notes -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const notesForm = document.getElementById('adminNotesForm');
                
                if (notesForm) {
                    notesForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(notesForm);
                        
                        fetch(notesForm.action, {
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
                                    Notes saved successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                `;
                                
                                // Insert the alert before the form
                                notesForm.parentNode.insertBefore(alert, notesForm);
                                
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
            
            <!-- User Notes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Notes</h5>
                </div>
                <div class="card-body">
                    <form id="userNotesForm">
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Private notes about this user (visible to admins only)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?php echo htmlspecialchars($user['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Notes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notes Save Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('userNotesForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // In a real application, you would make an AJAX call here
            // to save the notes to the server
            
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>
                Notes saved successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Insert the alert before the form
            form.parentNode.insertBefore(alert, form);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        });
    }
});
</script>

<?php

require_once '../includes/footer.php';
?>
