<?php
use AMO\AmoApi;
use AMO\Database;

require __DIR__ . '/vendor/autoload.php';

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

echo "Загрузка сделок...\n";
$leads = AmoApi::getInstance()->getAllLeads();

echo "Загрузка контактов...\n";
$contactsHash = AmoApi::getInstance()->getLeadContactsHash($leads);

echo "Загрузка инструкторов...\n";
$instructors = AmoApi::getInstance()->getInstructorIds();

$leads->setContactsHash($contactsHash);
$leads->setInstructors($instructors);

echo "Сохранение сделок...\n";
Database::getInstance()->updateLeads( $leads );
