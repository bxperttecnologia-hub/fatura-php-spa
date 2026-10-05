<?php

/**
 * Esta página foi substituída por um modal em contacts.php (registo/edição
 * de cliente sem sair da lista) — ver contacts/partials/contact_form_modal.php
 * e contacts/register_contact.js.
 *
 * Mantido apenas como redirecionamento, para não partir favoritos/links
 * antigos que ainda apontem para "register_contact.php" ou
 * "register_contact.php?id=123".
 */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

header('Location: contacts.php' . ($id > 0 ? '?edit=' . $id : '?new=1'));
exit;
