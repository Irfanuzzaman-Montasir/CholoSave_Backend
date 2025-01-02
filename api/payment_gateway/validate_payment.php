<?php
// validate_payment.php

include 'sslcommerz_config.php';
include 'db.php';

header("Content-Type: application/json");

$config = include 'sslcommerz_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tran_id = $_POST['tran_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$tran_id || !$amount || !$status) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters.']);
        exit();
    }

    if ($status === 'VALID') {
        $validation_url = $config['api_url'] . "/validator/api/validationserverAPI.php?tran_id=$tran_id&store_id=" . $config['store_id'] . "&store_passwd=" . urlencode($config['store_password']) . "&v=1&format=json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validation_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if ($response_data['status'] === 'VALID' && $response_data['amount'] == $amount) {
            $stmt = $conn->prepare("INSERT INTO transaction_info (user_id, group_id, amount, transaction_id, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidss", $_POST['value_a'], $_POST['value_b'], $amount, $tran_id, $_POST['value_c']);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Payment validated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Payment validation failed.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Payment failed.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
