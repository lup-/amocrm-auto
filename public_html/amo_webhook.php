<?php
use AMO\AmoApi;
require __DIR__ . '/../vendor/autoload.php';

$leadId = $_REQUEST['leads']['status'][0]['id'];

if ($leadId) {
    $lead = AmoApi::getInstance()->setLeadLink($leadId);
    header("Content-type: application/json; charset=utf-8");
    echo json_encode(["success" => true, "lead" => $lead->asStudentArray()]);
}