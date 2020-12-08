<?php

use AMO\AmoApi;
use AMO\Database;

require __DIR__ . '/../vendor/autoload.php';

function getLeadId() {
    $leadEvents = @$_REQUEST['leads'];
    $eventType = @current( array_keys($leadEvents) );

    $leadId = @$_REQUEST['leads'][$eventType][0]['id'];

    return $leadId;
}
function getContactId() {
    $contactEvents = @$_REQUEST['contacts'];
    $eventType = @current( array_keys($contactEvents) );

    $contactId = @$_REQUEST['contacts'][$eventType][0]['id'];

    return $contactId;
}

$leadId = getLeadId();
if (!$leadId) {
    $contactId = getContactId();
    if ($contactId) {
        $lead = Database::getInstance()->loadLeadByContactId($contactId);
        if ($lead) {
            $leadId = $lead->id();
        }
    }
}

if ($leadId) {
    $lead = AmoApi::getInstance()->getLeadById($leadId);
    Database::getInstance()->updateLead($lead);
    echo "Обновлена сделка $leadId\n";
}