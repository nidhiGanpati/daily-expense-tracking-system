<?php
/**
 * Expense Management API
 * CRUD operations for expenses
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

session_start();

// Check authentication
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized"));
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($method) {
    case 'GET':
        if($action === 'all') {
            getAllExpenses($db, $user_id);
        } elseif($action === 'filter') {
            filterExpenses($db, $user_id);
        } elseif($action === 'stats') {
            getExpenseStats($db, $user_id);
        } elseif(isset($_GET['id'])) {
            getExpenseById($db, $user_id, $_GET['id']);
        } else {
            getAllExpenses($db, $user_id);
        }
        break;
    case 'POST':
        createExpense($db, $user_id);
        break;
    case 'PUT':
        updateExpense($db, $user_id);
        break;
    case 'DELETE':
        if(isset($_GET['id'])) {
            deleteExpense($db, $user_id, $_GET['id']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
}

/**
 * Get all expenses for user
 */
function getAllExpenses($db, $user_id) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $query = "SELECT * FROM expenses 
              WHERE user_id = :user_id 
              ORDER BY expense_date DESC, created_at DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM expenses WHERE user_id = :user_id";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(":user_id", $user_id);
    $count_stmt->execute();
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode(array(
        "success" => true,
        "data" => $expenses,
        "total" => $total,
        "limit" => $limit,
        "offset" => $offset
    ));
}

/**
 * Filter expenses
 */
function filterExpenses($db, $user_id) {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $query = "SELECT * FROM expenses WHERE user_id = :user_id";
    $params = array(":user_id" => $user_id);
    
    if(!empty($category)) {
        $query .= " AND category = :category";
        $params[":category"] = $category;
    }
    
    if(!empty($start_date)) {
        $query .= " AND expense_date >= :start_date";
        $params[":start_date"] = $start_date;
    }
    
    if(!empty($end_date)) {
        $query .= " AND expense_date <= :end_date";
        $params[":end_date"] = $end_date;
    }
    
    if(!empty($search)) {
        $query .= " AND (description LIKE :search OR category LIKE :search)";
        $params[":search"] = "%$search%";
    }
    
    $query .= " ORDER BY expense_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array("success" => true, "data" => $expenses));
}

/**
 * Get expense statistics
 */
function getExpenseStats($db, $user_id) {
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    
    // Total expenses
    $total_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = :user_id";
    
    // Category breakdown
    $category_query = "SELECT category, SUM(amount) as total, COUNT(*) as count 
                       FROM expenses WHERE user_id = :user_id 
                       GROUP BY category ORDER BY total DESC";
    
    // Monthly trend
    $trend_query = "SELECT DATE_FORMAT(expense_date, '%Y-%m') as month, 
                    SUM(amount) as total 
                    FROM expenses WHERE user_id = :user_id 
                    GROUP BY DATE_FORMAT(expense_date, '%Y-%m') 
                    ORDER BY month DESC LIMIT 12";
    
    $stmt1 = $db->prepare($total_query);
    $stmt1->bindParam(":user_id", $user_id);
    $stmt1->execute();
    $total = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt2 = $db->prepare($category_query);
    $stmt2->bindParam(":user_id", $user_id);
    $stmt2->execute();
    $categories = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt3 = $db->prepare($trend_query);
    $stmt3->bindParam(":user_id", $user_id);
    $stmt3->execute();
    $trends = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        "success" => true,
        "total" => $total,
        "categories" => $categories,
        "trends" => $trends
    ));
}

/**
 * Create new expense
 */
function createExpense($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(empty($data->amount) || empty($data->category) || empty($data->expense_date)) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Required fields missing"));
        return;
    }
    
    $query = "INSERT INTO expenses (user_id, amount, category, description, expense_date, payment_method) 
              VALUES (:user_id, :amount, :category, :description, :expense_date, :payment_method)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":amount", $data->amount);
    $stmt->bindParam(":category", $data->category);
    $stmt->bindParam(":description", $data->description);
    $stmt->bindParam(":expense_date", $data->expense_date);
    $payment = isset($data->payment_method) ? $data->payment_method : 'Cash';
    $stmt->bindParam(":payment_method", $payment);
    
    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array(
            "success" => true,
            "message" => "Expense created successfully",
            "expense_id" => $db->lastInsertId()
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to create expense"));
    }
}

/**
 * Update expense
 */
function updateExpense($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"));
    
    if(empty($data->expense_id)) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Expense ID required"));
        return;
    }
    
    $query = "UPDATE expenses SET 
              amount = :amount, 
              category = :category, 
              description = :description, 
              expense_date = :expense_date, 
              payment_method = :payment_method 
              WHERE expense_id = :expense_id AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":amount", $data->amount);
    $stmt->bindParam(":category", $data->category);
    $stmt->bindParam(":description", $data->description);
    $stmt->bindParam(":expense_date", $data->expense_date);
    $stmt->bindParam(":payment_method", $data->payment_method);
    $stmt->bindParam(":expense_id", $data->expense_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Expense updated successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to update expense"));
    }
}

/**
 * Delete expense
 */
function deleteExpense($db, $user_id, $expense_id) {
    $query = "DELETE FROM expenses WHERE expense_id = :expense_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":expense_id", $expense_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Expense deleted successfully"));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Failed to delete expense"));
    }
}
?>
