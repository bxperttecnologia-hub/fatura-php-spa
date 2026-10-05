<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define o idioma padrão apenas se ainda não foi definido
if (!isset($_SESSION['user']['lang'])) {
    $_SESSION['user']['lang'] = 'angola';
}

// Se a requisição for AJAX e houver um idioma sendo passado, atualiza a sessão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lang'])) {
    $_SESSION['user']['lang'] = $_POST['lang'];
    echo json_encode(['status' => 'success', 'lang' => $_SESSION['user']['lang']]);
    exit;
}
 


// Carrega o idioma da sessão
$lang = $_SESSION['user']['lang'];

// Carrega o arquivo de tradução correspondente
$translations = include_once "assets/translations/$lang.php";

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


