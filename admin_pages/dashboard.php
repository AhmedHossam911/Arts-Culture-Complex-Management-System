<?php

$basePath = dirname(dirname(__FILE__));
require_once $basePath . '/includes/config.php';


if (!$auth->isAuth() || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$pageTitle = 'Admin Dashboard';


error_reporting(E_ALL);
ini_set('display_errors', 1);


function log_error($message)
{
    error_log('Dashboard Error: ' . $message);
    return 'Error: ' . $message;
}


if (!isset($db) || !$db->Connection) {
    die(log_error('Database connection failed. Please check your configuration.'));
}

try {

    if (!$db->Connection->ping()) {
        throw new Exception('Database connection is not active.');
    }
} catch (Exception $e) {
    die(log_error('Database connection error: ' . $e->getMessage()));
}


$stats = [
    'total_users' => 0,
    'pending_approvals' => 0,
    'total_reservations' => 0,
    'pending_reservations' => 0,
    'active_halls' => 0
];

try {

    $tableCheck = $db->Connection->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck->num_rows === 0) {
        throw new Exception("The 'users' table does not exist in the database.");
    }


    $result = $db->Connection->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_users'] = $row ? $row['count'] : 0;
    } else {
        throw new Exception("Error getting users count: " . $db->Connection->error);
    }


    $result = $db->Connection->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
    if ($result) {
        $stats['pending_approvals'] = $result->fetch_assoc()['count'];
    } else {
        throw new Exception("Error getting pending approvals: " . $db->Connection->error);
    }


    $result = $db->Connection->query("SELECT COUNT(*) as count FROM reservations");
    if ($result) {
        $stats['total_reservations'] = $result->fetch_assoc()['count'];
    } else {

        $stats['total_reservations'] = 0;
    }


    $result = $db->Connection->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
    if ($result) {
        $stats['pending_reservations'] = $result->fetch_assoc()['count'];
    } else {
        $stats['pending_reservations'] = 0;
    }


    $result = $db->Connection->query("SELECT COUNT(*) as count FROM theater_halls WHERE is_active = 1");
    if ($result) {
        $stats['active_halls'] = $result->fetch_assoc()['count'];
    } else {

        $stats['active_halls'] = 0;
    }

    // Check if required tables exist
    $tablesCheck = $db->Connection->query("
        SELECT 
            (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'reservations') as has_reservations,
            (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users') as has_users,
            (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'theater_halls') as has_halls
    ")->fetch_assoc();

    // Initialize arrays
    $recent_reservations = [];
    $recent_users = [];
    $recentReservations = [];

    // Fetch recent reservations if tables exist
    if ($tablesCheck['has_reservations'] && $tablesCheck['has_users'] && $tablesCheck['has_halls']) {
        try {
            // Get 10 most recent reservations with user and hall details
            $query = "SELECT 
                        r.id, 
                        r.event_name as title,
                        r.status,
                        r.start_datetime as event_date,
                        r.end_datetime,
                        r.created_at,
                        u.username, 
                        u.full_name, 
                        u.email, 
                        th.name as hall_name
                    FROM reservations r
                    JOIN users u ON r.user_id = u.id
                    JOIN theater_halls th ON r.theater_hall_id = th.id
                    ORDER BY r.created_at DESC
                    LIMIT 10";

            $result = $db->Connection->query($query);
            if ($result) {
                $recent_reservations = $result->fetch_all(MYSQLI_ASSOC);
                $recentReservations = $recent_reservations; // For backward compatibility
            }
        } catch (Exception $e) {
            error_log("Error in recent reservations: " . $e->getMessage());
        }
    } else {
        error_log("Required tables missing. Reservations: " . $tablesCheck['has_reservations'] .
            ", Users: " . $tablesCheck['has_users'] .
            ", Theater Halls: " . $tablesCheck['has_halls']);
    }

    // Fetch recent users
    if ($tablesCheck['has_users']) {
        try {
            $query = "SELECT 
                        id, 
                        username, 
                        email, 
                        CONCAT(first_name, ' ', last_name) as full_name, 
                        created_at, 
                        is_approved,
                        CASE 
                            WHEN is_approved = 0 THEN 'pending'
                            WHEN is_active = 1 THEN 'active'
                            ELSE 'inactive'
                        END as status
                    FROM users
                    ORDER BY created_at DESC
                    LIMIT 10";

            $result = $db->Connection->query($query);
            if ($result) {
                $recent_users = $result->fetch_all(MYSQLI_ASSOC);
                error_log("Fetched " . count($recent_users) . " recent users");
                if (empty($recent_users)) {
                    error_log("No users found in the database");
                }
            } else {
                error_log("Query failed: " . $db->Connection->error);
            }
        } catch (Exception $e) {
            error_log("Error fetching recent users: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Error loading dashboard data. Please try again later.";
}


require_once $basePath . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Dashboard</h1>
        <div>
            <span class="me-2"><?php echo date('l, F j, Y'); ?></span>
            <a href="export.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download"></i> Export
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Users</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['total_users']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 p-3 rounded">
                            <i class="bi bi-people fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="users.php" class="text-white text-decoration-underline small">View All Users</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Pending Approvals</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['pending_approvals']); ?></h2>
                        </div>
                        <div class="bg-dark bg-opacity-25 p-3 rounded">
                            <i class="bi bi-person-plus fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="users.php?status=pending" class="text-dark text-decoration-underline small">Review Now</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Reservations</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['total_reservations']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 p-3 rounded">
                            <i class="bi bi-calendar-check fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="reservations.php" class="text-white text-decoration-underline small">View All Reservations</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Pending Reservations</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['pending_reservations']); ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 p-3 rounded">
                            <i class="bi bi-clock-history fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="reservations.php?status=pending" class="text-white text-decoration-underline small">Review Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Reservations -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Reservations</h5>
                    <a href="reservations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Hall</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_reservations)): ?>
                                    <?php foreach ($recent_reservations as $reservation): ?>
                                        <tr>
                                            <td>#<?php echo $reservation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($reservation['hall_name']); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['username']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($reservation['event_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                                        echo $reservation['status'] === 'confirmed' ? 'success' : ($reservation['status'] === 'pending' ? 'warning' : 'secondary');
                                                                        ?>">
                                                    <?php echo ucfirst($reservation['status']); ?>
                                                </span>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">No recent reservations found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users & Quick Actions -->
        <div class="col-lg-4">
            <!-- Recent Users -->
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Users</h5>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_users)): ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td>#<?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                            <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $user['status'] === 'active' ? 'success' : 
                                                        ($user['status'] === 'pending' ? 'warning' : 'secondary');
                                                ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">No recent users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once $basePath . '/includes/footer.php';
?>