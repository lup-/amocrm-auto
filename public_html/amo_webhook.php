<?php
use AMO\AmoApi;
require __DIR__ . '/../vendor/autoload.php';
//ini_set('display_errors', 1);

if (isset($_REQUEST['leads']['status'])) {
    $leadId = $_REQUEST['leads']['status'][0]['id'];
}
else if (isset($_REQUEST['leads']['add'])) {
    $leadId = $_REQUEST['leads']['add'][0]['id'];
}

if ($leadId) {
    try {
        $lead = AmoApi::getInstance()->setLeadLink($leadId);
	header("Content-type: application/json; charset=utf-8");
	echo json_encode(["success" => true, "lead" => $lead->asStudentArray()]);
    }
    catch (Exception $e) {
	header("Content-type: application/json; charset=utf-8");
	echo json_encode(["success" => false, "error" => $e]);
    }
}