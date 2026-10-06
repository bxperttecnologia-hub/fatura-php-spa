<?php

/*
 * Cliente X — cliente anónimo (consumidor final).
 *
 * Como invoices.contact_id aponta sempre para um contacto, o "Cliente X" é um
 * contacto técnico, criado uma vez por empresa na primeira fatura anónima.
 * O create_invoices.js reconhece-o pelo e-mail (ANON_EMAIL) e mostra-o como
 * o bloco "Cliente X" em vez de o listar como cliente normal.
 *
 * ATENÇÃO: o e-mail abaixo tem de ser igual a ANON_EMAIL no create_invoices.js.
 */
const ANON_CONTACT_NAME  = 'Cliente X';
const ANON_CONTACT_EMAIL = 'cliente-x@anonimo.local';

function get_anonymous_contact_id(PDO $pdo, int $companyId): int
{
    $stmt = $pdo->prepare("SELECT id FROM contact WHERE company_id = ? AND email = ? LIMIT 1");
    $stmt->execute([$companyId, ANON_CONTACT_EMAIL]);

    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    // Mesmas colunas que o INSERT normal de contactos; as opcionais ficam a NULL
    $stmt = $pdo->prepare("
        INSERT INTO contact (name,email,contributor,address,po_box,country,city,company_id)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        ANON_CONTACT_NAME,
        ANON_CONTACT_EMAIL,
        null,
        null,
        null,
        null,
        null,
        $companyId
    ]);

    return (int)$pdo->lastInsertId();
}
