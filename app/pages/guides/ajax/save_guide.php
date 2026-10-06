<?php
// guides/ajax/save_guides.php

header('Content-Type: application/json');
require_once '../../../app/config/db.php';


try {
    // Lê os dados do POST
    $data = $_POST['guide'];
    $items = $_POST['items'];

    // Converte para array associativo se necessário (em alguns casos pode vir em JSON)
    if (is_string($data)) $data = json_decode($data, true);
    if (is_string($items)) $items = json_decode($items, true);

    // --- 1. Insere a guia principal ---
    $stmt = $pdo->prepare("INSERT INTO guides (
        contact_id, vehicle_plate, cargo_date, transport_reason, observations, retention,
        cargo_address, cargo_city, cargo_po_box,
        delivery_address, delivery_city, delivery_po_box,
        document_date, reference, series, currency,
        total_sum, total_discount, subtotal_without_tax, total_tax, retention_value, final_total
    ) VALUES (
        :contact_id, :vehicle_plate, :cargo_date, :transport_reason, :observations, :retention,
        :cargo_address, :cargo_city, :cargo_po_box,
        :delivery_address, :delivery_city, :delivery_po_box,
        :document_date, :reference, :series, :currency,
        :total_sum, :total_discount, :subtotal_without_tax, :total_tax, :retention_value, :final_total
    )");

    // Encontre cada campo no array $data, ajuste os nomes conforme seu form/JS
    $stmt->execute([
        ':contact_id' => $data['contact_id'],
        ':vehicle_plate' => $data['matricula'],
        ':cargo_date' => $data['data_carga'],
        ':transport_reason' => $data['motivo_transporte'] ?? null,
        ':observations' => $data['observacoes'],
        ':retention' => $data['retencao'],
        ':cargo_address' => $data['endereco_carga'],
        ':cargo_city' => $data['cidade_carga'],
        ':cargo_po_box' => $data['caixa_postal_carga'],
        ':delivery_address' => $data['endereco_entrega'],
        ':delivery_city' => $data['cidade_entrega'],
        ':delivery_po_box' => $data['caixa_postal_entrega'],
        ':document_date' => $data['data_documento'],
        ':reference' => $data['referencia'],
        ':series' => $data['serie'],
        ':currency' => $data['moeda'],
        ':total_sum' => $data['total_sum'] ?? 0,
        ':total_discount' => $data['total_discount'] ?? 0,
        ':subtotal_without_tax' => $data['subtotal_without_tax'] ?? 0,
        ':total_tax' => $data['total_tax'] ?? 0,
        ':retention_value' => $data['retention_value'] ?? 0,
        ':final_total' => $data['final_total'] ?? 0,
    ]);
    $guide_id = $pdo->lastInsertId();

    // --- 2. Insere os itens ---
    $stmtItem = $pdo->prepare("INSERT INTO guide_items
        (guide_id, item_id, description, unit_price, quantity, tax, discount)
        VALUES (:guide_id, :item_id, :description, :unit_price, :quantity, :tax, :discount)");

    foreach ($items as $item) {
        $stmtItem->execute([
            ':guide_id' => $guide_id,
            ':item_id' => $item['id'],
            ':description' => $item['description'] ?? '',
            ':unit_price' => $item['unit_price'],
            ':quantity' => $item['quantity'],
            ':tax' => $item['tax'],
            ':discount' => $item['discount'] ?? 0,
        ]);
    }

    echo json_encode(['success' => true, 'guide_id' => $guide_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
