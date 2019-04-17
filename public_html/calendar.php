<?php
require __DIR__ . '/../vendor/autoload.php';
require_once  'calendar_functions.php';

const EVENT_DESCRIPTION = 'Занятие с учеником в автошколе';

$client = getClient();
$service = new Google_Service_Calendar($client);

$response = [];
$calendarId = getInstructorCalendarId($_REQUEST['instructorId']);

switch ($_REQUEST['action']) {
    case 'list':
        $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $_REQUEST['date'].' 00:00:00')->getTimestamp();
        $response = getTimeframes($service, $calendarId, $timestamp);
    break;
    case 'add':
        $studentName = $_REQUEST['studentName'];
        $date = $_REQUEST['date'];
        $startTime = $_REQUEST['time'];

        $eventLink = addEvent($service, $calendarId, $studentName, $date, $startTime);
        $response = ["success" => boolval($eventLink), "link" => $eventLink];
    break;
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($response);