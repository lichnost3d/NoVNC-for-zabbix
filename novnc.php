<?php
// zabbix_novnc.php - упрощенная версия для интеграции с Zabbix
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

//-----------------PARAMS-----------
$passVNCDefault = '123443';
$pathToZabbixApi = 'http://localhost/zabbix/api_jsonrpc.php';
$loginZabbixApi = 'Admin';
$passZabbixApi = '123456678';
//=================================


header('Content-Type: application/json');

$target_ip = $_GET['ip'] ?? $_POST['ip'] ?? '';
$hostId = "";

if (empty($target_ip)) {
    $hostId = $_GET['hostId'];
    if (empty($hostId))
    {
    http_response_code(400);
    echo json_encode(['error' => 'IP address is required']);
    exit;
 }
 else
 {
 $ip = getZabbixHostIP($hostId, $pathToZabbixApi, $loginZabbixApi, $passZabbixApi);
 $target_ip = $ip;
 }
}

// Проверка IP (упрощенная)
if (!filter_var($target_ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid IP address']);
    exit;
}

// Поиск свободного порта
$port = 6080;
while ($port <= 6180) {
    $check = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if (!$check) {
        break;
    }
    fclose($check);
    $port++;
}

// Запуск websockify
$command = sprintf(
    '/usr/bin/websockify -D --web=/usr/share/novnc/ --cert=/home/debian/novnc.pem %d %s:5900',
    $port,
    escapeshellarg($target_ip)
);

exec($command . ' 2>&1', $output, $return_var);


if ($return_var === 0) {

/*    $novnc_url = sprintf(
        "http://%s/novnc/vnc.html?host=%s&port=%d&path=websockify",
        $_SERVER['HTTP_HOST'],
        $_SERVER['HTTP_HOST'],
        $port
    );
*/
    
    header('Location: http://'.$_SERVER['HTTP_HOST'].':'.$port.'/vnc.html?autoconnect=true&ip='.$target_ip.'&password='.$passVNCDefault);
    exit();
    
/*    echo json_encode([
        'success' => true,
        'url' => $novnc_url,
        'port' => $port,
        'ip' => $target_ip
    ]);
*/

} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to start noVNC',
        'details' => $output
    ]);
}

function getZabbixHostIP($hostId, $apiUrl, $username, $password) {
    // 1. Получаем auth token
    $loginData = [
        'jsonrpc' => '2.0',
        'method' => 'user.login',
        'params' => [
            'username' => $username,
            'password' => $password
        ],
        'id' => 1
    ];
    
    $authResponse = callZabbixAPI($apiUrl, $loginData);
    
    if (!isset($authResponse['result'])) {
        throw new Exception("Authentication failed: " . ($authResponse['error']['data'] ?? 'Unknown error'));
    }
    
    $authToken = $authResponse['result'];
    
    // 2. Получаем информацию о хосте
/*
    $hostData = [
        'jsonrpc' => '2.0',
        'method' => 'host.get',
        'params' => [
            'output' => ['hostid'],
            'selectInterfaces' => ['ip', 'dns', 'useip', 'type', 'main'],
            'hostids' => [$hostId]
        ],
        'id' => 2,
        'auth' => $authToken
    ];
*/

    $hostData = [
        'jsonrpc' => '2.0',
        'method' => 'host.get',
        'params' => [
            'output' => ['hostid'],
            'selectInterfaces' => ['ip', 'dns', 'useip', 'type', 'main'],
            'hostids' => [$hostId]
        ],
        'id' => 2
    ];
    
    $hostResponse = callZabbixAPI($apiUrl, $hostData,$authToken);
    var_dump($hostResponse);
    
    if (!isset($hostResponse['result'][0]['interfaces'])) {
        throw new Exception("Host not found or no interfaces");
    }
    
    $interfaces = $hostResponse['result'][0]['interfaces'];
    
    // Ищем подходящий интерфейс
    foreach ($interfaces as $interface) {
        if ($interface['useip'] == '1' && !empty($interface['ip'])) {
            return $interface['ip'];
        }
    }
    
    throw new Exception("No IP address found for host");
}

function callZabbixAPI($apiUrl, $requestData,$auth = "") {
    //echo "Request data";
    //var_dump($requestData);
    $jsonData = json_encode($requestData);
    var_dump($jsonData);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData).'',
	    'Authorization: Bearer '.$auth
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    var_dump($response);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP error: " . $httpCode);
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error");
    }
    
    return $data;
}

?>