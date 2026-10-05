<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
$employee_id = (int)($_POST['employee_id'] ?? 0);
$type = (string)($_POST['type'] ?? ''); // photo|doc1|doc2

$allowed = ['photo' => 'photo_url', 'doc1' => 'doc1_url', 'doc2' => 'doc2_url'];
if(!$company_id || !$employee_id || !isset($allowed[$type])){
  http_response_code(422);
  echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos.']);
  exit;
}

$field = $allowed[$type];

try{
  $stmt = $pdo->prepare("SELECT {$field} FROM employees WHERE id = ? AND company_id = ? LIMIT 1");
  $stmt->execute([$employee_id, $company_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row){
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Funcionário não encontrado.']);
    exit;
  }

  $filename = $row[$field] ?? null;

  // Limpa do banco
  $stmt = $pdo->prepare("UPDATE employees SET {$field} = NULL WHERE id = ? AND company_id = ?");
  $stmt->execute([$employee_id, $company_id]);

  // Remove do disco
  if($filename){
    $filename = basename($filename); // segurança
    if($type === 'photo'){
      $path = __DIR__ . '/../../assets/img/employees/' . $filename;
    } else {
      $path = __DIR__ . '/../../assets/docs/employees/' . $filename;
    }
    if(is_file($path)){
      @unlink($path);
    }
  }

  echo json_encode(['success'=>true]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
