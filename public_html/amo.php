<?php
require __DIR__ . '/../vendor/autoload.php';
require_once 'google_functions.php';
require_once 'calendar_functions.php';
require_once 'amo_functions.php';

$requestType = $_GET['type'];

switch ($requestType) {
    case 'instructors':
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);

        $instructors = loadInstructorIds($cookieFileName);

        header("Content-type: application/json; charset=utf-8");
        echo json_encode($instructors);
    break;
    case 'getAllInstructorsData':
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);

        $instructors = loadInstructorIds($cookieFileName);
        $instructorsData = [];
        foreach ($instructors as $id => $name) {
            $leadsResponse = loadInstructorLeadsWithExtraData($cookieFileName, $id);
            $leads = $leadsResponse['response']['items'];
            $instructorsData[] = [
                'id' => $id,
                'name' => $name,
                'groups' => getGroupsInfo($leads),
                'students' => getStudents($leads),
            ];
        }

        header("Content-type: application/json; charset=utf-8");
        echo json_encode([
            "instructors" => $instructorsData,
        ]);
    break;
    case 'getHours':
        $instructorId = $_GET['instructorId'];

        if ($instructorId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoInterface($cookieFileName);

            $instructors = loadInstructorIds($cookieFileName);
            $leadsResponse = loadInstructorLeadsWithExtraData($cookieFileName, $instructorId);
            $leads = $leadsResponse['response']['items'];

            $client = getClient('../token.json');
            $service = new Google_Service_Calendar($client);
            $calendarId = getInstructorCalendarId($instructorId);
            $timestamp = (new DateTime())->getTimestamp();
            $events = getAllEvents($service, $calendarId, $timestamp);

            $contactsAndHours = getContactsDataScheduleFromLeadsAndEvents($leads, $events);
            $groups = getGroupsInfo($leads);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode([
                "instructor" => $instructors[$instructorId],
                "leads"      => $contactsAndHours,
                "groups"     => $groups,
            ]);
        }
    break;
    case 'updateHours':
        $leadId = $_GET['leadId'];
        $hours = $_GET['hours'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            setLeadHours($leadId, $hours, $cookieFileName);
        }
    break;
    case 'addNote':
        $leadId = $_GET['leadId'];
        $text = $_GET['text'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            addNoteToLead($leadId, $text, $cookieFileName);
        }
    break;
    case 'getLead':
        $leadId = $_GET['leadId'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            $leadData = loadLeadWithExtraDataAndFilterFields($cookieFileName, $leadId);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode($leadData);
        }
    break;
    case 'getVideo':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(getVideoLinks());
    break;
    case 'getTickets':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(getGibddTickets());
    break;
}