<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$excelFile =
    '/www/wwwroot/power.insightlatam.com/QuestionPro.xlsx';

$jsonDir =
    '/www/wwwroot/power.insightlatam.com/json/Encuestas';

$jsonFile =
    $jsonDir .
    '/MS_TV_Ventas_pilotoInsitu.json';

$surveyName =
    'MS_TV_Ventas_pilotoInsitu';

$surveyId = 13583195;

/*
|--------------------------------------------------------------------------
| MAPEO DE RESPUESTAS
|--------------------------------------------------------------------------
*/

$maps = [

    'C.  Por favor indique la tienda visitada. SELECCIONE UNA OPCIÓN RU' => [

        '22' => 'City Mall - SPS (Jetstereo)',
        '24' => 'Megamall',
        '17' => 'La Colonia',
        '10' => 'Walmart',
        '18' => 'Diunsa',
        '5'  => 'Metromall',
        '3'  => 'Uniplaza',
        '2'  => 'Mall Galerías'
    ]
];

/*
|--------------------------------------------------------------------------
| VALIDAR EXCEL
|--------------------------------------------------------------------------
*/

if (!file_exists($excelFile)) {
    die("Excel no encontrado\n");
}

/*
|--------------------------------------------------------------------------
| CREAR CARPETA
|--------------------------------------------------------------------------
*/

if (!is_dir($jsonDir)) {
    mkdir($jsonDir, 0777, true);
}

/*
|--------------------------------------------------------------------------
| LEER JSON EXISTENTE
|--------------------------------------------------------------------------
*/

$existingData = [];

$existingIds = [];

if (file_exists($jsonFile)) {

    $content = file_get_contents($jsonFile);

    if (!empty($content)) {

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {

            $existingData = $decoded;

            foreach ($existingData as $item) {

                if (isset($item['responseID'])) {

                    $existingIds[] =
                        $item['responseID'];
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| LEER EXCEL
|--------------------------------------------------------------------------
*/

$spreadsheet =
    IOFactory::load($excelFile);

$sheet =
    $spreadsheet->getSheetByName(
        'Datos sin procesar'
    );

if (!$sheet) {
    die("No existe la hoja 'Datos sin procesar'\n");
}

$rows = $sheet->toArray();

/*
|--------------------------------------------------------------------------
| CABECERAS
|--------------------------------------------------------------------------
*/

$headers = array_shift($rows);

$newCount = 0;

/*
|--------------------------------------------------------------------------
| RECORRER FILAS
|--------------------------------------------------------------------------
*/

foreach ($rows as $row) {

    $answers = [];

    foreach ($headers as $index => $header) {

        $answers[$header] =
            $row[$index] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE ID
    |--------------------------------------------------------------------------
    */

    $responseId =
        $answers['ID de respuesta']
        ?? $answers['Response ID']
        ?? md5(json_encode($answers));

    /*
    |--------------------------------------------------------------------------
    | EVITAR DUPLICADOS
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $responseId,
            $existingIds
        )
    ) {
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE SET
    |--------------------------------------------------------------------------
    */

    $responseSet = [];

    foreach ($answers as $question => $answer) {

        /*
        |--------------------------------------------------------------------------
        | REEMPLAZAR CODIGOS
        |--------------------------------------------------------------------------
        */

        if (
            isset($maps[$question]) &&
            isset($maps[$question][$answer])
        ) {

            $answer =
                $maps[$question][$answer];
        }

        /*
        |--------------------------------------------------------------------------
        | CREAR RESPUESTA
        |--------------------------------------------------------------------------
        */

        $responseSet[] = [

            'questionID' => 0,

            'questionDescription' => '',

            'questionCode' => '',

            'questionText' => $question,

            'questionType' => 'historical_import',

            'imageUrl' => null,

            'answerValues' => [
                [

                    'answerID' => 0,

                    'answerText' =>
                        (string)$answer,

                    'value' => [

                        'ord' => 0,

                        'scale' => '',

                        'other' => '',

                        'dynamicExplodeText' => '',

                        'text' =>
                            (string)$answer,

                        'result' => '',

                        'fileLink' => '',

                        'weight' => 0
                    ]
                ]
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FORMATO FINAL
    |--------------------------------------------------------------------------
    */

    $formattedData = [

        'responseID' => $responseId,

        'surveyID' => $surveyId,

        'surveyName' => $surveyName,

        'ipAddress' => '',

        'timestamp' =>
            date('d M, Y h:i:s A'),

        'dataQuality' =>
            'historical_import',

        'dataQualityScore' => 0,

        'location' => [

            'country' => '',

            'region' => '',

            'latitude' => '',

            'longitude' => '',

            'radius' => 0,

            'countryCode' => ''
        ],

        'duplicate' => false,

        'timeTaken' => 0,

        'responseStatus' => 'Completed',

        'externalReference' => '',

        'customVariables' => [

            'custom1' => null,
            'custom2' => null,
            'custom3' => null,
            'custom4' => null,
            'custom5' => null
        ],

        'language' =>
            'Spanish (Latin America)',

        'currentInset' => '',

        'operatingSystem' => '',

        'osDeviceType' => '',

        'browser' => '',

        'responseSet' => $responseSet,

        'utctimestamp' => time()
    ];

    /*
    |--------------------------------------------------------------------------
    | AGREGAR
    |--------------------------------------------------------------------------
    */

    $existingData[] =
        $formattedData;

    $existingIds[] =
        $responseId;

    $newCount++;
}

/*
|--------------------------------------------------------------------------
| GUARDAR JSON
|--------------------------------------------------------------------------
*/

file_put_contents(
    $jsonFile,
    json_encode(
        $existingData,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ),
    LOCK_EX
);

/*
|--------------------------------------------------------------------------
| SUBIR A ONEDRIVE
|--------------------------------------------------------------------------
*/

$rcloneBinary =
    '/usr/bin/rclone';

$rcloneConfig =
    '/www/server/rclone/rclone.conf';

$rcloneCommand =
    $rcloneBinary .
    ' --config ' .
    escapeshellarg($rcloneConfig) .
    ' copy ' .
    escapeshellarg($jsonFile) .
    ' onedrive:Encuestas 2>&1';

$rcloneOutput =
    shell_exec($rcloneCommand);

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
| RESULTADO
|--------------------------------------------------------------------------
*/

echo "Importación completada\n";

echo "Nuevas respuestas agregadas: {$newCount}\n\n";

echo "Resultado rclone:\n";

echo $rcloneOutput;