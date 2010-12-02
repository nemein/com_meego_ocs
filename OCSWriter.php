<?php

class com_meego_ocs_OCSWriter extends XMLWriter
{
    public function __construct()
    {
        $this->openMemory();
        $this->startElement('ocs');
    }

    public function endDocument()
    {
        $this->endElement(); // ocs
        parent::endDocument();
    }

    public function writeMeta($totalitems, $message = '', $status = 'ok', $statuscode = '100')
    {
        $this->startElement('meta');
        $this->writeElement('status', $status);
        $this->writeElement('statuscode', $statuscode);
        $this->writeElement('message', $message);
        $this->writeElement('totalitems', $totalitems);
        $this->endElement(); // meta
    }
}
