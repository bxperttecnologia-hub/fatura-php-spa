<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define o idioma padrão apenas se ainda não foi definido
if (!isset($_SESSION['user']['lang'])) {
    $_SESSION['user']['lang'] = 'angola';
}

// Se a requisição for AJAX e houver um idioma sendo passado, atualiza a sessão
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['lang']) && in_array($_POST['lang'], ['angola', 'brasil'], true)) {
    $_SESSION['user']['lang'] = $_POST['lang'];
    echo json_encode(['status' => 'success', 'lang' => $_SESSION['user']['lang']]);
    exit;
}
 


// Carrega o idioma da sessão
$lang = in_array($_SESSION['user']['lang'], ['angola', 'brasil'], true) ? $_SESSION['user']['lang'] : 'angola';

// Carrega o arquivo de tradução correspondente
// Ficheiro opcional (public/assets/translations/<lang>.php): se não existir, os textos ficam como estão
$__trFile = dirname(__DIR__, 2) . "/public/assets/translations/$lang.php";
$translations = is_file($__trFile) ? (include $__trFile) : [];
if (!is_array($translations)) $translations = [];

// Lista de países disponíveis
$paises = [
    'brasil' => ['nome' => 'Brasil', 'bandeira' => 'https://flagcdn.com/w40/br.png'],
    'angola' => ['nome' => 'Angola', 'bandeira' => 'https://flagcdn.com/w40/ao.png']
];

// Define os valores com base na sessão
$paisSelecionado = $paises[$lang];

// Função para traduzir textos
function t($text)
{
    global $translations;
    return $translations[$text] ?? $text;
}


