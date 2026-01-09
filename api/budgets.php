<?php
/**
 * Budget Management API
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

require_once '../config/database.php';

session_start();

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized"));
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        getBudgets($db, $user_id);
        break;
    case 'POST':
        createBudget($db, $user_id);
        break;
    case 'PUT':
        updateBudget($db, $user_id);
        break;
    case 'DELETE':
        if(isset($_GET['id'])) {
            deleteBudget($db, $user_id, $_GET['id']);
        }
        break;
}

function getBudgets($db, $user_id) {
    $query = "SELECT b.*, 
              COALESCE(SUM(e.amount), 0) as spent_amount,
              (b.budget_amount - COALESCE(SUM(e.amount), 0)) as remaining
              FROM budgets b
              LEFT JOIN expenses e ON e.user_id = b.user_id 
                  AND e.expense_date BETWEEN b.start_date AND b.end_date
              WHERE b.user_id = :user_id
              GROUP BY b.budget_id
              ORDER BY b.start_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array("success" => true, "data" => $budgets));
}

function createBudget($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO budgets (user_id, budget_amount, budget_period, start_date, end_date) 
              VALUES (:user_id, :amount, :period, :start_date, :end_date)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":amount", $data->budget_amount);
    $stmt->bindParam(":period", $data->budget_period);
    $stmt->bindParam(":start_date", $data->start_date);
    $stmt->bindParam(":end_date", $data->end_date);
    
    if($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Budget created"));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to create budget"));
    }
}

function updateBudget($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "UPDATE budgets SET budget_amount = :amount WHERE budget_id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":amount", $data->budget_amount);
    $stmt->bindParam(":id", $data->budget_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Budget updated"));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to update budget"));
    }
}

function deleteBudget($db, $user_id, $budget_id) {
    $query = "DELETE FROM budgets WHERE budget_id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $budget_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Budget deleted"));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to delete budget"));
    }
}
?>