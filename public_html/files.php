<?php

use AMO\AmoApi;
use AMO\Database;
use AMO\Document;
use AMO\LeadsCollection;

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
    case 'makegroupdocajax':
        $googleTemplateId = $_REQUEST['templateId'];
        $groupName = $_REQUEST['group'];
        $selectedLeads = !empty($_REQUEST['selected']) ? $_REQUEST['selected'] : false;

        $date = (new DateTime())->format('d.m.Y');

        $activeLeads = Database::getInstance()->loadActiveLeads();

//        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
//        authAmoInterface($cookieFileName);
//        authAmoApi($cookieFileName);
//        $groups = loadGroups($cookieFileName);
        $groups = $activeLeads->getGroups(true);
        $group = $groups[$groupName];

        if ($selectedLeads) {
            $group['leads'] = array_values( array_filter($group['leads'], function ($lead) use ($selectedLeads) {
                return array_search($lead['id'], $selectedLeads) !== false;
            }) );
        }

        $doc = Document::makeFromTemplate($service, $googleTemplateId)
                   ->prepareTemplate()
                   ->fillGroupTemplate($group, $date)
                   ->generateFileName()
                   ->uploadToGoogleDrive();

        $doc = Database::getInstance()->saveDocument($doc);

        if ($_REQUEST['action'] === 'makegroupdoc') {
            $doc->sendDownload();
        }
        else {
            header("Content-type: application/json; charset=utf-8");
            echo json_encode([
                "doc" => $doc->asArray()
            ]);
        }
    break;
}