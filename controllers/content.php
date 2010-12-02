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

    public function get_distributions(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_repository'));
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
            $xml->startElement('distribution');
            $xml->writeElement('id', $obj->id);
            $xml->writeElement('name', $obj->name);
            $xml->endElement(); // distribution
        }
        $xml->endElement(); // data

        $xml->endElement(); // ocs
        $xml->endDocument();

        $this->output_xml($xml);
    }

    public function get_get(array $args)
    {
        $storage = new midgard_query_storage('com_meego_package');
        $q = new midgard_query_select($storage);
        $q->set_constraint(new midgard_query_constraint(new midgard_query_property('id', $storage), '=', new midgard_query_value($args['id'])));
        $q->execute();
        if ($q->get_results_count() > 0)
        {
            $package=$q->list_objects();

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

                $xml->startElement('content');
                $xml->writeAttribute('details','full');
                $xml->writeElement('id', $package[0]->id);
                $xml->writeElement('name', $package[0]->name);
                $xml->writeElement('version', $package[0]->version);
                $xml->writeElement('description', $package[0]->description);
                $xml->writeElement('summary', $package[0]->summary);
                $xml->writeElement('homepage', $package[0]->url);

                $xml->endElement(); //content

            $xml->endElement(); // data

            $xml->endElement(); // ocs
            $xml->endDocument();
        }

        $this->output_xml($xml);
    }

    private function output_xml($xml)
    {
        midgardmvc_core::get_instance()->dispatcher->header('Content-type: application/xml');
        echo $xml->outputMemory(true);

        midgardmvc_core::get_instance()->dispatcher->end_request();
    }

}
