<?php

namespace AMO;


class AutoSchoolLead
{
    protected $rawData;

    protected $dateFormat = "d.m.Y";
    protected $paymentFields = [413511, 413515, 413517, 413519, 571769];
    protected $invoiceFields = [539217, 539221, 539223, 539225, 571771];
    protected $sidePaymentFields = [587233, 561445];

    protected $docs = [];

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
    public function name() {
        return $this->rawData['main_contact']['name'];
    }
    public function phone() {
        $phone = $this->getCustomFieldValue(389479);
        if ( is_array($phone) ) {
            $phone = $phone[0];
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

    public function asStudentArray($foundEvent = false) {
        return [
            'id'             => $this->id(),
            'name'           => $this->name(),
            'contact'        => $this->name(),
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
}