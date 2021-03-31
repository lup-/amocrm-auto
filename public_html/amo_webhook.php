<?php

use AMO\AmoApi;
use AMO\Database;

require __DIR__ . '/../vendor/autoload.php';
//ini_set('display_errors', 1);

function randomPassword($charsLen = 3, $numLen = 5) {
    $alphaChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $numChars = "0123456789";

    $chars = substr(str_shuffle($alphaChars),0, $charsLen);
    $nums = substr(str_shuffle($numChars),0, $numLen);
    return $chars.$nums;
}

$skipLeadUpdate = $_REQUEST['skipLeadUpdate'] === '1';
$newPassword = $_REQUEST['password'] ? $_REQUEST['password'] : randomPassword();
$forceUpdate = boolval($_REQUEST['password']);

if (isset($_REQUEST['leads']['status'])) {
    $leadId = $_REQUEST['leads']['status'][0]['id'];
}
else if (isset($_REQUEST['leads']['add'])) {
    $leadId = $_REQUEST['leads']['add'][0]['id'];
}

header("Content-type: application/json; charset=utf-8");

if ($leadId) {
    try {
        $lead = AmoApi::getInstance()->getSingleLead($leadId, true);
        $noMetadata = Database::getInstance()->loadMetadata($leadId) === false;
        if ($noMetadata || $forceUpdate) {
            Database::getInstance()->updatePassword($lead, $newPassword);
            Database::getInstance()->updateRole($lead, 'amoUser');
        }

        if ($skipLeadUpdate) {
            echo json_encode(["success" => true, "lead" => null]);
        }
        else {
            $lead = AmoApi::getInstance()->setLeadLink($leadId);
            echo json_encode(["success" => true, "lead" => $lead->asStudentArray()]);
        }
    }
    catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e]);
    }
}
else {
    echo json_encode(["success" => false]);
}