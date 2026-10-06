<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;

switch ($action) {
    case 'list':
        listarItens($pdo);
        break;

    case 'save':
        salvarItens($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

// Retorna itens + nome do estoque
function listarItens($pdo)
{

    try {

        $company_id = $_SESSION['user']['company_id'] ?? null;
        $stock_id   = (int)($_GET['stock_id'] ?? 0);

        if (!$company_id || $stock_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parâmetros inválidos'
            ]);
            return;
        }

        // =========================
        // ITENS DO STOCK
        // =========================
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.code,
                i.name,
                i.description,
                i.unit_price,
                i.currency,
                i.item_type,
                i.track_stock,
                i.tax,

                si.quantity,
                si.min_quantity,

                c.iso_code,
                c.symbol,
                c.position,

                cp.vat_regime,

                (COALESCE(si.quantity, 0) * COALESCE(i.unit_price, 0)) AS total_value,

                CASE 
                    WHEN si.quantity IS NULL THEN 'no_stock'
                    WHEN si.quantity <= si.min_quantity THEN 'low'
                    ELSE 'ok'
                END AS stock_status

            FROM stock_items si

            INNER JOIN items i 
                ON i.id = si.item_id

            LEFT JOIN currencies c 
                ON c.iso_code = i.currency

            INNER JOIN companies cp 
                ON cp.id = i.company_id 

            WHERE 
                si.stock_id = :stock_id
                AND i.company_id = :company_id
                AND i.item_type = 'product'

            ORDER BY i.name ASC
        ");

        $stmt->execute([
            'stock_id'   => $stock_id,
            'company_id' => $company_id
        ]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // INFO DO STOCK
        // =========================
        $stmt2 = $pdo->prepare("
            SELECT 
                id,
                name,
                color,
                updated_at,
                updated_by,
                address,
                city,
                state
            FROM stocks 
            WHERE id = ? AND company_id = ?
        ");

        $stmt2->execute([$stock_id, $company_id]);
        $stock = $stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Stock não encontrado'
            ]);
            return;
        }

        // =========================
        // METRICS
        // =========================
        $totalItems = count($items);
        $totalQuantity = array_sum(array_column($items, 'quantity'));
        $totalValue = array_sum(array_column($items, 'total_value'));

        $lowStock = array_filter($items, function ($i) {
            return $i['stock_status'] === 'low';
        });

        echo json_encode([
            'success' => true,
            'stock' => $stock,
            'metrics' => [
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity,
                'total_value' => round($totalValue, 2),
                'low_stock_items' => count($lowStock)
            ],
            'items' => $items
        ]);
    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Erro ao listar itens',
            'error' => $e->getMessage() // remover em produção
        ]);
    }
}

function salvarItens($pdo)
{
    $stock_id = (int)($_POST['stock_id'] ?? 0);
    $items = json_decode($_POST['items'] ?? '[]', true);
    $success = true;
    $retorno = [];

    // Atualiza o stock pai (data e usuário) ao modificar itens
    $currentUser = $_SESSION['user']['name'] ?? 'Sistema';
    $stmtUpd = $pdo->prepare("UPDATE stocks SET updated_at = NOW(), updated_by = ? WHERE id = ?");
    $stmtUpd->execute([$currentUser, $stock_id]);

    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        $code = $item['code'] ?? null;
        $name = $item['name'] ?? '';
        $category = $item['category'] ?? '';
        $quantity = (int)($item['quantity'] ?? 0);
        $min_quantity = isset($item['min_quantity']) ? (int)$item['min_quantity'] : 1;
        $unit_price = (float)($item['unit_price'] ?? 0.0);
        $currency = $item['currency'] ?? 'AOA';

        if ($id) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE stock_items
                SET code = ?, name = ?, category = ?, quantity = ?, min_quantity = ?, unit_price = ?, updated_at = NOW(), currency = ?
                WHERE id = ? AND stock_id = ?
            ");
            $success = $stmt->execute([$code, $name, $category, $quantity, $min_quantity, $unit_price, $currency, $id, $stock_id]);

            $retorno[] = [
                'temp_id' => $id,
                'id' => $id
            ];
        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO stock_items (stock_id, code, name, category, quantity, min_quantity, unit_price, currency)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([$stock_id, $code, $name, $category, $quantity, $min_quantity, $unit_price, $currency]);

            $newId = $pdo->lastInsertId();
            $retorno[] = [
                'temp_id' => null,
                'id' => $newId
            ];
        }
    }

    // Busca dados atualizados do stock para retornar ao frontend
    $stmtGet = $pdo->prepare("SELECT updated_at, updated_by FROM stocks WHERE id = ?");
    $stmtGet->execute([$stock_id]);
    $stockInfo = $stmtGet->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $success,
        'items' => $retorno,
        'stock_update' => $stockInfo
    ]);
}
