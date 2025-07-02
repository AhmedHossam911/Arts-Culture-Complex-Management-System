<?php
require_once '../includes/config.php';


$auth = new Auth();


if (!$auth->isAuth() || !isAdmin()) {
    redirect('../index.php');
}

$pageTitle = 'Add New User';


$db = new DB();
$pdo = $db->Connection;

$pageTitle = 'Add New User';
$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_admin = isset($_POST['role']) && $_POST['role'] === 'admin' ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    $send_credentials = isset($_POST['send_credentials']);

    
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Username is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            
            $sql = "INSERT INTO users (username, email, password_hash, full_name, role, is_approved, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);

            if ($stmt === false) {
                throw new Exception('Failed to prepare statement: ' . $pdo->error);
            }

            $role = $is_admin ? 'admin' : 'user';
            $stmt->bind_param('sssss', $username, $email, $hashedPassword, $full_name, $role);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Database error: ' . $error);
            }

            $stmt->close();
            $result = ['status' => 'success', 'message' => 'User created successfully.'];
            $userId = $pdo->insert_id;

            
            add_alert('success', 'User created successfully.');

            
            if ($send_credentials && isset($userId)) {
                
                
            }

            
            header('Location: users.php');
            exit();
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            error_log("User creation error: " . $errorMessage);

            
            $errors[] = 'Error: ' . $errorMessage;

            
            if (isset($sql)) {
                error_log("SQL Query: " . $sql);
            }
        }
    }
}


require_once '../includes/header.php';


if (!empty($_SESSION['alert'])) {
    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['alert']['type']) . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['alert']['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    unset($_SESSION['alert']);
}
?>

<div class="container-fluid px-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add New</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Add New User</h1>
        <div>
            <a href="users.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
            <button type="submit" form="addUserForm" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save User
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
                    <form id="addUserForm" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                <div class="form-text">Letters, numbers, and underscores only</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($_POST['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo ($_POST['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php
                                                                                                echo htmlspecialchars($_POST['address'] ?? '');
                                                                                                ?></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="send_credentials" name="send_credentials" checked>
                            <label class="form-check-label" for="send_credentials">
                                Send login credentials to user via email
                            </label>
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
                            <?php echo date('M j, Y'); ?>
                        </li>
                        <li class="mb-0">
                            <strong>Last Login:</strong><br>
                            Never
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Password Requirements</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <span id="length" class="text-muted">
                                <i class="bi bi-check-circle-fill text-success d-none"></i>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                At least 8 characters
                            </span>
                        </li>
                        <li class="mb-2">
                            <span id="uppercase" class="text-muted">
                                <i class="bi bi-check-circle-fill text-success d-none"></i>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                At least one uppercase letter
                            </span>
                        </li>
                        <li class="mb-2">
                            <span id="lowercase" class="text-muted">
                                <i class="bi bi-check-circle-fill text-success d-none"></i>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                At least one lowercase letter
                            </span>
                        </li>
                        <li>
                            <span id="number" class="text-muted">
                                <i class="bi bi-check-circle-fill text-success d-none"></i>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                At least one number
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Validation Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const form = document.getElementById('addUserForm');

        // Password validation function
        function validatePassword() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Check password requirements
            const hasMinLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const passwordsMatch = password === confirmPassword && password.length > 0;

            // Update requirement indicators
            updateRequirement('length', hasMinLength);
            updateRequirement('uppercase', hasUppercase);
            updateRequirement('lowercase', hasLowercase);
            updateRequirement('number', hasNumber);

            // Update confirm password validation
            const confirmFeedback = document.getElementById('confirmPasswordFeedback');
            if (confirmPassword.length > 0) {
                if (passwordsMatch) {
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    confirmPasswordInput.classList.remove('is-valid');
                    confirmPasswordInput.classList.add('is-invalid');
                }
            } else {
                confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            }

            return hasMinLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
        }

        // Update requirement indicator
        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            const checkIcon = element.querySelector('.bi-check-circle-fill');
            const xIcon = element.querySelector('.bi-x-circle-fill');

            if (isValid) {
                element.classList.remove('text-muted', 'text-danger');
                element.classList.add('text-success');
                checkIcon.classList.remove('d-none');
                xIcon.classList.add('d-none');
            } else {
                element.classList.remove('text-success');
                element.classList.add(passwordInput.value.length > 0 ? 'text-danger' : 'text-muted');
                checkIcon.classList.add('d-none');
                xIcon.classList.remove('d-none');
            }
        }

        // Add event listeners
        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);

        // Form submission validation
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!validatePassword()) {
                    e.preventDefault();

                    // Show error message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-danger alert-dismissible fade show mt-3';
                    alert.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Please ensure all password requirements are met.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;

                    // Insert the alert before the form
                    form.parentNode.insertBefore(alert, form);

                    // Scroll to the alert
                    alert.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        }
    });
</script>

<?php

require_once '../includes/footer.php';
?>