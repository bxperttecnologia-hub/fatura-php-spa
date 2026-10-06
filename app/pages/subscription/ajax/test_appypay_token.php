<?php
require_once __DIR__ . '/../../../app/helpers/appypay.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $token = appypay_get_token();
  echo json_encode(['success'=>true,'token_prefix'=>substr($token,0,12)]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
