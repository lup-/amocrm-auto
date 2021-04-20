<?php

namespace AMO;

use Carbon\Carbon;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;
use stdClass;

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

    public function loadGroups($complete = false) {
        $filter = ['isSuccess' => $complete];

        $pipeline = [
            [ '$match' =>  $filter ],
            [ '$unwind' => '$students' ],
            [ '$addFields' => ['studentId' => ['$convert' => ['input' => '$students.id', 'to' => 'string']]] ],
            [ '$lookup' => [
                    'from' => 'documents',
                    'localField' => 'studentId',
                    'foreignField' => 'userId',
                    'as' => 'students.docs',
            ] ],
            [ '$group' => ["_id" => '$name', 'group' => [ '$first' => '$$ROOT' ], 'students' => ['$push' => '$students']] ],
            [ '$addFields' => ['group.students' => '$students'] ],
            [ '$replaceRoot' => ['newRoot' => '$group'] ],
            [ '$unset' => 'studentId' ],
        ];

        $command = new Command([
            'aggregate' => 'amo_groups',
            'pipeline' => $pipeline,
            'cursor' => new stdClass,
        ]);
        $cursor = $this->mongo->executeCommand($this->dbName, $command);
        $groups = $this->mongoToArray($cursor);

        return $groups;
    }

    public function loadActiveGroups() {
        return $this->loadGroups(false);
    }

    public function loadCompleteGroups() {
        return $this->loadGroups(true);
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

    public function loadMetadataByLogin($login) {
        $phone = $login;
        $normalPhone = HasCustomFields::normalizePhone($phone);
        $leadsCollection = $this->getFullCollectionName('leads_metadata');

        $filter = ['phone' => $phone];
        $cursor = $this->mongo->executeQuery($leadsCollection, new Query($filter));
        $meta = $this->mongoToArray($cursor);
        return $meta && $meta[0] ? $meta[0] : false;
    }

    public function loadMetadata($leadId) {
        $leadsCollection = $this->getFullCollectionName('leads_metadata');

        $filter = ['leadId' => ['$in' => [$leadId, "$leadId"]]];
        $cursor = $this->mongo->executeQuery($leadsCollection, new Query($filter));
        $meta = $this->mongoToArray($cursor);
        return $meta && $meta[0] ? $meta[0] : false;
    }

    public function updateMetadata(AutoSchoolLead $lead, $fields) {
        $leadId = $lead->id();
        $phone = $lead->phone();
        $operations = new BulkWrite;
        $leads = $this->getFullCollectionName('leads_metadata');

        $filter = ["leadId" => $leadId];
        $operations->update($filter, ['$set' => $fields, '$setOnInsert' => ["phone" => $phone]], ["upsert" => true]);

        $result = $this->mongo->executeBulkWrite($leads, $operations);
    }

    public function updatePassword($lead, $newPassword) {
        return $this->updateMetadata($lead, ['passwordHash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
    }

    public function updateRole($lead, $role) {
        return $this->updateMetadata($lead, ['role' => $role]);
    }

    public function deleteDocByGoogleId($googleId) {
        $collectionName = $this->getFullCollectionName('documents');

        $docsBulk = new BulkWrite;
        $docsBulk->delete(['googleId' => $googleId], ['limit' => 1]);
        $this->mongo->executeBulkWrite($collectionName, $docsBulk);
    }

    public function checkUser($login, $password) {
        $meta = $this->loadMetadataByLogin($login);
        if (!$meta) {
            $lead = $this->loadLeadByLogin($login);
            if (!$lead) {
                return false;
            }

            $meta = $this->loadMetadata($lead->id());
            if (!$meta) {
                return false;
            }
        }

        $savedPasswordHash = $meta['passwordHash'];
        $metaWithoutPassword = $meta;
        unset($metaWithoutPassword['passwordHash']);

        return password_verify($password, $savedPasswordHash)
            ? $metaWithoutPassword
            : false;
    }

    public function getExams($leadId) {
        $collectionName = $this->getFullCollectionName('exams');

        $query = new Query(["userId" => $leadId]);
        $cursor = $this->mongo->executeQuery($collectionName, $query);

        $exams = $this->mongoToArray($cursor);

        return $exams && count($exams) > 0 ? $exams : false;
    }

    public function getActiveExams($leadId) {
        $allExams = $this->getExams($leadId);
        if (!$allExams) {
            return false;
        }

        $startOfDay = Carbon::now()->startOfDay()->unix();

        $activeExams = [];
        foreach ($allExams as $exam) {
            if ($exam['saved'] >= $startOfDay) {
                $activeExams[] = $exam;
            }
        }

        return $activeExams;
    }

    public function hasPassedExam($leadId) {
        $allExams = $this->getExams($leadId);
        if (!$allExams) {
            return false;
        }

        $passedByDates = [];
        foreach ($allExams as $exam) {
            $date = Carbon::createFromTimestamp($exam['saved'])->toDateString();
            if (!array_key_exists($date, $passedByDates)) {
                $passedByDates[$date] = 0;
            }

            $hasPassed = is_bool($exam['examResult']['result']['isCorrect'])
                ? $exam['examResult']['result']['isCorrect']
                : strtolower($exam['examResult']['result']['isCorrect']) === 'true';

            if ($hasPassed) {
                $passedByDates[$date]++;
            }
        }

        $hasPassed = false;
        foreach ($passedByDates as $countPassed) {
            $dayPassed = $countPassed >= 2;
            $hasPassed = $hasPassed || $dayPassed;
        }

        return $hasPassed;
    }

    public function canPassExam($leadId) {
        if ($this->hasPassedExam($leadId)) {
            return false;
        }

        $exams = $this->getActiveExams($leadId);
        if (!$exams) {
            return true;
        }

        $todayTries = count($exams);
        return $todayTries < 3;
    }

    public function saveExam($leadId, $attempt, $examResult) {
        $operations = new BulkWrite;
        $collectionName = $this->getFullCollectionName('exams');

        $operations->update(
            [ "userId" => $leadId, "attempt" => $attempt ],
            [
                "\$set" => ["examResult" => $examResult, "updated" => time()],
                "\$setOnInsert" => ["saved" => time()],
            ],
            [ "upsert" => true ]
        );

        $this->mongo->executeBulkWrite($collectionName, $operations);
        return $this->getExams($leadId);
    }

    public function getActiveInstructors($allInstructorIds) {
        $pipeline = [
            [ '$match' =>  ['status_id' => ['$nin' => [142, 143]], '_parsed.instructor' => ['$nin' => [null, false, '']]] ],
            [ '$group' => [
                    '_id' => "\$_parsed.instructor",
                    "name" => ['$first' => "\$_parsed.instructor"],
                    'groups' => ['$addToSet' => "\$_parsed.group"],
                    'students' => ['$addToSet' => "\$_parsed"]
                ]
            ]
        ];

        $command = new Command([
            'aggregate' => 'amo_leads',
            'pipeline' => $pipeline,
            'cursor' => new stdClass,
        ]);
        $cursor = $this->mongo->executeCommand($this->dbName, $command);
        $instructorData = $this->mongoToArray($cursor);

        $result = [];
        foreach ($allInstructorIds as $id => $name) {
            foreach ($instructorData as $data) {
                if ($data['name'] === $name) {
                    $data['id'] = $id;
                    $result[] = $data;
                }
            }
        }

        return $result;
    }
}