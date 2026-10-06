<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$wsdl = 'https://sifphml.minfin.gov.ao/sigt/contribuinte/consultarNIF/ws/v5?WSDL';

$nif = '5002455595';

try {

    $client = new SoapClient($wsdl, [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE
    ]);

    $request = [
        'tipoDocumento' => 'NIF',
        'numeroDocumento' => $nif
    ];

    $response = $client->obter($request);

    echo '<h3>Resposta PHP</h3>';
    echo '<pre>';
    print_r($response);
    echo '</pre>';

    echo '<h3>XML enviado</h3>';
    echo '<pre>';
    echo htmlspecialchars($client->__getLastRequest());
    echo '</pre>';

    echo '<h3>XML recebido</h3>';
    echo '<pre>';
    echo htmlspecialchars($client->__getLastResponse());
    echo '</pre>';
} catch (SoapFault $e) {

    echo '<pre>';
    echo $e->getMessage();
    echo '</pre>';

    if (isset($client)) {
        echo '<h3>XML enviado</h3>';
        echo '<pre>';
        echo htmlspecialchars($client->__getLastRequest());
        echo '</pre>';

        echo '<h3>XML recebido</h3>';
        echo '<pre>';
        echo htmlspecialchars($client->__getLastResponse());
        echo '</pre>';
    }
}
