<?php
require_once 'includes/config.php';


$auth = new Auth();


if (!$auth->isAuth()) {
    redirect('login.php');
}


if ($auth->isAuth()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$pageTitle = 'Home - ' . SITE_NAME;


require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-4 fw-bold mb-4">Welcome to <?php echo SITE_NAME; ?></h1>
                <p class="lead mb-4">Manage your theater hall reservations with ease. Our system provides a seamless experience for both administrators and users to book, manage, and track theater hall reservations.</p>
                <div class="d-flex gap-3">
                    <a href="register.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-person-plus me-2"></i> Sign Up
                    </a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="assets/img/theater-hero.jpg" alt="Theater Hall" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Features</h2>
            <p class="text-muted">Discover what makes our theater management system special</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-3 mx-auto" style="width: 64px; height: 64px;">
                            <i class="bi bi-calendar-check fs-3"></i>
                        </div>
                        <h5 class="card-title">Easy Reservations</h5>
                        <p class="card-text text-muted">Book theater halls with just a few clicks. Check availability in real-time and get instant confirmation.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-opacity-10 text-success rounded-circle p-3 mb-3 mx-auto" style="width: 64px; height: 64px;">
                            <i class="bi bi-graph-up fs-3"></i>
                        </div>
                        <h5 class="card-title">Powerful Analytics</h5>
                        <p class="card-text text-muted">Gain insights with detailed reports and analytics on hall usage and reservations.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-info bg-opacity-10 text-info rounded-circle p-3 mb-3 mx-auto" style="width: 64px; height: 64px;">
                            <i class="bi bi-bell fs-3"></i>
                        </div>
                        <h5 class="card-title">Instant Notifications</h5>
                        <p class="card-text text-muted">Get instant notifications about your reservations and important updates.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How It Works</h2>
            <p class="text-muted">Get started in just a few simple steps</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="display-6 fw-bold">1</span>
                    </div>
                    <h5>Create an Account</h5>
                    <p class="text-muted">Sign up for a new account or log in if you already have one.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="display-6 fw-bold">2</span>
                    </div>
                    <h5>Check Availability</h5>
                    <p class="text-muted">View available time slots for your preferred theater hall.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <span class="display-6 fw-bold">3</span>
                    </div>
                    <h5>Make a Reservation</h5>
                    <p class="text-muted">Book your preferred time slot and receive confirmation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-4">Ready to get started?</h2>
        <p class="lead mb-4">Join our community and experience the best theater management system.</p>
        <a href="register.php" class="btn btn-light btn-lg px-4 me-2">
            <i class="bi bi-person-plus me-2"></i> Sign Up Now
        </a>
        <a href="login.php" class="btn btn-outline-light btn-lg px-4">
            <i class="bi bi-box-arrow-in-right me-2"></i> Login
        </a>
    </div>
</section>

<?php

require_once 'includes/footer.php';
?>
