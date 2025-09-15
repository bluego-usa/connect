<?php
require 'paypal_config.php';
require 'config.php';
if(session_status() === PHP_SESSION_NONE) session_start();
$input = json_decode(file_get_contents('php://input'), true);
$orderID = $input['orderID'] ?? null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ($input['user_id'] ?? null);
$task_id = $input['task_id'] ?? null;
if(!$orderID || !$user_id || !$task_id){ http_response_code(400); echo json_encode(['error'=>'Missing parameters']); exit; }
$base = PAYPAL_ENV === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $base . '/v1/oauth2/token'); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET); curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Accept-Language: en_US']); $res = curl_exec($ch);
if(!$res){ http_response_code(500); echo json_encode(['error'=>curl_error($ch)]); exit; } $data = json_decode($res, true); $token = $data['access_token'] ?? null; curl_close($ch);
if(!$token){ http_response_code(500); echo json_encode(['error'=>'Failed to get access token']); exit; }
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $base . "/v2/checkout/orders/{$orderID}/capture"); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Authorization: Bearer ' . $token]);
$res = curl_exec($ch); if(!$res){ http_response_code(500); echo json_encode(['error'=>curl_error($ch)]); exit; } curl_close($ch);
$resp = json_decode($res, true);
$status = $resp['status'] ?? '';
$captures = $resp['purchase_units'][0]['payments']['captures'][0] ?? null;
if(!$captures){ http_response_code(400); echo json_encode(['error'=>'Payment not completed','details'=>$resp]); exit; }
$txn = $captures['id']; $amount = $captures['amount']['value'] ?? 0;
try{
    $stmt = $pdo->prepare('INSERT INTO payments (user_id, task_id, amount, paypal_txn, status, created_at) VALUES (?,?,?,?,?,NOW())');
    $stmt->execute([$user_id, $task_id, $amount, $txn, 'COMPLETED']);
    echo json_encode(['success'=>true,'txn'=>$txn]);
} catch(Exception $e){
    http_response_code(500); echo json_encode(['error'=>'DB error: '.$e->getMessage()]);
}
?>