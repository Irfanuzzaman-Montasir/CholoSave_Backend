<?php
// Include database connection
include 'db.php';

// Set content type to JSON
header("Content-Type: application/json");

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the input data from the frontend
    $input = json_decode(file_get_contents("php://input"), true);

    // Extract and sanitize user input
    $name = htmlspecialchars(trim($input['name']));
    $email = htmlspecialchars(trim($input['email']));
    $phone_number = htmlspecialchars(trim($input['phone_number']));
    $password = htmlspecialchars(trim($input['password']));

    // Check if all fields are filled
    if (empty($name) || empty($email) || empty($phone_number) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit();
    }

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Check if email already exists
    $check_email_query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email already registered."]);
        exit();
    }

    // Insert user into the database
    $insert_query = "INSERT INTO users (name, email, phone_number, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssss", $name, $email, $phone_number, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User registered successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to register user."]);
    }
} else {
    // Invalid request method
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>
