<?php
// initiate_payment.php

include 'sslcommerz_config.php';

$config = include 'sslcommerz_config.php';
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['amount'], $data['group_id'], $data['user_id'], $data['payment_method'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters.']);
        exit();
    }

    $amount = floatval($data['amount']);
    $group_id = intval($data['group_id']);
    $user_id = intval($data['user_id']);
    $payment_method = $data['payment_method'];

    // Build SSLCommerz payload
    $post_data = [
        'store_id' => $config['store_id'],
        'store_passwd' => $config['store_password'],
        'total_amount' => $amount,
        'currency' => 'BDT',
        'tran_id' => uniqid("SSL_"),
        'success_url' => 'http://yourdomain.com/success.php',
        'fail_url' => 'http://yourdomain.com/fail.php',
        'cancel_url' => 'http://yourdomain.com/cancel.php',
        'emi_option' => 0,
        'cus_name' => $data['customer_name'] ?? 'N/A',
        'cus_email' => $data['customer_email'] ?? 'N/A',
        'cus_phone' => $data['customer_phone'] ?? 'N/A',
        'value_a' => $user_id,
        'value_b' => $group_id,
        'value_c' => $payment_method
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['api_url'] . '/gwprocess/v4/api.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $ssl_response = json_decode($response, true);

    if (isset($ssl_response['GatewayPageURL']) && $ssl_response['GatewayPageURL'] !== '') {
        echo json_encode(['status' => 'success', 'url' => $ssl_response['GatewayPageURL']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to initiate payment.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
