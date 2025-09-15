<?php
require 'paypal_config.php';
$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? number_format((float)$input['amount'],2,'.','') : null;
if(!$amount){ http_response_code(400); echo json_encode(['error'=>'Missing amount']); exit; }
$base = PAYPAL_ENV === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $base . '/v1/oauth2/token'); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET); curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Accept-Language: en_US']); $res = curl_exec($ch);
if(!$res){ http_response_code(500); echo json_encode(['error'=>curl_error($ch)]); exit; } $data = json_decode($res, true); $token = $data['access_token'] ?? null; curl_close($ch);
if(!$token){ http_response_code(500); echo json_encode(['error'=>'Failed to get access token']); exit; }
$ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $base . '/v2/checkout/orders'); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
$body = json_encode(['intent'=>'CAPTURE','purchase_units'=>[['amount'=>['currency_code'=>'USD','value'=>$amount]]]]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Authorization: Bearer ' . $token]);
$res = curl_exec($ch); if(!$res){ http_response_code(500); echo json_encode(['error'=>curl_error($ch)]); exit; } curl_close($ch);
echo $res;
?>