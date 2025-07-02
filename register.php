<?php
require_once 'includes/config.php';


if (isset($_SESSION['user_id'])) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'index.php');
}

$pageTitle = 'Register';
$success = '';
$error = '';


function usernameExists($username, $db) {
    $stmt = $db->Connection->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}


function emailExists($email, $db) {
    $stmt = $db->Connection->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        $errors[] = 'Username must be 4-20 characters long and can only contain letters, numbers, and underscores.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = new DB();
            
            
            if (usernameExists($username, $db)) {
                throw new Exception('Username is already taken. Please choose another one.');
            }
            
            if (emailExists($email, $db)) {
                throw new Exception('Email is already registered. Please use another email or try to login.');
            }
            
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            
            $stmt = $db->Connection->prepare("
                INSERT INTO users (username, email, full_name, password_hash, role, is_approved)
                VALUES (?, ?, ?, ?, 'user', 0)
            
            ");
            $stmt->bind_param('ssss', $username, $email, $fullName, $passwordHash);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Your account is pending approval from an administrator.';
                
                $_POST = [];
            } else {
                throw new Exception('Failed to create account. Please try again.');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}


require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7 col-xl-6">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white py-4">
                    <div class="text-center">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 mb-3">
                            <i class="bi bi-person-circle display-4 text-white"></i>
                        </div>
                        <h2 class="h3 mb-0">Create an Account</h2>
                    </div>
                </div>
                <div class="card-body p-4 p-md-5">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <div class="mt-2">
                                <a href="login.php" class="alert-link">Click here to login</a> once your account is approved.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form action="register.php" method="POST" novalidate class="needs-validation" id="registerForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="username" name="username"
                                            placeholder="Username"
                                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                            required pattern="[a-zA-Z0-9_]{4,20}">
                                        <label for="username" class="text-muted">
                                            <i class="bi bi-person-badge me-2 text-primary"></i>Username
                                        </label>
                                        <div class="invalid-feedback">
                                            Please choose a valid username (4-20 characters, letters, numbers, and underscores only).
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="Email Address"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <label for="email" class="text-muted">
                                            <i class="bi bi-envelope me-2 text-primary"></i>Email Address
                                        </label>
                                        <div class="invalid-feedback">
                                            Please provide a valid email address.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <br>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                    placeholder="Full Name"
                                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                <label for="full_name" class="text-muted">
                                    <i class="bi bi-person-lines-fill me-2 text-primary"></i>Full Name
                                </label>
                                <div class="invalid-feedback">
                                    Please provide your full name.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Password" required minlength="8">
                                        <label for="password" class="text-muted">
                                            <i class="bi bi-lock me-2 text-primary"></i>Password
                                        </label>
                                        <div class="invalid-feedback">
                                            Password must be at least 8 characters long.
                                        </div>
                                        <button class="btn btn-sm btn-link position-absolute end-0 top-0 mt-3 me-2 p-0 text-muted password-toggle" type="button">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                            placeholder="Confirm Password" required>
                                        <label for="confirm_password" class="text-muted">
                                            <i class="bi bi-lock-fill me-2 text-primary"></i>Confirm Password
                                        </label>
                                        <div class="invalid-feedback">
                                            Passwords must match.
                                        </div>
                                        <button class="btn btn-sm btn-link position-absolute end-0 top-0 mt-3 me-2 p-0 text-muted password-toggle" type="button">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill py-3 fw-bold">
                                    <i class="bi bi-person-plus me-2"></i>Create Account
                                </button>
                            </div>

                            <div class="text-center mt-4 pt-3 border-top">
                                <p class="mb-0">Already have an account?
                                    <a href="login.php" class="fw-semibold text-decoration-none">
                                        Sign in <i class="bi bi-arrow-right-short"></i>
                                    </a>
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });

        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(event) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');

                if (password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match!');
                    confirmPassword.focus();
                }

                if (!document.getElementById('terms').checked) {
                    event.preventDefault();
                    alert('You must agree to the Terms of Service and Privacy Policy.');
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>