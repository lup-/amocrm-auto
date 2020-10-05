<?php
namespace AMO;


use AmoCRM\Collections\ContactsCollection;

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
        $contactHash = [];
        foreach ($contacts as $contact) {
            $contactHash[ $contact->getId() ] = $contact->toArray();
        }

        $this->setContactsHash($contactHash);
    }

    public function setContactsHash(array $contactHash = []) {
        foreach ($this->leads as $lead) {
            if ($lead->contactId()) {
                $contact = $contactHash[ $lead->contactId() ];
                if ($contact) {
                    $contactModel = AmoContact::createFromArray($contact);
                    $lead->setContactData($contactModel);
                }
            }
        }

        return $this;
    }

    public function __construct($leads, $fields, $instructors) {
        $this->rawLeads = $leads;
        $this->rawFields = $fields;
        $this->instructors = $instructors;

        foreach ($this->rawLeads as $lead) {
            $this->leads[] = new AutoSchoolLead($lead);
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
                $groups[$groupName]['people'] += 1;
                $hours = $lead->hours();
                $groups[$groupName]['totalHours'] += $hours;
                $groups[$groupName]['salary'] += $hours * HOUR_PRICE;
                $groups[$groupName]['students'][] = $lead->asStudentArray();

                if ($withLeads) {
                    $groups[$groupName]['leads'][] = $lead;
                }
            }
        }

        return $groups;
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

    public function getRawLeads() {
        return $this->rawLeads;
    }

    public function getRawFields() {
        return $this->rawFields;
    }

    public function getRawInstructors() {
        return $this->instructors;
    }
}