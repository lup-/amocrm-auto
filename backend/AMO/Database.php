<?php

namespace AMO;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

function getEnvVar($varName, $default = false) {
    if (isset($_SERVER[$varName])) {
        return $_SERVER[$varName];
    }

    if (isset($_ENV[$varName])) {
        return $_ENV[$varName];
    }

    return $default;
}


class Database
{
    /**
     * @var Database
     */
    private static $instance;

    private $mongo;
    private $dbName;

    /**
     * @return Database
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function __construct() {
        $mongoHost = getEnvVar('MONGO_HOST');
        $mongoPort = getEnvVar('MONGO_PORT', 27017);
        $mongoDSN = "mongodb://${mongoHost}:${mongoPort}/";

        $this->mongo = new Manager($mongoDSN);
        $this->dbName = getEnvVar('MONGO_DB');
    }

    private function getFullCollectionName($shortName) {
        return "{$this->dbName}.{$shortName}";
    }

    /**
     * @param $doc Document
     * @return Document
     */
    public function saveDocument(Document $doc) {
        $bulk = new BulkWrite;
        $collectionName = $this->getFullCollectionName('documents');

        $newId = new ObjectID;
        $docRecord = $doc->asArray();
        $docRecord['_id'] = $newId;

        $bulk->insert($docRecord);
        $insertResult = $this->mongo->executeBulkWrite($collectionName, $bulk);

        if ($insertResult) {
            $doc->setDbId((string) $newId);
        }

        return $doc;
    }

    public function updateLeads(LeadsCollection $leads) {
        $leadsBulk = new BulkWrite;
        $fieldsBulk = new BulkWrite;
        $instructorsBulk = new BulkWrite;
        $groupsBulk = new BulkWrite;

        $leadsCollection = $this->getFullCollectionName('amo_leads');

        $leadsBulk->delete([]);
        foreach ($leads->getLeads() as $lead) {
            $leadData = $lead->asDatabaseArray();
            $newId = new ObjectID;
            $leadData['_id'] = $newId;
            $leadsBulk->insert($leadData);
        }

        $this->mongo->executeBulkWrite($leadsCollection, $leadsBulk);

        $fieldsCollection = $this->getFullCollectionName('amo_fields');
        $instructorsCollection = $this->getFullCollectionName('amo_instructors');
        $groupsCollection = $this->getFullCollectionName('amo_groups');

        $fieldsBulk->delete([]);
        foreach ($leads->getRawFields() as $field) {
            $newId = new ObjectID;
            $field['_id'] = $newId;
            $fieldsBulk->insert($field);
        }

        $instructorsBulk->delete([]);
        foreach ($leads->getRawInstructors() as $instructorId => $instructorName) {
            $newId = new ObjectID;
            $instructor['_id'] = $newId;
            $instructorsBulk->insert([
                "id"   => $instructorId,
                "name" => $instructorName
            ]);
        }

        $groupsBulk->delete([]);
        foreach ($leads->getGroups() as $group) {
            $newId = new ObjectID;
            $instructor['_id'] = $newId;
            $groupsBulk->insert($group);
        }

        $this->mongo->executeBulkWrite($fieldsCollection, $fieldsBulk);
        $this->mongo->executeBulkWrite($instructorsCollection, $instructorsBulk);
        $this->mongo->executeBulkWrite($groupsCollection, $groupsBulk);

        return $this;
    }

    public function updateLead(AutoSchoolLead $lead) {
        $operations = new BulkWrite;
        $leads = $this->getFullCollectionName('amo_leads');

        $filter = ["id" => $lead->id()];
        $operations->update($filter, $lead->asDatabaseArray(), ["upsert" => true]);

        $this->mongo->executeBulkWrite($leads, $operations);
    }

    private function mongoToArray($cursor) {
        return array_map(function ($mongoDoc) {
            $arrayResult = json_decode(json_encode( $mongoDoc ), true);
            if (isset($arrayResult['_id'])) {
                $arrayResult['_id'] = (string) $arrayResult['_id'];
            }
            return $arrayResult;
        }, $cursor->toArray());
    }

    public function loadDocs($filter) {
        $collectionName = $this->getFullCollectionName('documents');

        $query = new Query($filter);
        $cursor = $this->mongo->executeQuery($collectionName, $query);
        $docs = $this->mongoToArray($cursor);

        return DocsCollection::from($docs);
    }

    public function loadAllDocs() {
        return $this->loadDocs([]);
    }

    public function loadDocByGoogleId($googleId) {
        $docs = $this->loadDocs(['googleId' => $googleId]);
        return $docs->getDoc(0);
    }

    public function loadFields() {
        $fieldsCollection = $this->getFullCollectionName('amo_fields');
        $cursor = $this->mongo->executeQuery($fieldsCollection, new Query([]));
        return $this->mongoToArray($cursor);
    }

    public function loadInstructors() {
        $instructorsCollection = $this->getFullCollectionName('amo_instructors');
        $cursor = $this->mongo->executeQuery($instructorsCollection, new Query([]));

        $instructors = [];
        foreach ($this->mongoToArray($cursor) as $instructor) {
            $instructors[ $instructor['id'] ] = $instructor['name'];
        }

        return $instructors;
    }

    private function finishedStatuses() {
        return [AmoApi::STATUS_SUCCESS, AmoApi::STATUS_CANCELED];
    }

    public function loadFilteredLeads($filter = []) {
        $leadsCollection = $this->getFullCollectionName('amo_leads');

        $cursor = $this->mongo->executeQuery($leadsCollection, new Query($filter));
        $leads = $this->mongoToArray($cursor);

        $fields = $this->loadFields();
        $instructors = $this->loadInstructors();

        return LeadsCollection::fromDbResult($leads, $fields, $instructors);
    }

    public function loadActiveLeads() {
        return $this->loadFilteredLeads([
            'status_id' => ['$nin' => $this->finishedStatuses()]
        ]);
     }

    public function loadCompleteLeads() {
        return $this->loadFilteredLeads([
            'status_id' => ['$in' => $this->finishedStatuses()]
        ]);
    }

    public function loadActiveInstructorLeads($instructorFullName) {
        return $this->loadFilteredLeads([
            'status_id' => ['$nin' => $this->finishedStatuses()],
            '_parsed.instructor' => $instructorFullName,
        ]);
    }

    public function loadGroupLeads($groupName) {
        return $this->loadFilteredLeads([
            '_parsed.group' => $groupName,
        ]);
    }

    public function loadLeadByContactId($contactId) {
        $leads = $this->loadFilteredLeads([
            '_parsed.contactId' => $contactId,
        ])->getLeads();

        return $leads && $leads[0] ? $leads[0] : false;
    }

    public function loadLeadByLogin($login) {
        $phone = AutoSchoolLead::normalizePhone($login);

        $leads = $this->loadFilteredLeads([
            '_parsed.phone' => ['$in' => [$phone, '+'.$phone]],
        ])->getLeads();

        return $leads && $leads[0] ? $leads[0] : false;
    }

    public function loadMetadata($leadId) {
        $leadsCollection = $this->getFullCollectionName('leads_metadata');

        $filter = ['leadId' => ['$in' => [$leadId, "$leadId"]]];
        $cursor = $this->mongo->executeQuery($leadsCollection, new Query($filter));
        $meta = $this->mongoToArray($cursor);
        return $meta && $meta[0] ? $meta[0] : false;
    }

    public function updateMetadata($leadId, $fields) {
        $operations = new BulkWrite;
        $leads = $this->getFullCollectionName('leads_metadata');

        $filter = ["leadId" => $leadId];
        $operations->update($filter, ['$set' => $fields], ["upsert" => true]);

        $result = $this->mongo->executeBulkWrite($leads, $operations);
    }

    public function updatePassword($leadId, $newPassword) {
        return $this->updateMetadata($leadId, ['passwordHash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
    }

    public function updateRole($leadId, $role) {
        return $this->updateMetadata($leadId, ['role' => $role]);
    }

    public function deleteDocByGoogleId($googleId) {
        $collectionName = $this->getFullCollectionName('documents');

        $docsBulk = new BulkWrite;
        $docsBulk->delete(['googleId' => $googleId], ['limit' => 1]);
        $this->mongo->executeBulkWrite($collectionName, $docsBulk);
    }

    public function checkUser($login, $password) {
        $lead = $this->loadLeadByLogin($login);
        if (!$lead) {
            return false;
        }

        $meta = $this->loadMetadata($lead->id());
        if (!$meta) {
            return false;
        }

        $savedPasswordHash = $meta['passwordHash'];
        $metaWithoutPassword = $meta;
        unset($metaWithoutPassword['passwordHash']);

        return password_verify($password, $savedPasswordHash)
            ? $metaWithoutPassword
            : false;
    }
}