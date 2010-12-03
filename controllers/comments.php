<?php
/**
 * @see http://freedesktop.org/wiki/Specifications/open-collaboration-services#CONTENT
 */
class com_meego_ocs_controllers_comments
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get(array $args)
    {
        if ($args['type'] != 1)
        {
            throw new midgardmvc_exception_notfound("Only CONTENT type supported");
        }

        $primary = new com_meego_package();
        $primary->get_by_id((int) $args['contentid1']);

        if ($args['contentid2'] != 0)
        {
            throw new midgardmvc_exception_notfound("No subcontent available");
        }

        $storage = new midgard_query_storage('com_meego_comments_comment');
        $q = new midgard_query_select($storage);
        $q->set_constraint
        (
            new midgard_query_constraint
            (
                new midgard_query_property('to', $storage), 
                '=',
                new midgard_query_value($primary->guid)
            )
        );
        $q->add_order(new midgard_query_property('metadata.created', $storage), 'ASC');

        $ocs = new com_meego_ocs_OCSWriter();

        // First run a query of the whole set to get results count
        $q->execute();
        $cnt = $q->get_results_count();
        $ocs->writeMeta($cnt);

        $query = $this->request->get_query();
        $page = 0;
        if (isset($query['page']))
        {
            $page = $query['page'];
        }

        $pagesize = midgardmvc_core::get_instance()->configuration->list_pagesize;
        if (isset($query['pagesize']))
        {
            $pagesize = $query['pagesize'];
        }

        $q->set_limit($pagesize);
        $q->set_offset($page * $pagesize);

        $q->execute();
        $comments = $q->list_objects();
        $ocs->startElement('data');
        foreach ($comments as $comment)
        {
            $ocs->startElement('comment');
            $ocs->writeElement('id', $comment->id);
            $ocs->writeElement('subject', $comment->title);
            $ocs->writeElement('text', $comment->content);
            // TODO: Implement comment replies
            $ocs->writeElement('childcount', 0);
            // TODO: Get from the joined table
            $ocs->writeElement('user', '');
            $ocs->writeElement('date', $comment->metadata->created->format('c'));
            $ocs->writeElement('score', $comment->metadata->score);
            $ocs->endElement(); 
        }
        $ocs->endElement();
        $ocs->endDocument();

        self::output_xml($ocs);
    }
}
