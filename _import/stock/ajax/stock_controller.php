<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;

switch ($action) {
    case 'list':
        listarEstoques($pdo);
        break;

    case 'create':
        criarEstoque($pdo);
        break;

    case 'delete':
        deletarEstoque($pdo);
        break;

    case 'verificar_vazio':
        verificarSeEstoqueEstaVazio($pdo);
        break;

    case 'get':
        buscarEstoquePorId($pdo);
        break;

    case 'list_transfer':
        listarEstoquesParaTransferencia($pdo);
        break;

    case 'update':
        atualizarEstoque($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

function buscarEstoquePorId($pdo)
{
    $id = (int)($_GET['id'] ?? 0);
    $company_id = $_SESSION['user']['company_id'] ?? null;

    if (!$id || !$company_id) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);
    $estoque = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estoque) {
        echo json_encode(['success' => false, 'message' => 'Estoque não encontrado']);
        return;
    }

    echo json_encode(['success' => true, 'estoque' => $estoque]);
}

function atualizarEstoque($pdo)
{
    $id = (int)($_POST['id'] ?? 0);
    $company_id = $_SESSION['user']['company_id'] ?? null;
    $updated_by = $_SESSION['user']['name'] ?? 'Sistema';

    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '#4e73df';
    $icon = $_POST['icon'] ?? 'box';
    $address = $_POST['address'] ?? null;
    $address_number = $_POST['address_number'] ?? null;
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!$id || !$company_id || trim($name) === '') {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios ausentes']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE stocks SET name = ?, description = ?, color = ?, icon = ?, address = ?, 
    address_number = ?, latitude = ?, longitude = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
    $success = $stmt->execute([
        $name,
        $description,
        $color,
        $icon,
        $address,
        $address_number,
        $latitude,
        $longitude,
        $updated_by,
        $id,
        $company_id
    ]);

    echo json_encode(['success' => $success]);
}


function listarEstoques($pdo)
{

    try {

        $company_id = $_SESSION['user']['company_id'] ?? null;

        if (!$company_id) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Empresa não identificada'
            ]);
            return;
        }

        // =========================
        // QUERY PRINCIPAL
        // =========================
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.name,
                s.description,
                s.icon,
                s.address,
                s.address_number,
                s.city,
                s.state,
                s.country,
                s.location_detail,
                s.color,

                COUNT(DISTINCT si.item_id) AS total_items,
                COALESCE(SUM(si.quantity), 0) AS total_quantity,
                COALESCE(SUM(i.unit_price * si.quantity), 0) AS total_stock_value,

                COUNT(DISTINCT CASE 
                    WHEN si.quantity <= si.min_quantity THEN si.item_id 
                END) AS low_stock_items

            FROM stocks s

            LEFT JOIN stock_items si 
                ON si.stock_id = s.id

            LEFT JOIN items i 
                ON i.id = si.item_id
                AND i.company_id = s.company_id

            WHERE s.company_id = :company_id

            GROUP BY s.id
        ");

        $stmt->execute(['company_id' => $company_id]);
        $estoques = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // BUSCAR TODOS ITENS DE UMA VEZ ( evita N+1)
        // =========================
        $stmtItens = $pdo->prepare("
            SELECT 
                si.stock_id,
                i.id,
                i.code,
                i.name,
                si.quantity,
                si.min_quantity,
                (si.quantity * i.unit_price) as total
            FROM stock_items si
            JOIN items i ON i.id = si.item_id
            WHERE i.company_id = ?
            AND i.item_type = 'product'
        ");

        $stmtItens->execute([$company_id]);
        $allItems = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por stock_id
        $itemsByStock = [];
        foreach ($allItems as $item) {
            $itemsByStock[$item['stock_id']][] = $item;
        }

        // =========================
        // PROCESSAMENTO FINAL
        // =========================
        foreach ($estoques as &$estoque) {

            $dados = $itemsByStock[$estoque['id']] ?? [];

            // localização formatada
            $estoque['location_full'] = trim(sprintf(
                "%s, %s, %s - %s",
                $estoque['address'] ?? '',
                $estoque['address_number'] ?? '',
                $estoque['city'] ?? '',
                $estoque['state'] ?? ''
            ), ' ,');

            // gráfico
            $estoque['grafico'] = [
                'labels' => array_column($dados, 'name'),
                'values' => array_map('intval', array_column($dados, 'quantity')),
            ];

            // cor
            $estoque['cor'] = $estoque['color'] ?: gerarCorAleatoria($estoque['id']);

            // =========================
            // LOW STOCK DETALHADO
            // =========================
            $lowItems = array_filter($dados, function ($it) {
                return (int)$it['quantity'] <= (int)$it['min_quantity'];
            });

            $estoque['low_stock_details'] = array_values(array_map(function ($it) {
                return [
                    'code' => $it['code'],
                    'name' => $it['name'],
                    'quantity' => (int)$it['quantity'],
                    'min_quantity' => (int)$it['min_quantity'],
                    'label' => trim(($it['code'] ? $it['code'] . ' - ' : '') . $it['name']),
                ];
            }, $lowItems));
        }

        echo json_encode([
            'success' => true,
            'total' => count($estoques),
            'data' => $estoques
        ]);
    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Erro ao listar estoques',
            'error' => $e->getMessage() // remove em produção
        ]);
    }
}

function listarEstoquesParaTransferencia($pdo)
{
    $company_id = $_SESSION['user']['company_id'] ?? null;
    $current_stock = $_GET['current_stock'] ?? null;

    if (!$company_id) {
        echo json_encode(['success' => false, 'message' => 'Empresa não identificada']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, name FROM stocks WHERE company_id = ? AND id != ?");
    $stmt->execute([$company_id, $current_stock]);
    $estoques = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'estoques' => $estoques]);
}


function criarEstoque($pdo)
{
    $company_id = $_SESSION['user']['company_id'] ?? 1;

    // Dados básicos
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = $_POST['color'] ?? '#4e73df';
    $icon = $_POST['icon'] ?? 'box';
    $location_type = $_POST['location_type'] ?? 'interna';
    $location_detail = $_POST['location_detail'] ?? '';

    // Endereço detalhado
    $display_name = $_POST['display_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $address_number = $_POST['address_number'] ?? '';
    $neighborhood = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $county = $_POST['county'] ?? '';
    $state = $_POST['state'] ?? '';
    $state_district = $_POST['state_district'] ?? '';
    $region = $_POST['region'] ?? '';
    $country = $_POST['country'] ?? '';
    $continent = $_POST['continent'] ?? '';
    $iso_region_code = $_POST['iso_region_code'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';

    // Geolocalização
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    // Dados extras OSM
    $osm_type = $_POST['osm_type'] ?? '';
    $osm_id = $_POST['osm_id'] ?? null;
    $boundingbox = $_POST['boundingbox'] ?? '';
    $place_class = $_POST['place_class'] ?? '';
    $place_type = $_POST['place_type'] ?? '';

    if (trim($name) === '') {
        echo json_encode(['success' => false, 'message' => 'Nome obrigatório']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO stocks (
        company_id, name, description, color, icon, location_type, location_detail,
        display_name, address, address_number, neighborhood, city, county, state,
        state_district, region, country, continent, iso_region_code, zip_code,
        latitude, longitude, osm_type, osm_id, boundingbox, place_class, place_type
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    $success = $stmt->execute([
        $company_id,
        $name,
        $description,
        $color,
        $icon,
        $location_type,
        $location_detail,
        $display_name,
        $address,
        $address_number,
        $neighborhood,
        $city,
        $county,
        $state,
        $state_district,
        $region,
        $country,
        $continent,
        $iso_region_code,
        $zip_code,
        $latitude,
        $longitude,
        $osm_type,
        $osm_id,
        $boundingbox,
        $place_class,
        $place_type
    ]);

    echo json_encode(['success' => $success]);
}


function deletarEstoque($pdo)
{
    $stock_id = (int)($_POST['stock_id'] ?? 0);
    $company_id = $_SESSION['user']['company_id'] ?? null;

    if (!$stock_id || !$company_id) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    // Verifica se há itens vinculados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_items WHERE stock_id = ?");
    $stmt->execute([$stock_id]);
    $total = $stmt->fetchColumn();

    if ($total > 0) {
        echo json_encode(['success' => false, 'message' => 'O estoque possui itens e não pode ser deletado.']);
        return;
    }

    // Deleta o estoque
    $stmt = $pdo->prepare("DELETE FROM stocks WHERE id = ? AND company_id = ?");
    $success = $stmt->execute([$stock_id, $company_id]);

    echo json_encode(['success' => $success]);
}

function verificarSeEstoqueEstaVazio($pdo)
{
    $stock_id = (int)($_GET['stock_id'] ?? 0);

    if (!$stock_id) {
        echo json_encode(['success' => false, 'message' => 'ID do estoque não informado']);
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_items WHERE stock_id = ?");
    $stmt->execute([$stock_id]);
    $total = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'vazio' => ($total == 0)]);
}


function gerarCorAleatoria($semente = null)
{
    if ($semente !== null) {
        srand($semente);
    }

    $cores = [
        '#4e73df',
        '#1cc88a',
        '#36b9cc',
        '#f6c23e',
        '#e74a3b',
        '#858796',
        '#20c997',
        '#6f42c1',
        '#fd7e14',
    ];

    return $cores[array_rand($cores)];
}
