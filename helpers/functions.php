<?php
function gerarDropdownPaises($paises, $paisSelecionado)
{
    ob_start(); // Inicia buffer para capturar o HTML
?>
    <div class="dropdown">
        <button class="btn dropdown-toggle d-flex align-items-center" type="button" id="countryDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #fff!important;">
            <img src="<?= $paisSelecionado['bandeira'] ?>" id="selectedFlag" class="me-2" width="20">
            <span id="selectedCountry"><?= $paisSelecionado['nome'] ?></span>
        </button>
        <ul class="dropdown-menu" aria-labelledby="countryDropdown">
            <?php foreach ($paises as $key => $pais): ?>
                <li>
                    <a class="dropdown-item d-flex align-items-center country-option" href="#" data-country="<?= $key ?>" data-flag="<?= $pais['bandeira'] ?>">
                        <img src="<?= $pais['bandeira'] ?>" class="me-2" width="20"> <?= $pais['nome'] ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <input type="hidden" id="country" name="country" value="<?= $_SESSION['user']['lang'] ?? 'brasil' ?>">
<?php
    return ob_get_clean();
}

function getUserCompanies($user_id)
{
    global $pdo;

    $query = $pdo->prepare("SELECT 
                            c.id, c.name 
                            FROM companies c 
                            JOIN company_has_user uc ON c.id = uc.company_id 
                            WHERE uc.user_id = :user_id");
    $query->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $query->execute();

    return $query->fetchAll(PDO::FETCH_ASSOC);
}


function formatName($nomeCompleto)
{
    $partes = explode(' ', trim($nomeCompleto));

    if (count($partes) > 1) {
        $primeiroNome = ucfirst(strtolower($partes[0]));
        $ultimoNome = ucfirst(strtolower(end($partes)));
        return $primeiroNome . ' ' . $ultimoNome;
    } else {
        return ucfirst(strtolower($nomeCompleto)); // Caso tenha apenas um nome
    }
}


function currencySelects($iso = null)
{
    try {
        $options = '';
        global $pdo;

        // Pega ISO da sessão como fallback
        $userIso = $_SESSION['user']['iso_code'] ?? '';

        $stmt = $pdo->query("SELECT iso_code, currency FROM currencies ORDER BY currency");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $selectedIso = $iso ?? $userIso;
            $selected = ($row['iso_code'] === $selectedIso) ? 'selected' : '';
            $options .= "<option value='{$row['iso_code']}' {$selected}>{$row['currency']} ({$row['iso_code']})</option>";
        }
    } catch (PDOException $e) {
        $options =  '<option disabled>' . t('Erro ao carregar moedas') . '</option>';
    }

    return $options;
}


function numberCopysSelect()
{
    return 
    '<select class="form-select" name="numberCopys" id="numberCopys">
        <option value="1">Original</option>
        <option value="2">em Duplicado</option>
        <option value="3">em Triplicado</option>
    </select>';
}

function due_dateSelect() {
    $currentPage = basename($_SERVER['PHP_SELF']);
     
    $extraOption = ($currentPage === 'create_invoices.php') 
        ? '<option value="other">Outro</option>' 
        : '';
    return '
        <select class="form-select" name="due_date" id="due_date" required onchange="handleOtherOption()">
            <option value="0">Pronto Pagamento</option>
            <option value="15" selected>15 Dias</option>
            <option value="30">30 Dias</option>
            <option value="45">45 Dias</option>
            <option value="60">60 Dias</option>
            <option value="90">90 Dias</option>
            ' . $extraOption . '
        </select>
        <div id="other_date_input" style="display: none;">
            <label for="custom_date">Selecione a data:</label>
            <input type="date" id="custom_date" onchange="addCustomDateOption()">
        </div>
    ';
}
function getPaymentMethods() {
    global $pdo;
    $query = $pdo->query("SELECT code, name FROM payment_methods_contacts ORDER BY name");
    $options = "";
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $options .= '<option value="'.$row['code'].'">'.t($row['name']).'</option>';
    }
    return $options;
}

