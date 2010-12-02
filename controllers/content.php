<?php
//
// http://freedesktop.org/wiki/Specifications/open-collaboration-services#CONTENT
//
class com_meego_ocs_controllers_content
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_categories(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_package_category'));
        $q->execute();

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startElement('ocs');

        $xml->startElement('meta');
        $xml->writeElement('status', 'ok');
        $xml->writeElement('statuscode', '100');
        $xml->writeElement('message', '');
        $xml->writeElement('totalitems', $q->get_results_count());
        $xml->endElement(); // meta

        $xml->startElement('data');
        foreach ($q->list_objects() as $obj) {
            $xml->startElement('category');
            $xml->writeElement('id', $obj->id);
            $xml->writeElement('name', $obj->name);
            $xml->endElement(); // category
        }
        $xml->endElement(); // data

        $xml->endElement(); // ocs
        $xml->endDocument();

        $this->output_xml($xml);
    }

    private function output_xml($xml)
    {
        midgardmvc_core::get_instance()->dispatcher->header('Content-type: application/xml');
        echo $xml->outputMemory(true);

        midgardmvc_core::get_instance()->dispatcher->end_request();
    }

}
