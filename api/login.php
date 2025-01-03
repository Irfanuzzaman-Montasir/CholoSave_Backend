<?php
include 'db.php';
include 'session.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5175"); // Adjust port as needed
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON data");
        }

        $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($input['password']);

        if (empty($email) || empty($password)) {
            throw new Exception("Email and password are required.");
        }

        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Invalid email or password.");
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            throw new Exception("Invalid email or password.");
        }

        $_SESSION['user_id'] = $user['id'];
        
        echo json_encode([
            "status" => "success",
            "message" => "Login successful.",
            "data" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email'],
                "phone_number" => $user['phone_number']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}