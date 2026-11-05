<?php

/**
 * Senha temporária curta e legível (por omissão 8 caracteres), enviada por SMS/e-mail.
 * Letras maiúsculas, minúsculas e algarismos, sem os que se confundem (0 O o, 1 l I).
 * Tem sempre pelo menos uma maiúscula, uma minúscula e um algarismo.
 * O utilizador é obrigado a trocá-la no primeiro acesso (must_change_password).
 */
function bx_generate_temp_password(int $length = 8): string
{
    $length = max(6, min(8, $length));
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
    $all = implode('', $sets);

    $chars = [];
    foreach ($sets as $set) {
        $chars[] = $set[random_int(0, strlen($set) - 1)];
    }
    while (count($chars) < $length) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {   // baralha (Fisher–Yates com random_int)
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
