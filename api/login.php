<?php
include 'db.php';
include 'session.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    $email = htmlspecialchars(trim($input['email']));
    $password = htmlspecialchars(trim($input['password']));

    if (empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Email and password are required."]);
        exit();
    }

    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        exit();
    }

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id']; // Save user ID in session

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
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>
