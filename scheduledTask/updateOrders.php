 
<?php

function request($url, $logName) {
    $user = 'eep_client_user';
    $password = '19AS923!!"39A&$!="1VQlk2!D';
    $data['user'] = $user;
    $data['password'] = $password;
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = json_decode(curl_exec($ch), true);
        if (isset($response['status']) && $response['status'] == true) {
            echo date("d/m/Y H:i:s") . " - $logName: EXITOSO - " . ($response['description'] ?? (var_export($response, true))) . "\n";
        } else {
            echo date("d/m/Y H:i:s") . " - $logName: FALLIDO - " . ($response['description'] ?? (var_export($response, true))) . "\n";
        }
    } catch (Exception $exc) {
        echo date("d/m/Y H:i:s") . ': ERROR FATAL: ' . $exc->getTraceAsString();
    }
}

//UPDATE ORDER STATUS REQUEST
$host = "https://postgrados.farusac.edu.gt";
//CHANGE TO HTTPS
$url = "$host/treasury/updateOrdersStatus";
request($url, 'Revisión de vencimiento de órdenes');
//SINC SEEP USERS DATA TO SATU
$url = "$host/etl/updateUsers";
request($url, 'Sincronización de datos con SATU');
