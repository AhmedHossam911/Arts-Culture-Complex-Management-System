<?php
require_once '../includes/config.php';


$auth = new Auth();


if (!$auth->isAuth() || !isAdmin()) {
    redirect('../index.php');
}


if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_alert('error', 'Invalid user ID.');
    redirect('users.php');
}

$user_id = (int)$_GET['id'];
$pageTitle = 'Edit User';


$db = new DB();
$pdo = $db->Connection;


try {
    $stmt = $db->Connection->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        add_alert('error', 'User not found.');
        redirect('users.php');
    }

    
    $user['status'] = $user['is_approved'] ? 'active' : 'pending';
} catch (Exception $e) {
    error_log("User edit error: " . $e->getMessage());
    add_alert('error', 'Error loading user data.');
    redirect('users.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
    $status = in_array($_POST['status'] ?? '', ['active', 'pending', 'suspended']) ? $_POST['status'] : 'pending';
    $is_approved = ($status === 'active') ? 1 : 0;

    
    $errors = [];

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = 'This email is already registered to another account.';
    }

    
    $password_changed = false;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($password) || !empty($confirm_password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } else {
            $password_changed = true;
        }
    }

    
    if (empty($errors)) {
        try {
            
            $db = new DB();

            
            $update_fields = [
                'full_name' => $full_name,
                'email' => $email,
                'role' => $role,
                'is_approved' => $is_approved,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            
            if (!empty($password)) {
                $update_fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            
            $set_parts = [];
            $types = '';
            $values = [];

            foreach ($update_fields as $field => $value) {
                if ($field === 'is_approved') {
                    $types .= 'i'; 
                } else {
                    $types .= 's'; 
                }
                $set_parts[] = "$field = ?";
                $values[] = $value;
            }

            
            $values[] = $user_id;
            $types .= 'i';

            
            $query = "UPDATE users SET " . implode(', ', $set_parts) . " WHERE id = ?";
            $stmt = $db->Connection->prepare($query);

            
            $stmt->bind_param($types, ...$values);
            $stmt->execute();

            add_alert('success', 'User updated successfully.');
            redirect('user-edit.php?id=' . $user_id);
        } catch (Exception $e) {
            error_log("User update error: " . $e->getMessage());
            add_alert('error', 'Error updating user. Please try again.');
        }
    }
}


require_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
            <li class="breadcrumb-item"><a href="user-view.php?id=<?php echo $user_id; ?>">View User</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit User</h1>
        <div>
            <a href="user-view.php?id=<?php echo $user_id; ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
            <button type="submit" form="editUserForm" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save Changes
            </button>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <form id="editUserForm" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                    value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo ($user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo ($user['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo ($user['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="form-text text-muted">Username cannot be changed.</small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="changePassword">
                                <label class="form-check-label" for="changePassword">
                                    Change Password
                                </label>
                            </div>
                        </div>

                        <div id="passwordFields" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <div class="form-text">Leave blank to keep current password</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Account Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <strong>Member Since:</strong><br>
                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                        </li>
                        <?php if (!empty($user['last_login'])): ?>
                            <li class="mb-2">
                                <strong>Last Login:</strong><br>
                                <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                            </li>
                            <li class="mb-0">
                                <strong>Last IP:</strong><br>
                                <?php echo htmlspecialchars($user['last_ip'] ?? 'N/A'); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Danger Zone</h5>
                </div>
                <div class="card-body">
                    <?php if ($user_id != $_SESSION['user_id']): ?>
                        <div class="mb-3">
                            <h6 class="text-danger">Delete User Account</h6>
                            <p class="small text-muted">
                                This will permanently delete the user account and all associated data. This action cannot be undone.
                            </p>
                            <form method="post" class="d-inline"
                                onsubmit="return confirm('Are you absolutely sure you want to delete this user? This action cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i> Delete User
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            You cannot delete your own account while logged in.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toggle password fields -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const changePasswordCheckbox = document.getElementById('changePassword');
        const passwordFields = document.getElementById('passwordFields');

        if (changePasswordCheckbox && passwordFields) {
            changePasswordCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    passwordFields.style.display = 'block';
                    document.getElementById('password').setAttribute('required', 'required');
                    document.getElementById('confirm_password').setAttribute('required', 'required');
                } else {
                    passwordFields.style.display = 'none';
                    document.getElementById('password').removeAttribute('required');
                    document.getElementById('confirm_password').removeAttribute('required');
                }
            });
        }

        // Form validation
        const form = document.getElementById('editUserForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password || confirmPassword) {
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long.');
                        return false;
                    }

                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match.');
                        return false;
                    }
                }

                return true;
            });
        }
    });
</script>

<?php

require_once '../includes/footer.php';
?>