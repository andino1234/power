<?php

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| MYSQL CONFIG
|--------------------------------------------------------------------------
*/

// Credenciales centralizadas en config.php (no versionado; ver config.example.php)
require_once __DIR__ . '/config.php';

$host = DB_HOST;
$db = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = DB_CHARSET;

$dsn = "mysql:host=$host;port=" . DB_PORT . ";dbname=$db;charset=$charset";

/*
|--------------------------------------------------------------------------
| CONEXION MYSQL
|--------------------------------------------------------------------------
*/

try {

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'DB connection failed',
        'detail' => $e->getMessage()
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| RECIBIR WEBHOOK
|--------------------------------------------------------------------------
*/

$raw = file_get_contents('php://input');

if (!$raw) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => 'Empty body'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DECODIFICAR JSON
|--------------------------------------------------------------------------
*/

$data = json_decode($raw, true);

/*
|--------------------------------------------------------------------------
| DATOS PRINCIPALES
|--------------------------------------------------------------------------
*/

$eventType = $data['event_type']
    ?? $data['eventType']
    ?? null;

$surveyName = $data['survey_name']
    ?? $data['surveyName']
    ?? 'survey';

$surveyId = $data['survey_id']
    ?? $data['surveyId']
    ?? null;

$responseId = $data['response_id']
    ?? $data['responseId']
    ?? null;

$respondentEmail = $data['respondent_email']
    ?? $data['email']
    ?? null;

/*
|--------------------------------------------------------------------------
| LIMPIAR NOMBRE ARCHIVO
|--------------------------------------------------------------------------
*/

$cleanSurveyName = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $surveyName
);

/*
|--------------------------------------------------------------------------
| CARPETA JSON
|--------------------------------------------------------------------------
*/

$jsonDir = '/www/wwwroot/power.insightlatam.com/json/Encuestas';

if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0777, true);
}

/*
|--------------------------------------------------------------------------
| ARCHIVO JSON UNICO POR ENCUESTA
|--------------------------------------------------------------------------
*/

$jsonFile = $jsonDir . '/' . $cleanSurveyName . '.json';

/*
|--------------------------------------------------------------------------
| LEER JSON EXISTENTE
|--------------------------------------------------------------------------
*/

$existingData = [];

if (file_exists($jsonFile)) {

    $content = file_get_contents($jsonFile);

    $decoded = json_decode($content, true);

    if (is_array($decoded)) {
        $existingData = $decoded;
    }
}

/*
|--------------------------------------------------------------------------
| AGREGAR NUEVA RESPUESTA
|--------------------------------------------------------------------------
*/

$existingData[] = $data;

/*
|--------------------------------------------------------------------------
| GUARDAR JSON ACTUALIZADO
|--------------------------------------------------------------------------
*/

file_put_contents(
    $jsonFile,
    json_encode(
        $existingData,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);

/*
|--------------------------------------------------------------------------
| GUARDAR EN MYSQL
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO questionpro_webhook_demo
        (
            event_type,
            survey_name,
            survey_id,
            response_id,
            respondent_email,
            payload_json
        )
        VALUES
        (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $eventType,
        $surveyName,
        $surveyId,
        $responseId,
        $respondentEmail,
        $raw
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Insert failed',
        'detail' => $e->getMessage()
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| SUBIR A ONEDRIVE
|--------------------------------------------------------------------------
*/

$rcloneBinary = '/usr/bin/rclone';

$rcloneConfig = '/www/server/rclone/rclone.conf';

$rcloneCommand =
    $rcloneBinary .
    ' --config ' .
    escapeshellarg($rcloneConfig) .
    ' copy ' .
    escapeshellarg($jsonFile) .
    ' onedrive:Encuestas 2>&1';

$rcloneOutput = shell_exec($rcloneCommand);

/*
|--------------------------------------------------------------------------
| DEBUG
|--------------------------------------------------------------------------
*/

file_put_contents(
    '/www/wwwroot/power.insightlatam.com/rclone_debug.txt',
    $rcloneOutput
);

/*
|--------------------------------------------------------------------------
| RESPUESTA FINAL
|--------------------------------------------------------------------------
*/

echo json_encode([
    'ok' => true,
    'message' => 'Webhook received successfully',
    'survey_name' => $surveyName,
    'response_id' => $responseId
]);