<?php

namespace AMO;

use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\LeadModel;
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
    private $authUrl = 'https://mailjob.amocrm.ru/private/api/auth.php';
    private $contactUrl = 'https://mailjob.amocrm.ru/api/v2/contacts';
    private $notesUrl = 'https://mailjob.amocrm.ru/api/v2/notes';
    private $leadsUrl = 'https://mailjob.amocrm.ru/api/v2/leads';

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

    private function loadToken() {
        $tokenPath = __DIR__ . "/../../" . $_ENV['AMO_TOKEN_FILE'];
        $accessToken = json_decode(file_get_contents($tokenPath), true);

        return new AccessToken([
            'access_token'  => $accessToken['accessToken'],
            'refresh_token' => $accessToken['refreshToken'],
            'expires'       => $accessToken['expires'],
            'baseDomain'    => $accessToken['baseDomain'],
        ]);
    }

    private function makeApiClient() {
        $tokenPath = __DIR__ . "/../../" . $_ENV['AMO_TOKEN_FILE'];

        $clientId = $_ENV['AMO_CLIENT_ID'];
        $clientSecret = $_ENV['AMO_CLIENT_SECRET'];
        $redirectUri = $_ENV['AMO_CLIENT_REDIRECT_URI'];

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

    public function getAllPipelines() {
        $pipelineService = $this->apiClient->pipelines();
        return $pipelineService->get();
    }

    public function getAllStatuses() {
        if ($this->cache['allStatuses']) {
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