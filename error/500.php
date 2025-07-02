<?php
http_response_code(500);
$pageTitle = '500 Internal Server Error';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 text-danger">500</h1>
            <h2>Internal Server Error</h2>
            <p class="lead">Something went wrong on our end. We're working to fix it. Please try again later.</p>
            <a href="/" class="btn btn-primary mt-3">Return to Home</a>
            
            <?php if (getenv('ENVIRONMENT') === 'development' && !empty($error)): ?>
                <div class="alert alert-danger mt-4 text-start">
                    <h5>Error Details:</h5>
                    <pre><?php echo htmlspecialchars($error); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
