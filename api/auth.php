<?php
/**
 * Authentication API
 * Handles user registration, login, logout
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch($action) {
    case 'register':
        if($method === 'POST') {
            register($db);
        }
        break;
    case 'login':
        if($method === 'POST') {
            login($db);
        }
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkSession();
        break;
    default:
        http_response_code(400);
        echo json_encode(array("message" => "Invalid action"));
}

/**
 * User Registration
 */
function register($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(empty($data->username) || empty($data->email) || empty($data->password)) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "All fields are required"));
        return;
    }

    // Validate email
    if(!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Invalid email format"));
        return;
    }

    // Check if user exists
    $query = "SELECT user_id FROM users WHERE email = :email OR username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $data->email);
    $stmt->bindParam(":username", $data->username);
    $stmt->execute();

    if($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Username or email already exists"));
        return;
    }

    // Insert new user
    $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $db->prepare($query);
    
    $hashed_password = password_hash($data->password, PASSWORD_BCRYPT);
    
    $stmt->bindParam(":username", $data->username);
    $stmt->bindParam(":email", $data->email);
    $stmt->bindParam(":password", $hashed_password);

    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array(
            "success" => true,
            "message" => "Registration successful"
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Registration failed"));
    }
}

/**
 * User Login
 */
function login($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(empty($data->email) || empty($data->password)) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Email and password required"));
        return;
    }

    $query = "SELECT user_id, username, email, password FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $data->email);
    $stmt->execute();

    if($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($data->password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Login successful",
                "user" => array(
                    "user_id" => $row['user_id'],
                    "username" => $row['username'],
                    "email" => $row['email']
                )
            ));
        } else {
            http_response_code(401);
            echo json_encode(array("success" => false, "message" => "Invalid credentials"));
        }
    } else {
        http_response_code(401);
        echo json_encode(array("success" => false, "message" => "Invalid credentials"));
    }
}

/**
 * User Logout
 */
function logout() {
    session_destroy();
    http_response_code(200);
    echo json_encode(array("success" => true, "message" => "Logout successful"));
}

/**
 * Check Session
 */
function checkSession() {
    if(isset($_SESSION['user_id'])) {
        echo json_encode(array(
            "success" => true,
            "user" => array(
                "user_id" => $_SESSION['user_id'],
                "username" => $_SESSION['username'],
                "email" => $_SESSION['email']
            )
        ));
    } else {
        http_response_code(401);
        echo json_encode(array("success" => false, "message" => "Not authenticated"));
    }
}
?>