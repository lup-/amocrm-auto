<?php
namespace AMO;


class LeadsCollection
{
    protected $rawLeads;
    protected $rawFields;

    protected $leads = [];
    protected $instructors = [];



    public static function loadAllFromInterface() {
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);
        $responseData = loadAllLeadsWithExtraData($cookieFileName);

        $instructors = loadInstructorIds($cookieFileName);

        return new self($responseData['response']['items'], $responseData['response']['fields'], $instructors);
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

    public function getGroups() {
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
            }

            if ($groupName && $isCorrectGroupName) {
                $groups[$groupName]['people'] += 1;
                $hours = $lead->hours();
                $groups[$groupName]['totalHours'] += $hours;
                $groups[$groupName]['salary'] += $hours * HOUR_PRICE;
                $groups[$groupName]['students'][] = $lead->asStudentArray();
            }
        }

        return $groups;
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
}