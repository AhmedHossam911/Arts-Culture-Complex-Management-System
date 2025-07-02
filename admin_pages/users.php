<?php
require_once '../includes/config.php';


$auth = new Auth();


if (!$auth->isAuth() || !isAdmin()) {
    redirect('../index.php');
}

$pageTitle = 'Manage Users';


$db = new DB();
$pdo = $db->Connection;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];

        try {
            switch ($_POST['action']) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    add_alert('success', 'User approved successfully.');
                    break;

                case 'suspend':
                    $stmt = $pdo->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    add_alert('warning', 'User has been suspended.');
                    break;

                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    add_alert('success', 'User activated successfully.');
                    break;

                case 'delete':
                    
                    if ($user_id == $_SESSION['user_id']) {
                        add_alert('danger', 'You cannot delete your own account.');
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        add_alert('success', 'User deleted successfully.');
                    }
                    break;
            }


        } catch (PDOException $e) {
            error_log("User action error: " . $e->getMessage());
            add_alert('danger', 'An error occurred while processing your request.');
        }
    }
}


$status = $_GET['status'] ?? 'all';
$role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';


$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($status !== 'all') {
    
    $statusMap = [
        'active' => 1,
        'suspended' => 0,
        'pending' => 0
    ];
    
    if (isset($statusMap[$status])) {
        $query .= " AND is_approved = ?";
        $params[] = $statusMap[$status];
    }
}

if ($role !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role;
}

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}


$sort = $_GET['sort'] ?? 'created_at';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY $sort $order";


$db = new DB();
$stmt = $db->Connection->prepare($query);


if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    
    $row['status'] = $row['is_approved'] ? 'active' : 'pending';
    $users[] = $row;
}
$stmt->close();


$status_counts = [
    'all' => 0,
    'active' => 0,
    'pending' => 0,
    'suspended' => 0
];

$role_counts = [
    'all' => 0,
    'admin' => 0,
    'user' => 0
];

try {
    
    $db = new DB();
    $result = $db->Connection->query("SELECT 
        CASE 
            WHEN is_approved = 1 THEN 'active' 
            ELSE 'pending' 
        END as status, 
        COUNT(*) as count 
        FROM users 
        GROUP BY is_approved");
    $status_results = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status_results[$row['status']] = (int)$row['count'];
        }
        $status_counts = array_merge($status_counts, $status_results);
        $status_counts['all'] = array_sum($status_results);
    }

    
    $result = $db->Connection->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $role_results = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $role_results[$row['role']] = (int)$row['count'];
        }
        $role_counts = array_merge($role_counts, $role_results);
        $role_counts['all'] = array_sum($role_results);
    }
} catch (Exception $e) {
    error_log("Error getting user counts: " . $e->getMessage());
}


require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Manage Users</h1>
        <a href="user-new.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i> Add New User
        </a>
    </div>

    <?php display_alerts(); ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by name, username, or email">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>
                            All (<?php echo $status_counts['all']; ?>)
                        </option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>
                            Active (<?php echo $status_counts['active']; ?>)
                        </option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>
                            Pending (<?php echo $status_counts['pending']; ?>)
                        </option>
                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>
                            Suspended (<?php echo $status_counts['suspended']; ?>)
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" onchange="this.form.submit()">
                        <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>
                            All Roles (<?php echo $role_counts['all']; ?>)
                        </option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>
                            Admin (<?php echo $role_counts['admin']; ?>)
                        </option>
                        <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>
                            User (<?php echo $role_counts['user']; ?>)
                        </option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <a href="users.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <a href="?sort=id&order=<?php echo $sort === 'id' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>">
                                    ID
                                    <?php if ($sort === 'id'): ?>
                                        <i class="bi bi-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>User</th>
                            <th>Email</th>
                            <th>
                                <a href="?sort=role&order=<?php echo $sort === 'role' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>">
                                    Role
                                    <?php if ($sort === 'role'): ?>
                                        <i class="bi bi-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Status</th>
                            <th>
                                <a href="?sort=created_at&order=<?php echo $sort === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>">
                                    Joined
                                    <?php if ($sort === 'created_at'): ?>
                                        <i class="bi bi-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3">
                                                <span class="avatar-title bg-soft-primary text-primary rounded-circle">
                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">
                                                    <a href="user-view.php?id=<?php echo $user['id']; ?>">
                                                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $userStatus = $user['status'] ?? 'pending'; 
                                            $statusClass = 'secondary';
                                            $statusText = ucfirst($userStatus);
                                            
                                            
                                            if ($userStatus === 'active') {
                                                $statusClass = 'success';
                                            } elseif ($userStatus === 'pending') {
                                                $statusClass = 'warning';
                                            } elseif (empty($userStatus)) {
                                                $statusText = 'Inactive';
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($statusText); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="d-none d-md-inline"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                                        <span class="d-md-none"><?php echo date('m/d/Y', strtotime($user['created_at'])); ?></span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                                id="dropdownMenuButton<?php echo $user['id']; ?>"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-gear"></i>
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $user['id']; ?>">
                                                <li>
                                                    <a class="dropdown-item" href="user-view.php?id=<?php echo $user['id']; ?>">
                                                        <i class="bi bi-eye me-2"></i>View
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="user-edit.php?id=<?php echo $user['id']; ?>">
                                                        <i class="bi bi-pencil me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <?php 
                                                    $userStatus = $user['status'] ?? 'pending'; 
                                                    
                                                    if ($userStatus === 'pending'): 
                                                ?>
                                                    <li>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this user?');">
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="bi bi-check-lg me-2"></i>Approve
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if ($userStatus === 'active'): ?>
                                                    <li>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-warning">
                                                                <i class="bi bi-pause me-2"></i>Suspend
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php elseif ($userStatus === 'suspended'): ?>
                                                    <li>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                                            <input type="hidden" name="action" value="activate">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="bi bi-check-circle me-2"></i>Activate
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="py-3">
                                        <i class="bi bi-people display-4 text-muted"></i>
                                        <h5 class="mt-3">No users found</h5>
                                        <p class="text-muted">Try adjusting your search or filter to find what you're looking for.</p>
                                        <a href="users.php" class="btn btn-primary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (count($users) > 0): ?>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing <span class="fw-bold">1-<?php echo count($users); ?></span> of <span class="fw-bold"><?php echo count($users); ?></span> users
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php

require_once '../includes/footer.php';
?>