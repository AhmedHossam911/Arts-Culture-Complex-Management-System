<?php
http_response_code(403);
$pageTitle = '403 Forbidden';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-1 text-danger">403</h1>
            <h2>Access Forbidden</h2>
            <p class="lead">You don't have permission to access this resource.</p>
            <a href="/" class="btn btn-primary mt-3">Return to Home</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
