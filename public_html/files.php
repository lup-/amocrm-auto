<?php

use AMO\AmoApi;
use AMO\Database;
use AMO\Document;

require __DIR__ . '/../vendor/autoload.php';
require_once 'google_functions.php';
require_once 'drive_functions.php';
require_once 'amo_functions.php';
$settings = require('settings.php');

$client = getClient('../token.json');
$service = new Google_Service_Drive($client);

$response = [];

switch ($_REQUEST['action']) {
    case 'list':
        header("Content-type: application/json; charset=utf-8");

        $response = [];
        foreach ($settings['docs'] as $location => $templates) {
            foreach ($templates as $type => $folderId) {
                $response[$location][$type] = listFolderFiles($service, $folderId);
            }
        }

        echo json_encode($response);
    break;
    case 'makedoc':
    case 'makedocajax':
        $googleTemplateId = $_REQUEST['templateId'];
        $leadId = $_REQUEST['leadId'];

        $cookieFileName = initAmoApi();
        authAmoInterface($cookieFileName);

        $leadPairs = loadLeadReplacementPairs($cookieFileName, $leadId);

        $doc = Document::makeFromTemplate($service, $googleTemplateId, $leadId)
                ->prepareTemplate()
                ->fillTemplate($leadPairs)
                ->generateFileName()
                ->uploadToGoogleDrive();

        $doc = Database::getInstance()->saveDocument($doc);
        AmoApi::getInstance()->sendFileToLead($doc);

        if ($_REQUEST['action'] === 'makedoc') {
            $doc->sendDownload();
        }
        else {
            header("Content-type: application/json; charset=utf-8");
            echo json_encode([
                "doc" => $doc->asArray()
            ]);
        }
    break;
    case 'makegroupdoc':
        $googleTemplateId = $_REQUEST['templateId'];
        $groupName = $_REQUEST['group'];
        $selectedLeads = !empty($_REQUEST['selected']) ? $_REQUEST['selected'] : false;

        $date = (new DateTime())->format('d.m.Y');

        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);
        authAmoApi($cookieFileName);
        $groups = loadGroups($cookieFileName);
        $group = $groups[$groupName];

        if ($selectedLeads) {
            $group['leads'] = array_values( array_filter($group['leads'], function ($lead) use ($selectedLeads) {
                return array_search($lead['id'], $selectedLeads) !== false;
            }) );
        }

        $templateFile = downloadTemplate($googleTemplateId, $service);
        $replacedFile = groupReplaceInDocxTemplate($templateFile, $group, $date, $cookieFileName);
        $downloadFileName = getFilename($googleTemplateId, $service);
        $fileNameSuffix = $groupName;
        $downloadFileName = str_replace('.', '_'.$fileNameSuffix.'.', $downloadFileName);

        header("Content-disposition: attachment; filename=" . $downloadFileName);
        header("Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        readfile($replacedFile);
    break;
}