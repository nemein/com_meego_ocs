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

        $ocs->startElement('data');
        foreach ($q->list_objects() as $obj) {
            $ocs->startElement('category');
            $ocs->writeElement('id', $obj->id);
            $ocs->writeElement('name', $obj->name);
            $ocs->endElement(); // category
        }
        $ocs->endElement(); // data

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

    public function get_get(array $args)
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
            $ocs->startElement('data');
            $package = $q->list_objects();

            $ocs->startElement('content');
            $ocs->writeAttribute('details','full');
            $ocs->writeElement('id', $package[0]->id);
            $ocs->writeElement('name', $package[0]->name);
            $ocs->writeElement('version', $package[0]->version);
            $ocs->writeElement('description', $package[0]->description);
            $ocs->writeElement('summary', $package[0]->summary);
            $ocs->writeElement('homepage', $package[0]->url);
            $ocs->writeElement('created', $package[0]->metadata->created);
            $ocs->writeElement('changed', $package[0]->metadata->revised);

            $counter = 0;
            $attachments = $package[0]->list_attachments();
            foreach ($attachments as $attachment)
            {
                $counter++;
                $package[0]->screenshoturl = midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'attachmentserver_variant',
                    array
                    (
                        'guid' => $attachment->guid,
                        'variant' => 'sidesquare',
                        'filename' => $attachment->name,
                    ),
                    '/'
                );
                $package[0]->smallscreenshoturl = midgardmvc_core::get_instance()->dispatcher->generate_url
                (
                    'attachmentserver_variant',
                    array
                    (
                        'guid' => $attachment->guid,
                        'variant' => 'thumbnail',
                        'filename' => $attachment->name,
                    ),
                    '/'
                );

                $ocs->writeElement('previewpic' . $counter, $package[0]->screenshoturl);
                $ocs->writeElement('smallpreviewpic' . $counter, $package[0]->smallscreenshoturl);

                if ($counter == 3)
                    break;
            }

            // Comments
            $qs = new midgard_query_storage('com_meego_comments_comment');
            $qb = new midgard_query_select($qs);
            $qb->set_constraint(new midgard_query_constraint(new midgard_query_property('up', $qs), '=', new midgard_query_value($package[0]->guid)));
            $qb->execute();
            $ocs->writeElement('comments', $qb->get_results_count());

            $ocs->endElement(); //content
        }
        else // item not found
        {
            $ocs->writeMeta($cnt,'content not found','ok',101);
            $ocs->startElement('data');
        }

        $ocs->endElement(); // data
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
