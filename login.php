<?php

require_once 'includes/config.php';


$auth = new Auth();


if ($auth->isAuth()) {
    $isAdmin = ($_SESSION['is_admin'] ?? 0) == 1;
    header('Location: ' . ($isAdmin ? 'admin_pages/dashboard.php' : 'index.php'));
    exit();
}


$pageTitle = 'Login';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    
    if (function_exists('verifyCSRFToken') && isset($_POST['csrf_token'])) {
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid request. Please try again.';
        }
    }

    if (empty($error)) {
        $result = $auth->login($username, $password);

        if ($result['success']) {
            
            $redirect = $result['is_admin'] ? 'admin_pages/dashboard.php' : 'reserve.php';
            
            
            $redirectUrl = $_SESSION['redirect_after_login'] ?? $redirect;
            unset($_SESSION['redirect_after_login']);
            
            header('Location: ' . $redirectUrl);
            exit();
        } else {
            $error = $result['message'];
        }
    }
}


require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle display-4 text-primary"></i>
                        <h2 class="h4 mb-3">Sign in to your account</h2>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST" novalidate>
                        <?php if (function_exists('generateCSRFToken')): ?>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                    required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label">Password</label>
                                <a href="forgot-password.php" class="small">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary password-toggle" type="button">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                        </button>

                        <div class="text-center mt-3">
                            <p class="mb-0">Don't have an account? <a href="register.php">Sign up</a></p>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });

    // Clear error message when user starts typing
    const formInputs = document.querySelectorAll('input');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            const errorAlert = this.closest('form').querySelector('.alert');
            if (errorAlert) {
                errorAlert.remove();
            }
        });
    });
});
</script>