<?php

namespace AMO;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

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
        $mongoHost = getenv('MONGO_HOST');
        $mongoDSN = "mongodb://${mongoHost}/";

        $this->mongo = new Manager($mongoDSN);
        $this->dbName = getenv('MONGO_DB');
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

    public function updateLeads(LeadsCollection $leads, $updateSuccessfulLeads = false) {
        $leadsBulk = new BulkWrite;
        $fieldsBulk = new BulkWrite;
        $instructorsBulk = new BulkWrite;

        $leadsCollection = $this->getFullCollectionName($updateSuccessfulLeads ? 'amo_successful_leads' : 'amo_leads');

        $leadsBulk->delete([]);
        foreach ($leads->getRawLeads() as $lead) {
            $newId = new ObjectID;
            $lead['_id'] = $newId;
            $leadsBulk->insert($lead);
        }

        $this->mongo->executeBulkWrite($leadsCollection, $leadsBulk);

        if (!$updateSuccessfulLeads) {
            $fieldsCollection = $this->getFullCollectionName('amo_fields');
            $instructorsCollection = $this->getFullCollectionName('amo_instructors');

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

            $this->mongo->executeBulkWrite($fieldsCollection, $fieldsBulk);
            $this->mongo->executeBulkWrite($instructorsCollection, $instructorsBulk);
        }

        return $this;
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

    public function loadAllDocs() {
        $collectionName = $this->getFullCollectionName('documents');
        $filter = [];

        $query = new Query($filter);
        $cursor = $this->mongo->executeQuery($collectionName, $query);
        $docs = $this->mongoToArray($cursor);

        return DocsCollection::from($docs);
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

    public function loadActiveLeads() {
        $leadsCollection = $this->getFullCollectionName('amo_leads');

        $cursor = $this->mongo->executeQuery($leadsCollection, new Query([]));
        $leads = $this->mongoToArray($cursor);

        $fields = $this->loadFields();
        $instructors = $this->loadInstructors();

        return LeadsCollection::fromDbResult($leads, $fields, $instructors);
    }

    public function loadCompleteLeads() {
        $leadsCollection = $this->getFullCollectionName('amo_successful_leads');

        $cursor = $this->mongo->executeQuery($leadsCollection, new Query([]));
        $leads = $this->mongoToArray($cursor);

        $fields = $this->loadFields();
        $instructors = $this->loadInstructors();

        return LeadsCollection::fromDbResult($leads, $fields, $instructors);
    }

}