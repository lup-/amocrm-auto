<?php

use AMO\AmoApi;
use AMO\Database;

require __DIR__ . '/../vendor/autoload.php';

function getLeadId() {
    $leadEvents = @$_REQUEST['leads'];
    $eventType = @current( array_keys($leadEvents) );

    $leadId = @$_REQUEST['leads'][$eventType]['id']
        ? $_REQUEST['leads'][$eventType]['id']
        : $_REQUEST['leads'][$eventType][0]['id'];

    return [$leadId, $eventType];
}
function getContactId() {
    $contactEvents = @$_REQUEST['contacts'];
    $eventType = @current( array_keys($contactEvents) );

    $contactId = @$_REQUEST['contacts'][$eventType]['id']
        ? $_REQUEST['contacts'][$eventType]['id']
        : $_REQUEST['contacts'][$eventType][0]['id'];

    return [$contactId, $eventType];
}

[$leadId, $eventType] = getLeadId();
if (!$leadId) {
    [$contactId, $eventType] = getContactId();
    if ($contactId) {
        $lead = Database::getInstance()->loadLeadByContactId($contactId);
        if ($lead) {
            $leadId = $lead->id();
        }
    }
}

if ($leadId) {
    $lead = AmoApi::getInstance()->getLeadById($leadId);
    if ($lead) {
        Database::getInstance()->updateLead($lead);
        $message = "$eventType: Обновлена сделка $leadId\n";
        echo $message;
        error_log($message, 3, "lead_updates.log");
    }
    else {
        $message = "$eventType: Загрузка данных по сделке $leadId не прошла\n";
        echo $message;
        error_log($message, 3, "lead_updates.log");
    }
}