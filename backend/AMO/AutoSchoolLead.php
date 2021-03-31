<?php
namespace AMO;

use \DateTime;
use \Exception;

class AutoSchoolLead
{
    use HasCustomFields;

    protected $rawData;
    const HOUR_PRICE = 275;

    protected $dateFormat = "d.m.Y";
    protected $paymentFields = [413511, 413515, 413517, 413519, 571769];
    protected $invoiceFields = [539217, 539221, 539223, 539225, 571771];
    protected $sidePaymentFields = [587233, 561445];

    protected $docs = [];
    protected $event = false;

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

    public function fieldExists($fieldId) {
        $field = $this->findCustomField($fieldId);
        return !empty($field);
    }

    /**
     * @param AmoContact $contactData
     */
    public function setContactData(AmoContact $contactData) {
        $this->contactData = $contactData;
    }

    /**
     * @return AmoContact
     */
    public function getContactData() {
        return $this->contactData;
    }

    public function setEvent($event) {
        $this->event = $event;
    }

    public function fetchContactData() {
        $contactData = AmoApi::getInstance()->getSingleContact($this->contactId());
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

    public function price() {
        return isset($this->rawData['sale'])
            ? $this->rawData['sale']
            : $this->rawData['price'];
    }

    public function studyPrice() {
        $payment = intval( $this->price() );
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
        $contact = isset($this->rawData['contacts'])
            ? @$this->rawData['contacts'][0]
            : @$this->rawData['main_contact'];
        return @$contact['id'];
    }
    public function name() {
        if ($this->contactData) {
            return $this->contactData->name();
        }

        $contact = isset($this->rawData['contacts'])
            ? @$this->rawData['contacts'][0]
            : @$this->rawData['main_contact'];
        return @$contact['name'];
    }
    public function phone() {
        $phone = $this->getPhoneField(389479);

        if ( empty($phone) ) {
            if (!$this->contactData) {
                return false;
            }

            return $this->contactData->phone();
        }
    }
    public function group() {
        $similarLetters = [
            "A" => "А",
            "B" => "В",
            "E" => "Е",
            "K" => "К",
            "M" => "М",
            "H" => "Н",
            "O" => "О",
            "P" => "Р",
            "T" => "Т",
            "X" => "Х",
        ];

        $rawGroup = $this->getCustomFieldValue(580073);
        $group = trim( strtr($rawGroup, $similarLetters) );
        $group = mb_strtoupper($group);

        return $group;
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
        return $this->rawData['status'] == 142 || $this->rawData['status_id'] == 142;
    }
    public function isCanceled() {
        return $this->rawData['status'] == 143 || $this->rawData['status_id'] == 143;
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
        $event = $this->event ? $this->event : $foundEvent;

        return [
            'id'             => $this->id(),
            'name'           => $this->name(),
            'contact'        => $this->name(),
            'contactId'      => $this->contactId(),
            'hours'          => $this->hours(),
            'neededHours'    => $this->neededHours(),
            'salary'         => $this->hours() * self::HOUR_PRICE,
            'success'        => $this->isSuccessful(),
            'dateFinished'   => $this->finishedDate(),
            'debt'           => $this->totalDebt(),
            'paymentOverdue' => $this->getPaymentOverdueDays(),
            'gsmPayment'     => $this->getPaymentValue(561445),
            'phone'          => $this->phone(),
            'group'          => $this->group(),
            'schedule'       => $event !== false ? $event->getStart()->getDateTime() : false,
            'instructor'     => $this->instructor(),
            'docs'           => $this->docs,
        ];
    }
    public function asDatabaseArray() {
        $result = $this->raw();
        $contact = $this->getContactData();
        $result['_parsed'] = $this->asStudentArray();
        if ($contact) {
            $result['_contact'] = $contact->raw();
        }
        return $result;
    }
    public function asUserArray() {
        $hours = $this->getCustomFieldValue(552963);
        $exam = $this->getDateValue(540659);

        return [
            "ФИО"             => $this->name(),
            "Категория"       => $this->getCustomFieldValue(405003),
            "Группа"          => $this->group(),
            "Коробка"         => $this->getCustomFieldValue(389859),
            "Откат по часам"  => $hours ? $hours : 0,
            "Стоимость"       => $this->studyPrice() ? $this->studyPrice() : 'не указана',
            "Остаток"         => $this->paymentDetails(),
            "Медкомиссия"   => [
                "Серия, номер, лицензия" => $this->getCustomFieldValue(413345),
                "Кем выдано"             => $this->getCustomFieldValue(413347),
                "Когда выдано"           => $this->getDateValue(542317),
            ],
            "Сертификат" => [
                "Серия, номер" => $this->getCustomFieldValue(413337),
                "Кем выдано"   => $this->getCustomFieldValue(413343),
                "Когда выдано" => $this->getDateValue(542325),
            ],
            "Экзамен в ГИБДД" => $exam ? $exam : '-',
            "Организация"     => $this->getCustomFieldValue(590327)
        ];
    }

    private function getFieldName($field) {
        return isset($field['name'])
            ? $field['name']
            : $field['field_name'];
    }
    private function getFieldValue($field) {
        return $field['values'][0]['value'];
    }
    public function asReplacementPairs() {
        if (!$this->contactData) {
            $this->fetchContactData();
        }

        $replacementPairs = [
            'Сделка.ID'                              => $this->rawData['id'],
            'Имя'                                    => $this->contactData->name(),
            'Имя.Фамилия'                            => $this->contactData->familyName(),
            'Имя.Имя'                                => $this->contactData->firstName(),
            'Имя.Отчество'                           => $this->contactData->secondName(),
            'Телефон'                                => $this->phone(),
            'Телефон.Рабочий'                        => $this->phone(),
            'Контакт.Имя'                            => $this->contactData->name(),
            'Контакт.Имя.Фамилия'                    => $this->contactData->familyName(),
            'Контакт.Имя.Имя'                        => $this->contactData->firstName(),
            'Контакт.Имя.Отчество'                   => $this->contactData->secondName(),
            'Контакт.Телефон'                        => $this->phone(),
            'Контакт.Телефон.Рабочий'                => $this->phone(),
            'Бюджет'                                 => $this->price(),
            'Бюджет.Прописью'                        => is_numeric($this->price()) ? $this->numberToText($this->price()) : '',
            'Сделка.Бюджет'                          => $this->price(),
            'Сделка.Бюджет.Прописью'                 => is_numeric($this->price()) ? $this->numberToText($this->price()) : '',
            'Сделка.Ответственный'                   => '',
        ];

        $dateTimeFields = [
            'Дата заключения договора',
            'Медкомиссия, когда выдано',
            'Дата распределения инструктора',
            'Дата начала обучения',
            'Дата окончания обучения',
            'Дата окончания  обучения',
            'Дата оплаты ГСМ',
            'Дата выдачи свидетельства',
            'Дата Экзамена в Гибдд',
            'День рождения',
            'Контакт.День рождения',
            'Дата выдачи паспорта',
            'Контакт.Дата выдачи паспорта'
        ];

        foreach ($this->customFields() as $field) {
            $fieldName = $this->getFieldName($field);
            $fieldValue = $this->getFieldValue($field);
            $isDateField = in_array($fieldName, $dateTimeFields);
            $addNumAsText = is_numeric($fieldValue) && !$isDateField;

            $replacementPairs[$fieldName] = $fieldValue;

            if ($addNumAsText) {
                $replacementPairs[$fieldName . '.Прописью'] = $this->numberToText($fieldValue);
            }
        }

        if ($this->contactData->customFields()) {
            foreach ($this->contactData->customFields() as $field) {
                $fieldName = $this->getFieldName($field);
                $fieldValue = $this->getFieldValue($field);

                $fieldIsEmpty = empty($replacementPairs[$fieldName]);
                if ($fieldIsEmpty) {
                    $replacementPairs[$fieldName] = $fieldValue;
                }
                $replacementPairs['Контакт.'.$fieldName] = $fieldValue;

                if (is_numeric($fieldValue)) {
                    $replacementPairs[ 'Контакт.'.$fieldName.'.Прописью' ] = $this->numberToText($fieldValue);
                }
            }
        }

        foreach ($dateTimeFields as $fieldName) {
            try {
                $date = $replacementPairs[$fieldName];

                if (is_numeric($date)) {
                    $dateAsInt = $date;

                    $parsedDate = new \DateTime();
                    $parsedDate->setTimestamp($dateAsInt);
                    $parsedDate->setTimezone(new \DateTimeZone('Europe/Moscow'));
                }
                else {
                    $dateAsString = $replacementPairs[$fieldName];
                    $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateAsString);

                    if (!$parsedDate) {
                        $parsedDate = DateTime::createFromFormat('d.m.Y', $dateAsString);
                    }
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

    public function raw() {
        return $this->rawData;
    }
}