<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET ALL ACTIVITY LOGS
if ($method === 'GET' && !isset($_GET['id'])) {

    $query = "SELECT al.*, u.name AS user_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              ORDER BY al.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    respond(true, "Activity logs retrieved successfully.", $stmt->fetchAll());
}

// GET SINGLE ACTIVITY LOG
elseif ($method === 'GET' && isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Invalid activity log ID.", null, 400);
    }

    $query = "SELECT al.*, u.name AS user_name
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);

    $log = $stmt->fetch();

    if (!$log) {
        respond(false, "Activity log not found.", null, 404);
    }

    respond(true, "Activity log retrieved successfully.", $log);
}

// CREATE ACTIVITY LOG
elseif ($method === 'POST') {

    $data = getJsonInput();

    $action = trim($data['action'] ?? '');
    $description = trim($data['description'] ?? '');

    if ($action === '') {
        respond(false, "Action is required.", null, 400);
    }

    if (strlen($action) > 100) {
        respond(false, "Action must not exceed 100 characters.", null, 400);
    }

    $query = "INSERT INTO activity_logs
              (user_id, action, description)
              VALUES (:user_id, :action, :description)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        'user_id' => $authenticatedUser['id'],
        'action' => $action,
        'description' => $description !== '' ? $description : null
    ]);

    respond(true, "Activity log created successfully.", [
        "log_id" => $db->lastInsertId(),
        "user_id" => $authenticatedUser['id'],
        "action" => $action
    ], 201);
}

// INVALID METHOD
else {

    respond(false, "Method not allowed.", null, 405);

}