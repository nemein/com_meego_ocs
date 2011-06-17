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
        $q = new midgard_query_select(new midgard_query_storage('com_meego_package_basecategory'));
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
        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);

        $query = $this->request->get_query();

        if (count($query))
        {
            if (   array_key_exists('search', $query)
                && strlen($query['search']))
            {
                $cnstr1 = new midgard_query_constraint(
                                new midgard_query_property('packagename'),
                                'LIKE',
                                new midgard_query_value('%' . $query['search'] .'%')
                              );
                $cnstr2 = new midgard_query_constraint(
                                new midgard_query_property('packagetitle'),
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
                        new midgard_query_property('basecategory'),
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
                        new midgard_query_property('repoid'),
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
                                      new midgard_query_property('packagerevised'),
                                      SORT_DESC);
                                  break;
                    case 'alpha':
                                  $q->add_order(
                                      new midgard_query_property('packagename'),
                                      SORT_ASC);
                                  break;
                    case 'high' :
                                  $q->add_order(
                                      new midgard_query_property('packagescore'),
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
        }

        if (isset($args['id']))
        {
            $q->set_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('packageid'),
                    '=',
                    new midgard_query_value($args['id'])
                )
            );
        }

        // 1st execute to get the total number of records
        // required by OCS
        $q->execute();

        $cnt = $q->get_results_count();

        // set page size
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

        // 2nd execute to limit pagesize
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();

        if ($cnt > 0)
        {
            $packages = $q->list_objects();

            foreach ($packages as $package)
            {
                $package->comments_count = 0;

                // get number of comments
                $comments_qs = new midgard_query_storage('com_meego_comments_comment');
                $comments_q = new midgard_query_select($comments_qs);

                $comments_q->set_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('to'),
                        '=',
                        new midgard_query_value($package->packageguid)
                    )
                );

                $comments_q->execute();

                $package->comments_count = $comments_q->get_results_count();

                # debug
                #echo "package: " . $package->packageid . ', ' . $package->packagename . ': ' . $package->comments_count . "\n";
                #ob_flush();

                // get attachments
                $origpackage = new com_meego_package($package->packageguid);
                $package->attachments = $origpackage->list_attachments();
                unset ($origpackage);

                // generate the URL of the package instance
                $repository = new com_meego_repository($package->repoid);

                if (isset($args['id']))
                {
                    $package->commentsurl = midgardmvc_core::get_instance()->dispatcher->generate_url
                    (
                        'package_instance',
                        array
                        (
                            'package' => $package->packagename,
                            'version' => $package->packageversion,
                            'project' => $package->repoproject,
                            'repository' => $package->repoid,
                            'arch' => $repository->repoarch
                        ),
                        'com_meego_packages'
                    );
                }
            }

            // write the xml content
            $ocs->writeMeta($cnt, '', 'ok', '100', $pagesize);
            $ocs->writeContent($packages);
        }
        else
        {
            // item not found
            $ocs->writeMeta($cnt, 'content not found', 'failed', 101);
            $ocs->writeEmptyData();
        }

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    /**
     * Gather licenses
     * @param array HTTP GET arguments
     */
    public function get_licenses(array $args)
    {
        // to store the unique licenses
        $licenses = array();

        $q = new midgard_query_select(new midgard_query_storage('com_meego_package_license'));

        // to get the number of all icenses; required by OCS
        $q->execute();

        $packages = $q->list_objects();

        $id = 0;

        foreach ($packages as $package)
        {
            if ( ! array_key_exists($package->license, $licenses))
            {
                $id++;
                $licenses[$package->license] = array(
                    'id' => $id,
                    'name' => $package->license,
                    /** todo: write a subroutine which can fetch licenses based on their names or something **/
                    'link' => 'N/A'
                );
            }
        }

        $total = count($licenses);

        // set page size
        $pagesize = 10;

        $query = $this->request->get_query();

        if (   array_key_exists('pagesize', $query)
            && strlen($query['pagesize']))
        {
            $pagesize = $query['pagesize'];
        }

        if (count($licenses) > $pagesize)
        {
            $page = 0;

            if (   array_key_exists('page', $query)
                && strlen($query['page']))
            {
                $page = $query['page'];
            }

            $offset = $page * $pagesize;

            if ($offset > count($licenses))
            {
                $offset = count($licenses) - $pagesize;
            }

            $licenses = array_slice($licenses, $offset, $pagesize);
        }

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($total, '', 'ok', '100', $pagesize);
        $ocs->writeLicenses($licenses);

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
