<?php
require __DIR__ . '/../vendor/autoload.php';
require_once 'google_functions.php';
require_once 'drive_functions.php';
require_once 'amo_functions.php';

$client = getClient('../token.json');
$service = new Google_Service_Drive($client);

$response = [];

switch ($_REQUEST['action']) {
    case 'list':
        header("Content-type: application/json; charset=utf-8");
        $response = [
            'Железнодорожный' => [
                ['id' => '1LMmz7ujo5oWrSOGV9ft205tDYZ-YNKGW', 'name' => 'Договор']
            ]
        ];
        echo json_encode($response);
    break;
    case 'makedoc':
        //$templateId = '1uDmyRhOUtjvl9194cI6antqCAE5AcpqA';
        $templateId = $_REQUEST['templateId'];
        $leadId = $_REQUEST['leadId'];
        $cookieFileName = initAmoApi();
        authAmoInterface($cookieFileName);
        $leadPairs = loadLeadReplacementPairs($cookieFileName, $leadId);

        $templateFile = downloadTemplate($templateId, $service);
        $replacedFile = replaceInDocxTemplate($templateFile, $leadPairs);
        $downloadFileName = getFilename($templateId, $service);
        $fileNameSuffix = $leadPairs['Контакт.Имя'].'_'.$leadPairs['Группа'];
        $downloadFileName = str_replace('.', '_'.$fileNameSuffix.'.', $downloadFileName);

        header("Content-disposition: attachment; filename=" . $downloadFileName);
        header("Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        readfile($replacedFile);
    break;
}