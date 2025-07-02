<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}


header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');


define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'theater_management');


define('SITE_NAME', 'Arts & Culture Complex Management System');
define('SITE_URL', 'http://localhost/Arts and culture management system');
define('ADMIN_EMAIL', 'admin@helwan.edu.eg');

define('MAX_RESERVATION_DAYS', 30); 
define('CANCEL_EDIT_DAYS', 2); 


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');


date_default_timezone_set('Africa/Cairo');


class DB
{
    private $hostname = "127.0.0.1";
    private $username = "root";
    private $password = "";
    private $database = "theater_management";
    private $port     = 3307; 
    public $Connection;

    public function __construct()
    {
        $this->Connection = new mysqli(
            $this->hostname,
            $this->username,
            $this->password,
            $this->database,
            $this->port 
        );

        if ($this->Connection->connect_error) {
            die("Connection failed: " . $this->Connection->connect_error);
        }

        $this->Connection->set_charset("utf8mb4");
    }
}


class Auth
{
    public function isAuth()
    {
        return isset($_SESSION['user_id']);
    }

    public function redirectIfNotAuth()
    {
        if (!$this->isAuth()) {
            $this->redirect(SITE_URL . '/login.php?notsignedin=1');
        }
    }

    public function redirectIfAuth()
    {
        if ($this->isAuth()) {
            $this->redirect(SITE_URL . '/index.php');
        }
    }

    public function signUp($data)
    {
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $fullName = $data['full_name'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($fullName)) {
            return ['status' => 'error', 'message' => 'All fields are required'];
        }

        if ($password !== $confirmPassword) {
            return ['status' => 'error', 'message' => 'Password mismatch while confirming'];
        }

        $db = new DB();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $db->Connection->prepare("INSERT INTO users (username, email, password, full_name, is_approved) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param('ssss', $username, $email, $hashedPassword, $fullName);

            if ($stmt->execute()) {
                return ['status' => 'success', 'message' => 'Registration successful! Please wait for admin approval.'];
            } else {
                if ($db->Connection->errno == 1062) {
                    return ['status' => 'error', 'message' => 'Username or email already exists'];
                }
                return ['status' => 'error', 'message' => 'Error while signing up: ' . $db->Connection->error];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username/Email and password are required'];
        }

        $db = new DB();
        $stmt = $db->Connection->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);

        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Database error'];
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
                if ($user['is_approved'] == 0) {
                    return ['success' => false, 'message' => 'Your account is pending admin approval'];
                }

                
                $ip = $_SERVER['REMOTE_ADDR'];
                $updateStmt = $db->Connection->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                $updateStmt->bind_param('si', $ip, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();

                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['is_admin'] = ($user['role'] === 'admin') ? 1 : 0;

                $isAdmin = ($user['role'] === 'admin') ? 1 : 0;
                return [
                    'success' => true,
                    'is_admin' => $isAdmin,
                    'message' => 'Login successful'
                ];
            }

            return ['success' => false, 'message' => 'Incorrect password'];
        }

        return ['success' => false, 'message' => 'User not found'];
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        $this->redirect(SITE_URL . '/login.php');
    }

    private function redirect($url)
    {
        header("Location: $url");
        exit();
    }
}


function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

function isAdmin()
{
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireAdmin()
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

function redirect($url)
{
    
    if (strpos($url, 'http') !== 0) {
        
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        
        $baseUrl = rtrim(SITE_URL, '/');
        $url = $baseUrl . $url;
    }

    
    while (ob_get_level()) {
        ob_end_clean();
    }

    
    header('HTTP/1.1 302 Found');
    header('Location: ' . $url);
    exit();
}

function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}


$db = new DB();


function display_alerts() {
    if (isset($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $message) {
            $alert_type = $message['type'] ?? 'info';
            $alert_icon = '';
            
            
            switch ($alert_type) {
                case 'success':
                    $alert_icon = '<i class="bi bi-check-circle-fill me-2"></i>';
                    break;
                case 'danger':
                    $alert_icon = '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
                    break;
                case 'warning':
                    $alert_icon = '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
                    break;
                case 'info':
                default:
                    $alert_icon = '<i class="bi bi-info-circle-fill me-2"></i>';
                    break;
            }
            
            echo '<div class="alert alert-' . htmlspecialchars($alert_type) . ' alert-dismissible fade show" role="alert">
                ' . $alert_icon . '
                ' . htmlspecialchars($message['text']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
        
        unset($_SESSION['messages']);
    }
}


function add_alert($type, $message) {
    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }
    
    $_SESSION['messages'][] = [
        'type' => $type,
        'text' => $message
    ];
}


$auth = new Auth($db->Connection);
