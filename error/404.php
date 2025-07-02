<?php
http_response_code(404);
$pageTitle = '404 Not Found';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 text-warning">404</h1>
            <h2>Page Not Found</h2>
            <p class="lead">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
            <a href="/" class="btn btn-primary mt-3">Return to Home</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
