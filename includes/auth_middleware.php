
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

// Get Authorization header
$headers = getallheaders();

$authHeader = $headers['Authorization']
    ?? $headers['authorization']
    ?? '';

if (empty($authHeader)) {
    respond(false, "Authorization token is required.", null, 401);
}

// Extract Bearer token
if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    respond(false, "Invalid authorization format.", null, 401);
}

$token = $matches[1];

$tokenHash = hash('sha256', $token);

// Connect to database
$database = new Database();
$db = $database->connect();

// Verify token
$query = "SELECT id, name, email, role
          FROM users
          WHERE api_token_hash = :token_hash
          AND token_expires_at > NOW()
          LIMIT 1";

$stmt = $db->prepare($query);

$stmt->execute([
    'token_hash' => $tokenHash
]);

$authenticatedUser = $stmt->fetch();

if (!$authenticatedUser) {
    respond(false, "Invalid or expired token.", null, 401);
}
