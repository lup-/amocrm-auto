<?php

namespace AMO;

use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFields\NumericCustomFieldModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use \League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessTokenInterface;

class AmoApi
{
    private static $instance;

    private $cookieFileName;
    private $userName = 'mailjob@icloud.com';
    private $userHash = '142a2eebe3051c6b30a9d2cbe3c4cbdb';

    private $pipeline = ['1191751', '3469660'];

    private $baseDomain = "mailjob.amocrm.ru";
    private $contactUrl = 'https://mailjob.amocrm.ru/api/v2/contacts';
    private $notesUrl = 'https://mailjob.amocrm.ru/api/v2/notes';

    /**
     * @var AmoCRMApiClient
     */
    private $apiClient;
    /**
     * @var AccessTokenInterface
     */
    private $accessToken;
    private $cache = [];

    const ELEMENT_TYPE_LEAD = 2; //https://www.amocrm.com/developers/content/api/notes/#element_types
    const NOTE_TYPE_COMMON = 4;  //https://www.amocrm.com/developers/content/api/notes/#note_types
    const NOTE_TYPE_SYSTEM = 25;

    const STATUS_SUCCESS = 142;
    const STATUS_CANCELED = 143;

    const GROUP_FIELD_ID = 580073;
    const INSTRUCTOR_FIELD_ID = 398075;
    const HOURS_FIELD_ID = 552963;
    const LINK_FIELD_ID = 559905;

    public function __construct() {
        $this->cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
    }

    public function __destruct() {
        unlink($this->cookieFileName);
    }

    public function auth() {
        $this->accessToken = $this->loadToken();
        $this->makeApiClient();

        return $this;
    }

    private function getEnvVar($varName, $default = false) {
        if (isset($_SERVER[$varName])) {
            return $_SERVER[$varName];
        }

        if (isset($_ENV[$varName])) {
            return $_ENV[$varName];
        }

        return $default;
    }

    private function getTokenPath() {
        $tokenPath = $this->getEnvVar('AMO_TOKEN_PATH');
        if (!$tokenPath) {
            $tokenPath = __DIR__ . "/../../" . $this->getEnvVar('AMO_TOKEN_FILE', 'amo_token.json');
        }

        return $tokenPath;
    }

    private function loadToken() {
        $tokenPath = $this->getTokenPath();
        $accessToken = json_decode(file_get_contents($tokenPath), true);

        return new AccessToken([
            'access_token'  => $accessToken['accessToken'],
            'refresh_token' => $accessToken['refreshToken'],
            'expires'       => $accessToken['expires'],
            'baseDomain'    => $accessToken['baseDomain'],
        ]);
    }

    private function makeApiClient() {
        $tokenPath = $this->getTokenPath();;

        $clientId = $this->getEnvVar('AMO_CLIENT_ID');
        $clientSecret = $this->getEnvVar('AMO_CLIENT_SECRET');
        $redirectUri = $this->getEnvVar('AMO_CLIENT_REDIRECT_URI');

        $this->apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        $this->apiClient->setAccountBaseDomain($this->baseDomain);
        $this->apiClient->setAccessToken($this->accessToken);
        $this->apiClient->onAccessTokenRefresh(
              function (AccessTokenInterface $accessToken, string $baseDomain) use ($tokenPath) {
                  $data = [
                      'accessToken'  => $accessToken->getToken(),
                      'expires'      => $accessToken->getExpires(),
                      'refreshToken' => $accessToken->getRefreshToken(),
                      'baseDomain'   => $baseDomain,
                  ];

                  file_put_contents($tokenPath, json_encode($data));
              }
        );
    }

    public function addNoteToLead($leadId, $text) {
        $addData = [
            "add" => [
                [
                    "element_id"   => $leadId,
                    "element_type" => self::ELEMENT_TYPE_LEAD,
                    "text"         => $text,
                    "note_type"    => self::NOTE_TYPE_COMMON,
                ],
            ]
        ];

        $requestHandle = curl_init();
        curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($requestHandle,CURLOPT_URL, $this->notesUrl);
        curl_setopt($requestHandle,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($requestHandle,CURLOPT_POSTFIELDS, json_encode($addData));
        curl_setopt($requestHandle,CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($requestHandle,CURLOPT_HEADER,false);
        curl_setopt($requestHandle,CURLOPT_COOKIEFILE, $this->cookieFileName);
        $response = curl_exec($requestHandle);

        return $response;
    }

    public function sendFileToLead(Document $document) {
        $noteText = "{$document->getFilename()}: {$document->getEditUrl()}";
        $this->addNoteToLead($document->getUserId(), $noteText);
    }

    public function getContact($contactId) {
        $requestUrl = $this->contactUrl . '?id=' . $contactId;

        $requestHandle = curl_init();
        curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($requestHandle,CURLOPT_URL, $requestUrl);
        curl_setopt($requestHandle,CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($requestHandle,CURLOPT_HEADER,false);
        curl_setopt($requestHandle,CURLOPT_COOKIEFILE, $this->cookieFileName);
        $response = curl_exec($requestHandle);

        $asArray = true;
        $parsedResponse = json_decode($response, $asArray);

        return $parsedResponse
            ? AmoContact::createFromArray($parsedResponse['_embedded']['items'][0])
            : false;
    }

    public function getLeads(LeadsFilter $filter = null, $withContacts = false) {
        if (is_null($filter)) {
            $filter = new LeadsFilter();
        }

        $filter->setLimit(500);
        $leadsService = $this->apiClient->leads();
        $leadsCollection = $leadsService->get($filter, [LeadModel::CONTACTS, LeadModel::CATALOG_ELEMENTS, LeadModel::SOURCE_ID]);
        $allLeads = $leadsCollection;

        try {
            while (!is_null($leadsCollection->getNextPageLink())) {
                $leadsCollection = $leadsService->nextPage($leadsCollection);
                foreach ($leadsCollection as $lead) {
                    $allLeads->add($lead);
                }
            }
        }
        catch (AmoCRMApiNoContentException $e) {
        }

        $leadsCollection = LeadsCollection::fromAmoCollection($allLeads);

        if ($withContacts) {
            $contacts = $this->getContacts();
            $leadsCollection->setContacts($contacts);
        }

        return $leadsCollection;
    }

    public function getLeadById($leadId) {
        $leadsService = $this->apiClient->leads();
        $amoLead = $leadsService->getOne($leadId, [LeadModel::CONTACTS, LeadModel::CATALOG_ELEMENTS, LeadModel::SOURCE_ID]);
        $lead = new AutoSchoolLead($amoLead->toArray());
        $lead->fetchContactData();
        return $lead;
    }

    public function getAllPipelines() {
        $pipelineService = $this->apiClient->pipelines();
        return $pipelineService->get();
    }

    public function getAllStatuses() {
        if (@$this->cache['allStatuses']) {
            return $this->cache['allStatuses'];
        }

        $allStatuses = [];

        foreach ($this->pipeline as $pipelineId) {
            $statusService = $this->apiClient->statuses($pipelineId);
            $statusesCollection = $statusService->get();
            $allStatuses[$pipelineId] = $statusesCollection;
        }

        $this->cache['allStatuses'] = $allStatuses;
        return $allStatuses;
    }

    public function getAllFields($entityType) {
        if (!$entityType) {
            $entityType = EntityTypesInterface::LEADS;
        }

        if ($this->cache['allFields'][$entityType]) {
            return $this->cache['allFields'][$entityType];
        }

        $fieldsService = $this->apiClient->customFields($entityType);

        $fieldsCollection = $fieldsService->get();
        $allFields = $fieldsCollection;

        try {
            while (!is_null($fieldsCollection->getNextPageLink())) {
                $fieldsCollection = $fieldsService->nextPage($fieldsCollection);
                foreach ($fieldsCollection as $field) {
                    $allFields->add($field);
                }
            }
        }
        catch (AmoCRMApiNoContentException $e) {
        }

        $this->cache['allFields'][$entityType] = $allFields->toArray();
        return $this->cache['allFields'][$entityType];
    }

    public function getActiveLeads($filter = null, $withContacts = false) {
        if (is_null($filter)) {
            $filter = new LeadsFilter();
        }

        $finishedStatuses = [self::STATUS_SUCCESS, self::STATUS_CANCELED];

        $filterStatuses = [];
        foreach ($this->getAllStatuses() as $pipelineId => $pipeStatuses) {
            foreach ($pipeStatuses as $status) {
                if (array_search($status->getId(), $finishedStatuses) !== false) {
                    continue;
                }

                $filterStatuses[] = [
                    'status_id'   => $status->getId(),
                    'pipeline_id' => $status->getPipelineId(),
                ];
            }
        }

        $filter->setStatuses($filterStatuses);

        return $this->getLeads($filter, $withContacts);
    }

    public function getAllLeads($withContacts = false) {
        $filter = new LeadsFilter();

        $filterStatuses = [];
        foreach ($this->getAllStatuses() as $pipelineId => $pipeStatuses) {
            foreach ($pipeStatuses as $status) {
                $filterStatuses[] = [
                    'status_id'   => $status->getId(),
                    'pipeline_id' => $status->getPipelineId(),
                ];
            }
        }

        $filter->setStatuses($filterStatuses);

        return $this->getLeads($filter, $withContacts);
    }

    function getActiveInstructorLeads($instructorId) {
        $filter = new LeadsFilter();
        $filter->setCustomFieldsValues([self::INSTRUCTOR_FIELD_ID => $instructorId]);
        return $this->getActiveLeads($filter);
    }

    public function getCompletedLeads($filter = null, $withContacts = false) {
        if (is_null($filter)) {
            $filter = new LeadsFilter();
        }

        $finishedStatuses = [self::STATUS_SUCCESS, self::STATUS_CANCELED];

        $filterStatuses = [];
        foreach ($this->getAllStatuses() as $pipelineId => $pipeStatuses) {
            foreach ($pipeStatuses as $status) {
                if (array_search($status->getId(), $finishedStatuses) !== false) {
                    $filterStatuses[] = [
                        'status_id'   => $status->getId(),
                        'pipeline_id' => $status->getPipelineId(),
                    ];
                }
            }
        }

        $filter->setStatuses($filterStatuses);

        return $this->getLeads($filter, $withContacts);
    }

    public function getSingleLead($id, $withContact = true) {
        $leadsService = $this->apiClient->leads();
        $lead = $leadsService->getOne($id, [LeadModel::CONTACTS, LeadModel::CATALOG_ELEMENTS, LeadModel::SOURCE_ID]);
        $schoolLead = AutoSchoolLead::createFromArray( $lead->toArray() );

        if ($withContact) {
            $schoolLead->setContactData( $this->getSingleContact( $schoolLead->contactId() ) );
        }

        return $schoolLead;
    }

    public function getSingleContact($id) {
        $contactsService = $this->apiClient->contacts();
        $contact = $contactsService->getOne($id);
        return AmoContact::createFromArray($contact->toArray());
    }

    public function getContacts(ContactsFilter $filter = null) {
        if (is_null($filter)) {
            $filter = new ContactsFilter();
        }

        $filter->setLimit(500);
        $contactsService = $this->apiClient->contacts();
        $contactsCollection = $contactsService->get($filter);
        $allContacts = $contactsCollection;

        try {
            while (!is_null($contactsCollection->getNextPageLink())) {
                $contactsCollection = $contactsService->nextPage($contactsCollection);
                foreach ($contactsCollection as $contact) {
                    $allContacts->add($contact);
                }
            }
        }
        catch (AmoCRMApiNoContentException $e) {
        }

        return $allContacts;
    }

    public function getContactsHash(ContactsFilter $filter = null) {
        $contacts = $this->getContacts($filter);

        $contactHash = [];
        foreach ($contacts as $contact) {
            $contactHash[ $contact->getId() ] = $contact->toArray();
        }

        return $contactHash;
    }

    public function getInstructorIds() {
        $fieldsService = $this->apiClient->customFields(EntityTypesInterface::LEADS);
        $field = $fieldsService->getOne(self::INSTRUCTOR_FIELD_ID);

        $instructors = [];
        foreach ($field->getEnums() as $enum) {
            $instructors[ $enum->getId() ] = $enum->getValue();
        }

        return $instructors;
    }

    public function getGroupActiveLeads($groupId) {
        $filter = new LeadsFilter();
        $filter->setCustomFieldsValues([(string) self::GROUP_FIELD_ID => $groupId]);

        return $this->getActiveLeads($filter);
    }

    public function setLeadHours($leadId, $newHours) {
        $lead = $this->apiClient->leads()->getOne($leadId);

        $customFields = $lead->getCustomFieldsValues();

        if (empty($customFields)) {
            $customFields = new CustomFieldsValuesCollection();
        }

        $hoursField = $customFields->getBy('fieldId', self::HOURS_FIELD_ID);
        if (empty($hoursField)) {
            $hoursField = (new NumericCustomFieldValuesModel())->setFieldId(self::HOURS_FIELD_ID);
            $customFields->add($hoursField);
        }

        if (!empty($newHours)) {
            $fieldValues = new NumericCustomFieldValueCollection();
            $fieldValues->add((new NumericCustomFieldValueModel())->setValue($newHours));
        }
        else {
            $fieldValues = new NullCustomFieldValueCollection();
        }
        $hoursField->setValues($fieldValues);

        $lead->setCustomFieldsValues($customFields);
        $this->apiClient->leads()->updateOne($lead);

        return $this->getSingleLead($leadId, true);
    }
    public function setLeadLink($leadId) {
        $link = "http://amo-auto.humanistic.tech/user.html?id=".$leadId;
        $lead = $this->apiClient->leads()->getOne($leadId);

        $customFields = $lead->getCustomFieldsValues();

        if (empty($customFields)) {
            $customFields = new CustomFieldsValuesCollection();
        }

        $linkField = $customFields->getBy('fieldId', self::LINK_FIELD_ID);
        if (empty($linkField)) {
            $linkField = (new TextCustomFieldValuesModel())->setFieldId(self::LINK_FIELD_ID);
            $customFields->add($linkField);
        }

        if (!empty($link)) {
            $fieldValues = new TextCustomFieldValueCollection();
            $fieldValues->add((new TextCustomFieldValueModel())->setValue($link));
        }
        else {
            $fieldValues = new NullCustomFieldValueCollection();
        }
        $linkField->setValues($fieldValues);

        $lead->setCustomFieldsValues($customFields);
        $this->apiClient->leads()->updateOne($lead);

        return $this->getSingleLead($leadId, false);
    }

    public function addLeadNote($leadId, $text) {
        $notes = $this->apiClient->notes(EntityTypesInterface::LEADS);
        $note = new CommonNote();
        $note->setEntityId($leadId)->setText($text);

        $newNotes = new NotesCollection();
        $newNotes->add($note);

        return $notes->add($newNotes);
    }

    /**
     * @return mixed
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new AmoApi();
            self::$instance->auth();
        }

        return self::$instance;
    }
}