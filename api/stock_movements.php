<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET ALL STOCK MOVEMENTS
if ($method === 'GET' && !isset($_GET['id'])) {

    $query = "SELECT sm.*, p.name AS product_name, u.name AS user_name
              FROM stock_movements sm
              INNER JOIN products p ON sm.product_id = p.id
              INNER JOIN users u ON sm.user_id = u.id
              ORDER BY sm.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    respond(true, "Stock movements retrieved successfully.", $stmt->fetchAll());
}

// GET SINGLE STOCK MOVEMENT
elseif ($method === 'GET' && isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Invalid stock movement ID.", null, 400);
    }

    $query = "SELECT sm.*, p.name AS product_name, u.name AS user_name
              FROM stock_movements sm
              INNER JOIN products p ON sm.product_id = p.id
              INNER JOIN users u ON sm.user_id = u.id
              WHERE sm.id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);

    $movement = $stmt->fetch();

    if (!$movement) {
        respond(false, "Stock movement not found.", null, 404);
    }

    respond(true, "Stock movement retrieved successfully.", $movement);
}

// CREATE STOCK MOVEMENT
elseif ($method === 'POST') {

    $data = getJsonInput();

    $productId = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT);
    $movementType = $data['movement_type'] ?? '';
    $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
    $remarks = trim($data['remarks'] ?? '');

    if (!$productId || $productId < 1) {
        respond(false, "Valid product ID is required.", null, 400);
    }

    if (!in_array($movementType, ['stock_in', 'stock_out'], true)) {
        respond(false, "Movement type must be stock_in or stock_out.", null, 400);
    }

    if ($quantity === false || $quantity === null || $quantity < 1) {
        respond(false, "Quantity must be a positive whole number.", null, 400);
    }

    if (strlen($remarks) > 255) {
        respond(false, "Remarks must not exceed 255 characters.", null, 400);
    }

    try {

        $db->beginTransaction();

        // Lock product row while updating quantity
        $check = $db->prepare(
            "SELECT id, quantity FROM products WHERE id = :id FOR UPDATE"
        );

        $check->execute(['id' => $productId]);

        $product = $check->fetch();

        if (!$product) {
            $db->rollBack();
            respond(false, "Product not found.", null, 404);
        }

        $currentQuantity = (int) $product['quantity'];

        if ($movementType === 'stock_in') {

            $newQuantity = $currentQuantity + $quantity;

        } else {

            if ($quantity > $currentQuantity) {
                $db->rollBack();

                respond(false, "Insufficient stock. Available quantity: " . $currentQuantity, null, 400);
            }

            $newQuantity = $currentQuantity - $quantity;
        }

        // Update product quantity
        $update = $db->prepare(
            "UPDATE products SET quantity = :quantity WHERE id = :id"
        );

        $update->execute([
            'quantity' => $newQuantity,
            'id' => $productId
        ]);

        // Insert stock movement record
        $insert = $db->prepare(
            "INSERT INTO stock_movements
             (product_id, user_id, movement_type, quantity, remarks)
             VALUES
             (:product_id, :user_id, :movement_type, :quantity, :remarks)"
        );

        $insert->execute([
            'product_id' => $productId,
            'user_id' => $authenticatedUser['id'],
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'remarks' => $remarks !== '' ? $remarks : null
        ]);

        $movementId = $db->lastInsertId();

        $db->commit();

        respond(true, "Stock movement recorded successfully.", [
            "movement_id" => $movementId,
            "product_id" => $productId,
            "movement_type" => $movementType,
            "quantity_changed" => $quantity,
            "previous_quantity" => $currentQuantity,
            "new_quantity" => $newQuantity
        ], 201);

    } catch (PDOException $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        respond(false, "Failed to record stock movement.", null, 500);
    }
}

// INVALID METHOD
else {

    respond(false, "Method not allowed.", null, 405);

}