<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();
 

$query = "SELECT i.id, i.code, i.description, i.unit_price, i.tax
          FROM items i
          JOIN companies c ON c.id = i.id_company
          JOIN company_has_user chu ON chu.company_id = c.id
          JOIN users u ON u.id = chu.user_id
          WHERE u.id = :user_id and c.id = :company_id";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
$stmt->bindParam(':company_id', $_SESSION['user']['company_id'], PDO::PARAM_INT);
$stmt->execute();

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// description pode vir em HTML (editor). Para exibição em selects/tabelas,
// mandamos também uma versão em texto puro.
foreach ($items as &$it) {
    if (isset($it['description']) && is_string($it['description'])) {
        $plain = strip_tags($it['description']);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $it['description_plain'] = trim($plain);
    }
}
unset($it);

echo json_encode($items);
?>
