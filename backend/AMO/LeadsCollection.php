<?php
namespace AMO;

use AmoCRM\Collections\ContactsCollection;
use Google_Service_Calendar_Event;

class LeadsCollection
{
    protected $rawLeads;
    protected $rawFields;

    /**
     * @var AutoSchoolLead[]
     */
    protected $leads = [];
    protected $instructors = [];

    /**
     * @var DocsCollection
     */
    protected $docs;
    protected $contacts;


    public static function fromDbResult($leads, $fields, $instructors) {
        return new self($leads, $fields, $instructors);
    }

    /**
     * @param mixed $amoLeadsCollection
     * @param ContactsCollection $contactsCollection
     * @param array $fields
     * @param array $instructors
     * @return LeadsCollection
     */
    public static function fromAmoCollection($amoLeadsCollection, $contactsCollection = null, $fields = [], $instructors = []) {
        $leads = [];
        if ($amoLeadsCollection) {
            foreach ($amoLeadsCollection as $amoLead) {
                $leads[] = $amoLead->toArray();
            }
        }

        return new self($leads, $fields, $instructors);
    }

    /**
     * @param DocsCollection $docs
     */
    public function setDocs(DocsCollection $docs) {
        $this->docs = $docs;

        foreach ($this->leads as $lead) {
            $leadDocs = $docs->getDocsForUser( $lead->id() );
            $lead->setDocs( $leadDocs );
        }
    }

    public function setContacts(ContactsCollection $contacts) {
        $this->contacts = $contacts;

        $contactHash = [];
        foreach ($contacts as $contact) {
            $contactHash[ $contact->getId() ] = $contact->toArray();
        }

        return $this->setContactsHash($contactHash);
    }

    public function setContactsHash(array $contactHash = []) {
        foreach ($this->leads as $lead) {
            if ($lead->contactId()) {
                $contact = @$contactHash[ $lead->contactId() ];
                if ($contact) {
                    $contactModel = AmoContact::createFromArray($contact);
                    $lead->setContactData($contactModel);
                }
            }
        }

        return $this;
    }

    public function setEvents($events = []) {
        if (!$events) {
            return false;
        }

        foreach ($this->leads as $lead) {
            $name = $lead->name();
            /**
             * @var $foundEvent Google_Service_Calendar_Event
             */
            $foundEvent = false;
            foreach ($events as $event) {
                if ($name && $event->summary === $name) {
                    $foundEvent = $event;
                }
            }

            if ($foundEvent) {
                $lead->setEvent($foundEvent);
            }
        }

        return $this;
    }

    public function __construct($leads, $fields, $instructors) {
        $this->rawLeads = $leads;
        $this->rawFields = $fields;
        $this->instructors = $instructors;

        foreach ($this->rawLeads as $lead) {
            $this->leads[] = AutoSchoolLead::createFromDbArray($lead);
        }
    }

    public function groupByInstructors() {
        $groupedLeads = [];

        foreach ($this->leads as $lead) {
            $instructor = $lead->instructor();

            if ($instructor) {
                if (!isset($groupedLeads[$instructor])) {
                    $groupedLeads[$instructor] = [];
                }

                $groupedLeads[$instructor][] = $lead->asStudentArray();
            }
        }

        return $groupedLeads;
    }
    public function groupByGroup() {
        $groupedLeads = [];

        foreach ($this->leads as $lead) {
            $group = $lead->group();

            if ($group) {
                if (!isset($groupedLeads[$group])) {
                    $groupedLeads[$group] = [];
                }

                $groupedLeads[$group][] = $lead->asStudentArray();
            }
        }

        return $groupedLeads;
    }

    private function joinArrayData($dst, $src) {
        $result = [];
        $allKeys = array_unique( array_merge(array_keys($dst), array_keys($src)) );

        foreach ($allKeys as $key) {
            if (empty($dst[$key])) {
                $result[$key] = @$src[$key];
            }
            else {
                $result[$key] = @$dst[$key];
            }
        }

        return $result;
    }

    private function joinLeadData($dst, $src) {
        $merged = $this->joinArrayData($dst, $src);

        $mergedFields = [];

        foreach ($src['custom_fields_values'] as $srcField) {
            $mergedFields[$srcField['field_id']] = $srcField;
        }

        foreach ($dst['custom_fields_values'] as $dstField) {
            $mergedFields[$dstField['field_id']] = $dstField;
        }

        $merged['custom_fields_values'] = array_values($mergedFields);
        $merged['contacts'] = array_unique( array_merge($src['contacts'], $dst['contacts']), SORT_REGULAR );

        $mergedLead = AutoSchoolLead::createFromDbArray($merged);
        return $mergedLead->asDatabaseArray();
    }

    public function getGroups($withLeads = false) {
        $groups = [];

        foreach ($this->leads as $lead) {
            $groupName = $lead->group();
            $isCorrectGroupName = $groupName !== 'false';
            $isGroupAdded = isset($groups[$groupName]);

            if ($groupName && $isCorrectGroupName && !$isGroupAdded) {
                $groups[$groupName] = $lead->groupData()->asArray();
                $groups[$groupName]["people"]     = 0;
                $groups[$groupName]["totalHours"] = 0;
                $groups[$groupName]["salary"]     = 0;
                $groups[$groupName]["leads"]      = [];
                $groups[$groupName]["docs"]       = $this->docs ? $this->docs->getDocsForGroup($groupName) : [];

                if ($withLeads) {
                    $groups[$groupName]["leads"] = [];
                }
            }

            if ($groupName && $isCorrectGroupName) {
                $groups[$groupName] = $this->joinArrayData($groups[$groupName], $lead->groupData()->asArray());

                $groups[$groupName]['people'] += 1;
                $hours = $lead->hours();
                $groups[$groupName]['totalHours'] += $hours;
                $groups[$groupName]['salary'] += $hours * AutoSchoolLead::HOUR_PRICE;
                $groups[$groupName]['students'][$lead->id()] = $lead->asStudentArray();

                if ($withLeads) {
                    $groups[$groupName]['leads'][] = $lead;
                }
            }
        }

        foreach ($groups as $groupName => $group) {
            $groups[$groupName]['students'] = $this->joinDuplicateStudents($group['students']);
            if ($withLeads) {
                $groups[$groupName]['leads'] = $this->joinDuplicateLeads($group['leads']);
            }
        }

        return $groups;
    }

    public function joinDuplicateLeads($leads) {
        if (empty($leads)) {
            return [];
        }

        $uniqueLeads = [];
        foreach ($leads as $lead) {
            $uniqueId = $lead->contactId().':'.$lead->category();
            if (isset($uniqueLeads[$uniqueId])) {
                $leadA = $lead->raw();
                $leadB = $uniqueLeads[$uniqueId]->raw();

                $dateModifiedA = $leadA['updated_at'];
                $dateModifiedB = $leadB['updated_at'];

                if ($dateModifiedA > $dateModifiedB) {
                    $old = $leadB;
                    $new = $leadA;
                }
                else {
                    $old = $leadA;
                    $new = $leadB;
                }

                $merged = $this->joinLeadData($new, $old);
                $merged['links'] = $merged['links'] ?? [];
                $merged['links'][] = $old;
                $merged['links'][] = $new;
                $merged['links'] = array_unique($merged['links'], SORT_REGULAR);

                $uniqueLeads[$uniqueId] = AutoSchoolLead::createFromDbArray($merged);
            }
            else {
                $uniqueLeads[$uniqueId] = $lead;
            }
        }

        return array_values($uniqueLeads);
    }

    public function joinDuplicateStudents($students) {
        if (empty($students)) {
            return [];
        }

        $uniqueStudents = [];

        foreach ($students as $student) {
            $uniqueId = $student['contactId'].':'.$student['category'];

            if ( isset($uniqueStudents[$uniqueId]) ) {
                $dateModifiedA = $uniqueStudents[$uniqueId]['lastModified'];
                $dateModifiedB = $student['lastModified'];

                if ($dateModifiedA > $dateModifiedB) {
                    $old = $student;
                    $new = $uniqueStudents[$uniqueId];
                }
                else {
                    $new = $student;
                    $old = $uniqueStudents[$uniqueId];
                }

                $merged = $this->joinArrayData($new, $old);
                $merged['links'] = $merged['links'] ?? [];
                $merged['links'][] = $old;
                $merged['links'][] = $new;
                $merged['links'] = array_unique($merged['links'], SORT_REGULAR);

                $uniqueStudents[$uniqueId] = $merged;
            }
            else {
                $uniqueStudents[$uniqueId] = $student;
            }
        }

        return array_values($uniqueStudents);
    }

    public function setInstructors(array $instructors) {
        $this->instructors = $instructors;
        return $this;
    }

    public function getInstructors() {
        $instructorsData = [];
        $allInstructorLeads = $this->groupByInstructors();
        $allGroups = $this->getGroups();

        foreach ($this->instructors as $id => $name) {
            $instructorLeads = $allInstructorLeads[$name];
            $instructorGroupNames = array_unique( array_map( function ($lead) {
                return $lead['group'];
            }, $instructorLeads) );

            $instructorGroups = array_filter($allGroups, function ($groupName) use ($instructorGroupNames) {
                return array_search($groupName, $instructorGroupNames) !== false;
            }, ARRAY_FILTER_USE_KEY);

            $instructorsData[] = [
                'id' => $id,
                'name' => $name,
                'groups' => array_values($instructorGroups),
                'students' => $instructorLeads,
            ];
        }

        return $instructorsData;
    }

    public function getInstructorLeads($instructor) {
        $filteredLeads = [];
        foreach ($this->leads as $lead) {
            $instructorField = $lead->findCustomField(398075);
            $leadInstructor = $instructorField['values'][0]['value'];

            if ($leadInstructor === $instructor) {
                $filteredLeads[] = $lead->raw();
            }
        }

        $resultCollection = new LeadsCollection($filteredLeads, $this->rawFields, $this->instructors);
        if ($this->contacts) {
            $resultCollection->setContacts($this->contacts);
        }

        if ($this->docs) {
            $resultCollection->setDocs($this->docs);
        }

        return $resultCollection;
    }

    public function getContactIds() {
        $contactIds = [];
        foreach ($this->leads as $lead) {

        }
    }

    public function getLeads() {
        return $this->leads;
    }

    public function getRawLeads() {
        return $this->rawLeads;
    }

    public function getRawFields() {
        return $this->rawFields;
    }

    public function getRawInstructors() {
        return $this->instructors;
    }

    public function asStudentArrays() {
        $result = [];

        foreach ($this->leads as $lead) {
            $result[] = $lead->asStudentArray();
        }

        return $result;
    }
}