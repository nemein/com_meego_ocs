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
        $ocs->writeDistributions($q->list_objects());
        $ocs->endDocument();

        self::output_xml($ocs);
    }

    public function get_data(array $args)
    {
        $storage = new midgard_query_storage('com_meego_package');
        $q = new midgard_query_select($storage);

        $query = $this->request->get_query();
        if (count($query))
        {
            if (   array_key_exists('search', $query)
                && strlen($query['search']))
            {
                $cnstr1 = new midgard_query_constraint(
                                new midgard_query_property('name'),
                                'LIKE',
                                new midgard_query_value('%' . $query['search'] .'%')
                              );
                $cnstr2 = new midgard_query_constraint(
                                new midgard_query_property('title'),
                                'LIKE',
                                new midgard_query_value('%' . $query['search'] .'%')
                              );

                $group_constraint = new midgard_query_constraint_group ("OR", $cnstr1, $cnstr2);

                $q->set_constraint($group_constraint);
            }
            if (   array_key_exists('categories', $query)
                && strlen($query['categories']))
            {
                $q->set_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('category'),
                        'IN',
                        new midgard_query_value(explode('x',$query['categories']))
                    )
                );
            }
            if (   array_key_exists('distribution', $query)
                && strlen($query['distribution']))
            {
                $q->set_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('repository'),
                        'IN',
                        new midgard_query_value(explode(',',$query['distribution']))
                    )
                );
            }
            if (   array_key_exists('sortmode', $query)
                && strlen($query['sortmode']))
            {
                switch ($query['sortmode'])
                {
                    case 'new'  :
                                  $q->add_order(
                                      new midgard_query_property('metadata.revised'),
                                      SORT_DESC);
                                  break;
                    case 'alpha':
                                  $q->add_order(
                                      new midgard_query_property('name'),
                                      SORT_ASC);
                                  break;
                    case 'high' :
                                  $q->add_order(
                                      new midgard_query_property('metadata.score'),
                                      SORT_DESC);
                                  break;
                    case 'down' :
                                  echo "* TODO *";
                                  break;
                    default     :
                                  throw new midgardmvc_exception_notfound("Unknown sort mode.");
                                  break;
                }
            }
            $pagesize = 100;
            if (   array_key_exists('pagesize', $query)
                && strlen($query['pagesize']))
            {
                $pagesize = $query['pagesize'];
            }
            $q->set_limit($pagesize);
            $page = 0;

            if (   array_key_exists('page', $query)
                && strlen($query['page']))
            {
                $page = $query['page'];
            }
            $q->set_offset($page * $pagesize);
        }
        if (isset($args['id']))
        {
            $q->set_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('id'),
                    '=',
                    new midgard_query_value($args['id'])
                )
            );
        }

        $q->execute();

        $cnt = $q->get_results_count();
        $ocs = new com_meego_ocs_OCSWriter();

        if ($cnt > 0)
        {
            $packages = $q->list_objects();

            foreach ($packages as $package){
                $comments_qs = new midgard_query_storage('com_meego_comments_comment');
                $comments_q = new midgard_query_select($comments_qs);
                $comments_q->set_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('up'),
                        '=',
                        new midgard_query_value($packages[0]->guid)
                    )
                );
                $comments_q->execute();
                $package->comments_count = $comments_q->get_results_count();

                $package->attachments = $package->list_attachments();

                $repository = new com_meego_repository($package->repository);

                if (isset($args['id']))
                {
                    $package->commentsurl = midgardmvc_core::get_instance()->dispatcher->generate_url
                    (
                        'package_instance',
                        array
                        (
                            'package' => $package->name,
                            'version' => $package->version,
                            'project' => $package->project,
                            'repository' => $package->repository,
                            'arch' => $repository->arch
                        ),
                        'com_meego_packages'
                    );
                }
            }

            $ocs->writeMeta($cnt);
            $ocs->writeContent($packages);
        }
        else // item not found
        {
            $ocs->writeMeta($cnt, 'content not found', 'failed', 101);
            $ocs->writeEmptyData();
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
