
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

$database = new Database();
$db = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];

// LOGIN - POST
if ($method === 'POST') {

    $data = getJsonInput();

    if (empty($data['email']) || empty($data['password'])) {
        respond(false, "Email and password are required.", null, 400);
    }

    $email = trim($data['email']);
    $password = $data['password'];

    $query = "SELECT id, name, email, password, role
              FROM users
              WHERE email = :email
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        respond(false, "Invalid email or password.", null, 401);
    }

    // Generate API token
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

    $update = "UPDATE users
               SET api_token_hash = :token_hash,
                   token_expires_at = :expires_at
               WHERE id = :id";

    $stmt = $db->prepare($update);

    $stmt->execute([
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'id' => $user['id']
    ]);

    unset($user['password']);

    respond(true, "Login successful.", [
        "user" => $user,
        "token" => $token,
        "expires_at" => $expiresAt
    ]);
}

// GET USER INFORMATION
elseif ($method === 'GET') {

    require_once __DIR__ . '/../includes/auth_middleware.php';

    respond(true, "Authenticated user.", [
        "user" => $authenticatedUser
    ]);
}

// LOGOUT
elseif ($method === 'DELETE') {

    require_once __DIR__ . '/../includes/auth_middleware.php';

    $query = "UPDATE users
              SET api_token_hash = NULL,
                  token_expires_at = NULL
              WHERE id = :id";

    $stmt = $db->prepare($query);

    $stmt->execute([
        'id' => $authenticatedUser['id']
    ]);

    respond(true, "Logout successful.");
}

// INVALID METHOD
else {

    respond(false, "Method not allowed.", null, 405);

}
