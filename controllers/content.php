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

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($q->get_results_count());
        $ocs->writeCategories($q->list_objects());

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    public function get_distributions(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_repository'));
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();
        $ocs->writeMeta($q->get_results_count());

        $ocs->startElement('data');
        foreach ($q->list_objects() as $obj) {
            $ocs->startElement('distribution');
            $ocs->writeElement('id', $obj->id);
            $ocs->writeElement('name', $obj->name);
            $ocs->endElement(); // distribution
        }
        $ocs->endElement(); // data

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    public function get_data(array $args)
    {
        $storage = new midgard_query_storage('com_meego_package');
        $q = new midgard_query_select($storage);
        $q->set_constraint(new midgard_query_constraint(new midgard_query_property('id', $storage), '=', new midgard_query_value($args['id'])));
        $q->execute();

        $cnt = $q->get_results_count();

        $ocs = new com_meego_ocs_OCSWriter();

        if ($cnt > 0)
        {
            $ocs->writeMeta($cnt);

            $packages = $q->list_objects();

            $comments_qs = new midgard_query_storage('com_meego_comments_comment');
            $comments_q = new midgard_query_select($comments_qs);
            $comments_q->set_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('up', $comments_qs),
                    '=',
                    new midgard_query_value($packages[0]->guid)
                )
            );
            $comments_q->execute();

            $ocs->writeContent($packages[0], $packages[0]->list_attachments(), $comments_q->get_results_count());
        }
        else // item not found
        {
            $ocs->writeMeta($cnt, 'content not found', 'ok', 101);

            $ocs->startElement('data');
            $ocs->endElement(); // data
        }

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    private static function output_xml($xml)
    {
        midgardmvc_core::get_instance()->dispatcher->header('Content-type: application/xml');
        echo $xml->outputMemory(true);

        midgardmvc_core::get_instance()->dispatcher->end_request();
    }

}
