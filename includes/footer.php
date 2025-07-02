    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-1">
                        <span class="d-block d-md-inline">
                            <i class="bi bi-geo-alt"></i> Helwan, Cairo, Egypt
                        </span>
                        <span class="d-none d-md-inline"> | </span>
                        <span class="d-block d-md-inline">
                            <i class="bi bi-envelope"></i> info@helwan.edu.eg
                        </span>
                        <span class="d-none d-md-inline"> | </span>
                        <span class="d-block d-md-inline">
                            <i class="bi bi-telephone"></i> +20 2 2555 5555
                        </span>
                    </p>
                    <p class="mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Make sure footer stays at bottom -->
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- CSRF Token for AJAX -->
    <script>
        const CSRF_TOKEN = '<?php echo generateCSRFToken(); ?>';
        const SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $script; ?>.js"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Initialize DataTables -->
    <script>
        $(document).ready(function() {
            // Initialize all DataTables
            $('.datatable').DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                },
                order: [],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
            });
            
            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const html = document.documentElement;
            let theme = localStorage.getItem('theme') || 'light';
            
            // Function to update theme icon
            function updateThemeIcon(theme) {
                if (!themeToggle) return;
                const icon = themeToggle.querySelector('i');
                if (!icon) return;
                
                if (theme === 'dark') {
                    icon.classList.remove('bi-moon-stars');
                    icon.classList.add('bi-sun');
                    themeToggle.title = 'Switch to light mode';
                } else {
                    icon.classList.remove('bi-sun');
                    icon.classList.add('bi-moon-stars');
                    themeToggle.title = 'Switch to dark mode';
                }
            }
            
            // Apply saved theme with transition
            function setTheme(theme) {
                // Start transition
                html.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                
                // Set the theme
                html.setAttribute('data-bs-theme', theme);
                localStorage.setItem('theme', theme);
                updateThemeIcon(theme);
                
                // Force repaint
                const repaint = document.documentElement.offsetHeight;
                
                // Remove transition after it's done
                setTimeout(() => {
                    html.style.transition = '';
                }, 300);
            }
            
            // Initialize theme
            setTheme(theme);
            
            // Toggle theme on button click
            if (themeToggle) {
                themeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    const currentTheme = html.getAttribute('data-bs-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    setTheme(newTheme);
                });
            }
            
            function updateThemeIcon(theme) {
                if (!themeToggle) return;
                
                const icon = themeToggle.querySelector('i');
                if (theme === 'dark') {
                    icon.classList.remove('bi-moon-stars');
                    icon.classList.add('bi-sun');
                } else {
                    icon.classList.remove('bi-sun');
                    icon.classList.add('bi-moon-stars');
                }
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
