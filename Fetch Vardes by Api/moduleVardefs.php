<?php

// Reusable function for cURL requests
function sendCurlRequest($url, $headers = [], $data = null, $isPost = false)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($isPost) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response' => $response,
    ];
}

// Fetch Access Token
function getAccessToken($url, $client_id, $client_secret, $username, $password)
{
    $data = json_encode([
        "grant_type" => "password",
        "client_id" => $client_id,
        "client_secret" => $client_secret,
        "username" => $username,
        "password" => $password,
    ]);

    $response = sendCurlRequest($url, ["Content-Type: application/json"], $data, true);

    if ($response && $response['http_code'] === 200) {
        $responseData = json_decode($response['response'], true);
        return $responseData['access_token'] ?? null;
    }

    error_log("Failed to fetch access token. HTTP Code: " . $response['http_code']);
    return null;
}

// Fetch Module Names
function fetchModuleNames($url, $bearerToken)
{
    $response = sendCurlRequest($url, [
        "Authorization: Bearer $bearerToken",
        "Content-Type: application/json",
    ]);

    if ($response && $response['http_code'] === 200) {
        $decodedResponse = json_decode($response['response'], true);
        return $decodedResponse['data']['attributes'] ? array_keys($decodedResponse['data']['attributes']) : [];
    }

    error_log("Failed to fetch module names. HTTP Code: " . $response['http_code']);
    return [];
}

// Fetch Vardefs for a Module
function fetchVardefsByModule($baseUrl, $bearerToken, $moduleName)
{
    $url = "{$baseUrl}/{$moduleName}/110";

    $response = sendCurlRequest($url, [
        "Authorization: Bearer $bearerToken",
        "Content-Type: application/json",
    ]);

    if ($response && $response['http_code'] === 200) {
        return json_decode($response['response'], true);
    }

    error_log("Failed to fetch vardefs for module $moduleName. HTTP Code: " . $response['http_code']);
    return [
        'error' => true,
        'message' => "HTTP Code: " . $response['http_code'],
    ];
}

// Fetch Vardefs for All Modules
function fetchVardefs($baseUrl, $bearerToken, $moduleNames)
{
    $results = [];

    foreach ($moduleNames as $moduleName) {
        $vardefs = fetchVardefsByModule($baseUrl, $bearerToken, $moduleName);

        if (isset($vardefs['error'])) {
            $results[] = [
                'module_name' => $moduleName,
                'error' => $vardefs['message'],
            ];
        } else {
            $results[] = [
                'module_name' => $moduleName,
                'vardefs' => $vardefs['data'] ?? null,
            ];
        }
    }

    return json_encode($results, JSON_PRETTY_PRINT);
}

// Configuration
$client_id = "";
$client_secret = "";
$username = "";
$password = "";
$baseUrl = "http://localhost/imb-suitecrm/SuiteCRM/public/Api/";

$accessTokenEndpoint = "{$baseUrl}access_token";
$moduleNameEndpoint = "{$baseUrl}V8/meta/modules";
$vardefEndpoint = "{$baseUrl}V8/module/vardefs";

// Fetch Access Token
$bearerToken = getAccessToken($accessTokenEndpoint, $client_id, $client_secret, $username, $password);

if ($bearerToken) {
    // Fetch Modules and Vardefs
    $moduleNames = fetchModuleNames($moduleNameEndpoint, $bearerToken);
    $allVardefs = fetchVardefs($vardefEndpoint, $bearerToken, $moduleNames);

    echo "<pre>";
    print_r($allVardefs);
    echo "</pre>";
} else {
    echo "Failed to fetch access token.";
}
