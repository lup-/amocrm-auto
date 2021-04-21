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

        if (is_array($leadId)) {
            $leadsToMerge = [];
            foreach ($leadId as $singleId) {
                $leadsToMerge[] = AmoApi::getInstance()->getSingleLead($singleId);
            }

            $collection = new LeadsCollection([], [], []);
            $mergedLeads = $collection->joinDuplicateLeads($leadsToMerge);
            $lead = $mergedLeads[0];
            if ($_REQUEST['baseId']) {
                $leadId = $_REQUEST['baseId'];
            }
        }
        else {
            $lead = AmoApi::getInstance()->getSingleLead($leadId);
        }

        $leadPairs = $lead->asReplacementPairs();
        $leadId = intval($leadId);

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

        $groupLeads = Database::getInstance()->loadGroupLeads($groupName);
        $groups = $groupLeads->getGroups(true);
        $group = $groups[$groupName];

        if ($selectedLeads) {
            $group['leads'] = array_values( array_filter($group['leads'], function ($lead) use ($selectedLeads) {
                return array_search($lead->id(), $selectedLeads) !== false;
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
    case 'delete':
        $googleId = $_REQUEST['googleId'];
        $doc = Document::makeFromGoogleId($service, $googleId);
        $deleted = false;
        if ($doc) {
            $doc->delete();
            $deleted = true;
        }

        header("Content-type: application/json; charset=utf-8");
        echo json_encode([
            "deleted" => $deleted,
        ]);
    break;
}