
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// =================================
// GET ALL PRODUCTS
// =================================
if ($method === 'GET' && !isset($_GET['id'])) {

    $query = "SELECT * FROM products ORDER BY id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $products = $stmt->fetchAll();

    respond(true, "Products retrieved successfully.", $products);
}

// =================================
// GET SINGLE PRODUCT
// =================================
elseif ($method === 'GET' && isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Invalid product ID.", null, 400);
    }

    $query = "SELECT * FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);

    $product = $stmt->fetch();

    if (!$product) {
        respond(false, "Product not found.", null, 404);
    }

    respond(true, "Product retrieved successfully.", $product);
}

// =================================
// CREATE PRODUCT
// =================================
elseif ($method === 'POST') {

    $data = getJsonInput();

    $required = ['category_id', 'sku', 'name'];

    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            respond(false, "$field is required.", null, 400);
        }
    }

    $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT);
    $sku = trim($data['sku']);
    $name = trim($data['name']);

    $supplierId = $data['supplier_id'] ?? null;
    $description = $data['description'] ?? null;
    $price = $data['price'] ?? 0;
    $quantity = $data['quantity'] ?? 0;
    $minimumStock = $data['minimum_stock'] ?? 5;

    if (!$categoryId || $categoryId < 1) {
        respond(false, "Invalid category ID.", null, 400);
    }

    if ($supplierId !== null) {
        $supplierId = filter_var($supplierId, FILTER_VALIDATE_INT);

        if (!$supplierId || $supplierId < 1) {
            respond(false, "Invalid supplier ID.", null, 400);
        }
    }

    if (!is_numeric($price) || $price < 0 ||
        !filter_var($quantity, FILTER_VALIDATE_INT) ||
        $quantity < 0 ||
        !filter_var($minimumStock, FILTER_VALIDATE_INT) ||
        $minimumStock < 0) {
        respond(false, "Invalid price or stock values.", null, 400);
    }

    $check = $db->prepare(
        "SELECT id FROM products WHERE sku = :sku"
    );
    $check->execute(['sku' => $sku]);

    if ($check->fetch()) {
        respond(false, "SKU already exists.", null, 409);
    }

    $query = "INSERT INTO products
              (category_id, supplier_id, sku, name, description,
               price, quantity, minimum_stock)
              VALUES
              (:category_id, :supplier_id, :sku, :name, :description,
               :price, :quantity, :minimum_stock)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        'category_id' => $categoryId,
        'supplier_id' => $supplierId,
        'sku' => $sku,
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'quantity' => $quantity,
        'minimum_stock' => $minimumStock
    ]);

    $newId = $db->lastInsertId();

    respond(true, "Product created successfully.", [
        "product_id" => $newId
    ], 201);
}

// =================================
// UPDATE PRODUCT
// =================================
elseif ($method === 'PUT') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid product ID is required.", null, 400);
    }

    $data = getJsonInput();

    $query = "SELECT id FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);

    if (!$stmt->fetch()) {
        respond(false, "Product not found.", null, 404);
    }

    $fields = [
        'category_id',
        'supplier_id',
        'sku',
        'name',
        'description',
        'price',
        'quantity',
        'minimum_stock'
    ];

    $updates = [];
    $params = ['id' => $id];

    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = :$field";
            $params[$field] = $data[$field];
        }
    }

    if (empty($updates)) {
        respond(false, "No fields provided for update.", null, 400);
    }

    $query = "UPDATE products SET " .
             implode(', ', $updates) .
             " WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    respond(true, "Product updated successfully.");
}

// =================================
// DELETE PRODUCT
// =================================
elseif ($method === 'DELETE') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid product ID is required.", null, 400);
    }

    $query = "DELETE FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) {
        respond(false, "Product not found.", null, 404);
    }

    respond(true, "Product deleted successfully.");
}

else {

    respond(false, "Method not allowed.", null, 405);

}
