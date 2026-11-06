
<?php

// Return a JSON response
function respond($success, $message, $data = null, $statusCode = 200)
{
    http_response_code($statusCode);

    header("Content-Type: application/json; charset=UTF-8");

    $response = [
        "success" => $success,
        "message" => $message
    ];

    if ($data !== null) {
        $response["data"] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

    exit;
}

// Read JSON input from Postman
function getJsonInput()
{
    $input = file_get_contents("php://input");

    $data = json_decode($input, true);

    if (!is_array($data)) {
        respond(false, "Invalid JSON input.", null, 400);
    }

    return $data;
}
