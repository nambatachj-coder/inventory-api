
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET ALL SUPPLIERS
if ($method === 'GET' && !isset($_GET['id'])) {

    $query = "SELECT * FROM suppliers ORDER BY id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    respond(true, "Suppliers retrieved successfully.", $stmt->fetchAll());
}

// GET SINGLE SUPPLIER
elseif ($method === 'GET' && isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Invalid supplier ID.", null, 400);
    }

    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $supplier = $stmt->fetch();

    if (!$supplier) {
        respond(false, "Supplier not found.", null, 404);
    }

    respond(true, "Supplier retrieved successfully.", $supplier);
}

// CREATE SUPPLIER
elseif ($method === 'POST') {

    $data = getJsonInput();

    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');

    if ($name === '') {
        respond(false, "Supplier name is required.", null, 400);
    }

    if (strlen($name) > 150) {
        respond(false, "Supplier name must not exceed 150 characters.", null, 400);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, "Invalid email address.", null, 400);
    }

    if (strlen($email) > 150) {
        respond(false, "Email must not exceed 150 characters.", null, 400);
    }

    if (strlen($phone) > 30) {
        respond(false, "Phone must not exceed 30 characters.", null, 400);
    }

    $query = "INSERT INTO suppliers (name, email, phone, address)
              VALUES (:name, :email, :phone, :address)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        'name' => $name,
        'email' => $email !== '' ? $email : null,
        'phone' => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null
    ]);

    respond(true, "Supplier created successfully.", [
        "supplier_id" => $db->lastInsertId()
    ], 201);
}

// UPDATE SUPPLIER
elseif ($method === 'PUT') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid supplier ID is required.", null, 400);
    }

    $check = $db->prepare("SELECT id FROM suppliers WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        respond(false, "Supplier not found.", null, 404);
    }

    $data = getJsonInput();

    $fields = [];
    $params = ['id' => $id];

    if (array_key_exists('name', $data)) {

        $name = trim($data['name']);

        if ($name === '' || strlen($name) > 150) {
            respond(false, "Invalid supplier name.", null, 400);
        }

        $fields[] = "name = :name";
        $params['name'] = $name;
    }

    if (array_key_exists('email', $data)) {

        $email = trim($data['email'] ?? '');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(false, "Invalid email address.", null, 400);
        }

        if (strlen($email) > 150) {
            respond(false, "Email must not exceed 150 characters.", null, 400);
        }

        $fields[] = "email = :email";
        $params['email'] = $email !== '' ? $email : null;
    }

    if (array_key_exists('phone', $data)) {

        $phone = trim($data['phone'] ?? '');

        if (strlen($phone) > 30) {
            respond(false, "Phone must not exceed 30 characters.", null, 400);
        }

        $fields[] = "phone = :phone";
        $params['phone'] = $phone !== '' ? $phone : null;
    }

    if (array_key_exists('address', $data)) {

        $address = trim($data['address'] ?? '');

        $fields[] = "address = :address";
        $params['address'] = $address !== '' ? $address : null;
    }

    if (empty($fields)) {
        respond(false, "No fields provided for update.", null, 400);
    }

    $query = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    respond(true, "Supplier updated successfully.");
}

// DELETE SUPPLIER
elseif ($method === 'DELETE') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid supplier ID is required.", null, 400);
    }

    $check = $db->prepare("SELECT id FROM suppliers WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        respond(false, "Supplier not found.", null, 404);
    }

    try {

        $delete = $db->prepare("DELETE FROM suppliers WHERE id = :id");
        $delete->execute(['id' => $id]);

    } catch (PDOException $e) {

        respond(false, "Cannot delete supplier because it may be used by existing products.", null, 409);
    }

    respond(true, "Supplier deleted successfully.");
}

// INVALID METHOD
else {

    respond(false, "Method not allowed.", null, 405);

}
