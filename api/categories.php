
Those markers must not be inside your PHP file.

### Step 1 — Fix your file

Open:

`C:\xampp\htdocs\inventory-api\api\categories.php`

1. Press `Ctrl + A`.
2. Delete everything.
3. Copy the complete corrected code below.
4. Paste it into the file.
5. Save with `Ctrl + S`.

### Step 2 — Corrected complete code

<WritingBlock id="72419" variant="standard">```php
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// GET ALL CATEGORIES
if ($method === 'GET' && !isset($_GET['id'])) {

    $query = "SELECT * FROM categories ORDER BY id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    respond(true, "Categories retrieved successfully.", $stmt->fetchAll());
}

// GET SINGLE CATEGORY
elseif ($method === 'GET' && isset($_GET['id'])) {

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Invalid category ID.", null, 400);
    }

    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $category = $stmt->fetch();

    if (!$category) {
        respond(false, "Category not found.", null, 404);
    }

    respond(true, "Category retrieved successfully.", $category);
}

// CREATE CATEGORY
elseif ($method === 'POST') {

    $data = getJsonInput();

    $name = trim($data['name'] ?? '');
    $description = $data['description'] ?? null;

    if ($name === '') {
        respond(false, "Category name is required.", null, 400);
    }

    if (strlen($name) > 100) {
        respond(false, "Category name must not exceed 100 characters.", null, 400);
    }

    $check = $db->prepare("SELECT id FROM categories WHERE name = :name");
    $check->execute(['name' => $name]);

    if ($check->fetch()) {
        respond(false, "Category name already exists.", null, 409);
    }

    $query = "INSERT INTO categories (name, description)
              VALUES (:name, :description)";

    $stmt = $db->prepare($query);
    $stmt->execute([
        'name' => $name,
        'description' => $description
    ]);

    respond(true, "Category created successfully.", [
        "category_id" => $db->lastInsertId()
    ], 201);
}

// UPDATE CATEGORY
elseif ($method === 'PUT') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid category ID is required.", null, 400);
    }

    $data = getJsonInput();

    $check = $db->prepare("SELECT id FROM categories WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        respond(false, "Category not found.", null, 404);
    }

    $fields = [];
    $params = ['id' => $id];

    if (array_key_exists('name', $data)) {

        $name = trim($data['name']);

        if ($name === '' || strlen($name) > 100) {
            respond(false, "Invalid category name.", null, 400);
        }

        $duplicate = $db->prepare(
            "SELECT id FROM categories WHERE name = :name AND id != :id"
        );

        $duplicate->execute([
            'name' => $name,
            'id' => $id
        ]);

        if ($duplicate->fetch()) {
            respond(false, "Category name already exists.", null, 409);
        }

        $fields[] = "name = :name";
        $params['name'] = $name;
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = :description";
        $params['description'] = $data['description'];
    }

    if (empty($fields)) {
        respond(false, "No fields provided for update.", null, 400);
    }

    $query = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    respond(true, "Category updated successfully.");
}

// DELETE CATEGORY
elseif ($method === 'DELETE') {

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        respond(false, "Valid category ID is required.", null, 400);
    }

    $check = $db->prepare("SELECT id FROM categories WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        respond(false, "Category not found.", null, 404);
    }

    try {

        $delete = $db->prepare("DELETE FROM categories WHERE id = :id");
        $delete->execute(['id' => $id]);

    } catch (PDOException $e) {

        respond(false, "Cannot delete category because it may be used by existing products.", null, 409);
    }

    respond(true, "Category deleted successfully.");
}

// INVALID METHOD
else {

    respond(false, "Method not allowed.", null, 405);

}