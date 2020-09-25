<?php
namespace AMO;

use \DateTime;
use \Exception;

class AutoSchoolLead
{
    protected $rawData;

    protected $dateFormat = "d.m.Y";
    protected $paymentFields = [413511, 413515, 413517, 413519, 571769];
    protected $invoiceFields = [539217, 539221, 539223, 539225, 571771];
    protected $sidePaymentFields = [587233, 561445];

    protected $docs = [];

    /**
     * @var AmoContact
     */
    protected $contactData;

    public static function createFromArray($rawData) {
        return new self($rawData);
    }

    public function __construct($rawData) {
        $this->rawData = $rawData;
    }

    public function findCustomField($fieldId) {
        foreach ($this->rawData['custom_fields'] as $fieldData) {
            if ($fieldData['id'] == $fieldId) {
                return $fieldData;
            }
        }

        if ($this->rawData['_extra']) {
            foreach ($this->rawData['_extra']['custom_fields'] as $fieldData) {
                if ($fieldData['id'] == $fieldId) {
                    return $fieldData;
                }
            }
        }

        return null;
    }
    public function fieldExists($fieldId) {
        $field = $this->findCustomField($fieldId);
        return !empty($field);
    }
    public function getCustomFieldValue($fieldId) {
        if (isset($this->rawData['cf' . $fieldId])) {
            return $this->rawData['cf' . $fieldId];
        }

        $customField = $this->findCustomField($fieldId);
        if (!$customField) {
            return null;
        }

        $fieldValue = $customField['values'][0]['value'];

        if ($fieldValue === "false") {
            $fieldValue = false;
        }

        return $fieldValue;
    }
    public function getCustomFieldName($fieldId) {
        $field = $this->findCustomField($fieldId);

        if (!$field) {
            return null;
        }

        return $field['name'];
    }
    public function getPaymentValue($fieldId) {
        $fieldValue = $this->getCustomFieldValue($fieldId);

        preg_match('#^[\d \.,]+#', $fieldValue, $matches);
        if ($matches[0]) {
            $preparedValue = preg_replace('#\W#', '', $matches[0]);
            return intval($preparedValue);
        }

        return false;
    }
    public function getPaymentDate($fieldId) {
        return $this->getDateFromValue( $this->getCustomFieldValue($fieldId) );
    }

    private function formatTimestamp($timestamp) {
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone(new \DateTimeZone('Europe/Moscow'));
        return $date->format($this->dateFormat);
    }
    public function getDateValue($fieldId) {
        $timestamp = $this->getIntValue($fieldId);

        if (!$timestamp) {
            return false;
        }

        return $this->formatTimestamp($timestamp);
    }
    public function getIntValue($fieldId) {
        try {
            $value = intval($this->getCustomFieldValue($fieldId));
        }
        catch (Exception $e) {
            $value = 0;
        }

        return $value;
    }

    /**
     * @param mixed $contactData
     */
    public function setContactData($contactData) {
        $this->contactData = $contactData;
    }

    public function fetchContactData() {
        $contactData = AmoApi::getInstance()->getContact($this->contactId());
        $this->setContactData( $contactData );
        return $this;
    }

    public function isEverythingPayed() {
        return $this->getCustomFieldValue(583197) === '1';
    }

    public function totalDebt() {
        $studyPrice = $this->studyPrice();

        if (!$studyPrice) {
            return $this->getPaymentValue(552815);
        }

        $debt = $studyPrice - $this->totalPaymentsMade();
        return $debt > 0 ? $debt : 0;
    }
    public function studyPrice() {
        $payment = intval( $this->rawData['sale'] );
        if ($payment > 0) {
            return $payment;
        }

        return false;
    }
    public function totalPaymentsMade() {
        $sum = 0;

        foreach ($this->sidePaymentFields as $fieldId) {
            $sum += $this->getPaymentValue($fieldId);
        }

        foreach ($this->paymentFields as $fieldId) {
            $sum += $this->getPaymentValue($fieldId);
        }

        return $sum;
    }

    public function id() {
        return $this->rawData['id'];
    }
    public function contactId() {
        return $this->rawData['main_contact']['id'];
    }
    public function name() {
        return $this->rawData['main_contact']['name'];
    }
    public function phone() {
        $phone = $this->getCustomFieldValue(389479);

        if ( is_array($phone) ) {
            $phone = $phone[0];
        }

        if ( empty($phone) ) {
            if (!$this->contactData) {
                return false;
            }

            return $this->contactData->phone();
        }

        $phone = preg_replace('#\W#', '', $phone);
        if ($phone[0] === '8') {
            $phone[0] = '7';
        }

        if ($phone[0] !== '7') {
            $phone = '7'.$phone;
        }

        return '+'.$phone;
    }
    public function group() {
        return $this->groupData()->name();
    }
    public function instructor() {
        return $this->getCustomFieldValue(398075);
    }
    public function hours() {
        return $this->getIntValue(552963);
    }
    public function neededHours() {
        return $this->getIntValue(414085);
    }

    public function isSuccessful() {
        return $this->rawData['status'] == 142;
    }
    public function isCanceled() {
        return $this->rawData['status'] == 143;
    }
    public function lastChangedDate() {
        if ($this->rawData['last_event_at']) {
            return $this->formatTimestamp( $this->rawData['last_event_at'] );
        }

        return $this->getDateFromValue( $this->rawData['date_create'] );
    }
    public function finishedDate() {
        return $this->isSuccessful()
            ? $this->lastChangedDate()
            : false;
    }

    public function groupData() {
        return Group::createFromLead($this);
    }
    public function sidePayments() {
        $sidePayments = [
            "Остаток" => $this->totalDebt(),
        ];

        foreach ($this->sidePaymentFields as $fieldId) {
            if ($this->fieldExists($fieldId)) {
                $sidePayments[$this->getCustomFieldName($fieldId)] = $this->getPaymentValue($fieldId);
            }
        }

        return $sidePayments;
    }
    public function paymentDetails() {
        $details = $this->sidePayments();

        foreach ($this->paymentFields as $index => $fieldId) {
            if ($this->fieldExists($fieldId)) {
                $fieldName = $this->getCustomFieldName($fieldId);
                $paymentValue = $this->getPaymentValue($fieldId);
                $hasPayment = $paymentValue !== "не задано" && $paymentValue !== "нет";

                if ($hasPayment) {
                    $details[$fieldName] = $paymentValue;
                }
            }
        }

        return $details;
    }
    private function getDateFromValue($valueWithDate) {
        if (!$valueWithDate) {
            return false;
        }

        preg_match('#\d{2}.\d{2}.\d{4}#', $valueWithDate, $matches);
        if ($matches[0]) {
            $ddmmYY = $matches[0];
            $parsedDate = \DateTime::createFromFormat('d.m.Y', $ddmmYY);
            return $parsedDate;
        }

        return false;
    }
    public function getPaymentOverdueDays() {
        if ($this->isEverythingPayed()) {
            return 0;
        }

        if ($this->totalDebt() === 0) {
            return 0;
        }

        $lastSidePaymentDate = false;
        foreach ($this->sidePaymentFields as $fieldId) {
            $paymentDate = $this->getPaymentDate($fieldId);

            if ($paymentDate) {
                if (!$lastSidePaymentDate) {
                    $lastSidePaymentDate = $paymentDate;
                }
                else {
                    $isNewDateGreater = $paymentDate->diff($lastSidePaymentDate) > 0;
                    if ($isNewDateGreater) {
                        $lastSidePaymentDate = $paymentDate;
                    }
                }
            }
        }

        $allInvoicesPayed = true;
        $unpayedInvoiceDate = false;

        foreach ($this->paymentFields as $index => $paymentFieldId) {
            $paymentValue = $this->getPaymentValue($paymentFieldId);

            $invoiceFieldId = $this->invoiceFields[$index];
            $invoiceDate = $this->getPaymentDate($invoiceFieldId);

            if ($invoiceDate) {
                $isInvoicePayed = $paymentValue > 0;
                $allInvoicesPayed = $allInvoicesPayed && $isInvoicePayed;

                if (!$isInvoicePayed && !$unpayedInvoiceDate) {
                    $unpayedInvoiceDate = $invoiceDate;
                }
            }
        }

        $lastPaymentDate = $lastSidePaymentDate;
        if ($unpayedInvoiceDate) {
            if ($lastPaymentDate) {
                $isInvoceDateGreater = $unpayedInvoiceDate->diff($lastPaymentDate) > 0;
                if ($isInvoceDateGreater) {
                    $lastPaymentDate = $unpayedInvoiceDate;
                }
            }
            else {
                $lastPaymentDate = $unpayedInvoiceDate;
            }
        }

        if (!$lastPaymentDate) {
            return 0;
        }

        $today = new \DateTime();
        $daysFromLastPayment = $today->diff($lastPaymentDate)->days;
        return $daysFromLastPayment;
    }

    public function setDocs($docs) {
        $this->docs = $docs;
    }

    private function formatFullRussianDate($parsedDate) {
        $enDate = $parsedDate->format('d F Y');
        $monthNames = [
            'January' => 'Января',
            'February' => 'Февраля',
            'March' => 'Марта',
            'April' => 'Апреля',
            'May' => 'Мая',
            'June' => 'Июня',
            'July' => 'Июля',
            'August' => 'Августа',
            'September' => 'Сентября',
            'October' => 'Окрября',
            'November' => 'Ноября',
            'December' => 'Декабря',
        ];

        $ruDate = strtr($enDate, $monthNames);
        return $ruDate;
    }
    private function normalizeFieldName($fieldName) {
        $fieldName = preg_replace('#\W+#ui', '_', $fieldName);
        $fieldName = mb_strtolower($fieldName);
        $fieldName = trim($fieldName);
        return $fieldName;
    }
    private function numberToText($number) {
        $triplets = [
            1 => ['тысяча', 'тысячи', 'тысяч'],
            2 => ['миллион', 'миллиона', 'миллионов'],
        ];

        $digitNames = [
            0 => [
                1 => 'сто',
                2 => 'двести',
                3 => 'триста',
                4 => 'четыреста',
                5 => 'пятьсот',
                6 => 'шестьсот',
                7 => 'семьсот',
                8 => 'восемьсот',
                9 => 'девятьсот',
            ],
            1 => [
                1 => 'десять',
                2 => 'двадцать',
                3 => 'тридцать',
                4 => 'сорок',
                5 => 'пятьдесят',
                6 => 'шестьдесят',
                7 => 'семьдесят',
                8 => 'восемьдесят',
                9 => 'девяносто',
            ],
            2 => [
                1 => ['один', 'одна'],
                2 => ['два', 'две'],
                3 => 'три',
                4 => 'четыре',
                5 => 'пять',
                6 => 'шесть',
                7 => 'семь',
                8 => 'восемь',
                9 => 'девять',
            ],
        ];
        $tenOnes = [
            '10' => 'десять',
            '11' => 'одинадцать',
            '12' => 'двенадцать',
            '13' => 'тринадцать',
            '14' => 'четырнадцать',
            '15' => 'пятнадцать',
            '16' => 'шестнадцать',
            '17' => 'семнадцать',
            '18' => 'восемнадцать',
            '19' => 'девятнадцать',
        ];

        $padLength = ceil( strlen($number)/3 ) * 3;
        $paddedToFullTriplet = str_pad($number, $padLength, "0", STR_PAD_LEFT);
        $splitByTriplets = str_split($paddedToFullTriplet, 3);

        $text = "";
        foreach ( array_reverse($splitByTriplets) as $tripletIndex => $tripletDigits ) {
            $suffix = "";
            $isThousand = $tripletIndex === 1;
            $firstDigit = floor($tripletDigits / 100);
            $lastTwoDigits = $tripletDigits % 100;
            $lastTwoIsTenOnes = $lastTwoDigits >= 10 && $lastTwoDigits <= 19;
            if ($lastTwoIsTenOnes) {
                $splitDigits = [$firstDigit, $lastTwoDigits];
            }
            else {
                $splitDigits = str_split($tripletDigits, 1);
            }

            if ($tripletIndex > 0) {
                $suffix = declension($tripletDigits, $triplets[$tripletIndex]);
            }

            $thousandText = "";
            foreach ( $splitDigits as $digitIndex => $digit ) {
                $digitText = "";
                if ($lastTwoIsTenOnes && $digitIndex === 1) {
                    $digitText = $tenOnes[$digit];
                }
                else {
                    if ($digit > 0) {
                        $digitText = $digitNames[$digitIndex][$digit];
                        if (is_array($digitText)) {
                            $digitText = $isThousand ? $digitText[1] : $digitText[0];
                        }
                    }
                }

                $thousandText .= $digitText ? $digitText." " : "";
            }

            $thousandText .= $suffix ? $suffix." " : "";
            $text = $thousandText.$text;
        }

        $text = trim($text);

        return $text != "" ? $text : 'ноль';
    }

    public function asStudentArray($foundEvent = false) {
        return [
            'id'             => $this->id(),
            'name'           => $this->name(),
            'contact'        => $this->name(),
            'contactId'      => $this->contactId(),
            'hours'          => $this->hours(),
            'neededHours'    => $this->neededHours(),
            'salary'         => $this->hours() * HOUR_PRICE,
            'success'        => $this->isSuccessful(),
            'dateFinished'   => $this->finishedDate(),
            'debt'           => $this->totalDebt(),
            'paymentOverdue' => $this->getPaymentOverdueDays(),
            'gsmPayment'     => $this->getPaymentValue(561445),
            'phone'          => $this->phone(),
            'group'          => $this->group(),
            'schedule'       => $foundEvent !== false ? $foundEvent->getStart()->getDateTime() : false,
            'instructor'     => $this->instructor(),
            'docs'           => $this->docs,
        ];
    }
    public function asReplacementPairs() {
        $apiLeadData = $this->rawData;
        $contactData = $this->contactData;

        $replacementPairs = [
            'Сделка.ID'               => $apiLeadData['id'],
            'Имя'                     => $contactData->name(),
            'Имя.Фамилия'             => $contactData->familyName(),
            'Имя.Имя'                 => $contactData->firstName(),
            'Имя.Отчество'            => $contactData->secondName(),
            'Телефон'                 => '',
            'Телефон.Рабочий'         => '',
            'Контакт.Имя'             => $contactData->name(),
            'Контакт.Имя.Фамилия'     => $contactData->familyName(),
            'Контакт.Имя.Имя'         => $contactData->firstName(),
            'Контакт.Имя.Отчество'    => $contactData->secondName(),
            'Контакт.Телефон'         => '',
            'Контакт.Телефон.Рабочий' => '',
            'Сделка.Бюджет'           => $apiLeadData['sale'],
            'Сделка.Бюджет.Прописью'  => is_numeric($apiLeadData['sale']) ? $this->numberToText($apiLeadData['sale']) : '',
            'Сделка.Ответственный'    => '',
        ];

        foreach ($apiLeadData['custom_fields'] as $field) {
            $name = $field['name'];
            $value = $field['values'][0]['value'];
            $replacementPairs[ $name ] = $value;

            if (is_numeric($value)) {
                $replacementPairs[ $name.'.Прописью' ] = $this->numberToText($value);
            }
        }

        if ($contactData->customFields()) {
            foreach ($contactData->customFields() as $field) {
                $replacementPairs[$field['name']] = $field['values'][0]['value'];
                $replacementPairs['Контакт.'.$field['name']] = $field['values'][0]['value'];

                if ($field['name'] == 'Телефон') {
                    $replacementPairs['Контакт.Телефон'] = $field['values'][0] ? $field['values'][0]['value'] : '';
                    $replacementPairs['Контакт.Телефон.Рабочий'] = $field['values'][1] ? $field['values'][1]['value'] : '';
                }
            }
        }

        $dateTimeFields = [
            'Дата заключения договора',
            'Медкомиссия, когда выдано',
            'Дата распределения инструктора',
            'Дата начала обучения',
            'Дата окончания обучения',
            'Дата окончания  обучения',
            'Дата выдачи свидетельства',
            'Дата Экзамена в Гибдд',
            'День рождения',
            'Контакт.День рождения',
            'Дата выдачи паспорта',
            'Контакт.Дата выдачи паспорта'
        ];

        foreach ($dateTimeFields as $fieldName) {
            try {
                $dateAsString = $replacementPairs[$fieldName];

                $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateAsString);

                if (!$parsedDate) {
                    $parsedDate = DateTime::createFromFormat('d.m.Y', $dateAsString);
                }

                if ($parsedDate) {
                    $replacementPairs[$fieldName] = $parsedDate->format('d.m.Y');
                    $replacementPairs[$fieldName . '.Полный'] = $this->formatFullRussianDate($parsedDate);
                }
            }
            catch (Exception $e) {
            }
        }

        foreach ($replacementPairs as $field => $value) {
            $replacementPairs[ $this->normalizeFieldName($field) ] = $value;
        }

        return $replacementPairs;
    }
}