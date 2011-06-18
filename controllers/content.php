<?php
//
// http://freedesktop.org/wiki/Specifications/open-collaboration-services#CONTENT
//
class com_meego_ocs_controllers_content
{
    // @todo: make the default page size configurable
    //        and set it in the constructor
    var $pagesize = 100;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    /**
     * Returns "basecategories", e.g. Games, Multimedia
     * These are not the package categoiries, but the categories
     * MeeGo Apps defined. They are mapped to real package categories anyway.
     *
     * @param array HTTP GET args
     */
    public function get_categories(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_package_basecategory'));
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($q->get_results_count(), $this->pagesize);
        $ocs->writeCategories($q->list_objects());

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    /**
     * Returns "dependencies", ie. various OS releases, ie. meego 1.2
     *
     * @param array HTTP GET args
     */
    public function get_distributions(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_os'));
        $q->execute();

        $total = $q->get_results_count();

        $query = $this->request->get_query();

        if (   array_key_exists('pagesize', $query)
            && strlen($query['pagesize']))
        {
            $this->pagesize = $query['pagesize'];
        }

        $q->set_limit($this->pagesize);

        $page = 0;

        if (   array_key_exists('page', $query)
            && strlen($query['page']))
        {
            $page = $query['page'];
        }

        $offset = $page * $this->pagesize;

        $q->set_offset($offset);

        if ($offset > $total)
        {
            $offset = $total - $this->pagesize;
        }

        // 2nd execute to limit pagesize
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($total, $this->pagesize);
        $ocs->writeDistributions($q->list_objects());

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    /**
     * The main method to return list of packages
     *
     * @param array HTTP GET arguments
     */
    public function get_data(array $args)
    {
        $query = $this->request->get_query();

        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        // a dummy constraint to make sure SELECT will work even if
        // query contains only one argument
        $qc->add_constraint(
            new midgard_query_constraint(
                new midgard_query_property('packageid'),
                '>',
                new midgard_query_value(0)
            )
        );

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

                $qc->add_constraint($group_constraint);
            }
            if (   array_key_exists('categories', $query)
                && strlen($query['categories']))
            {
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('basecategory'),
                        'IN',
                        new midgard_query_value(explode('x',$query['categories']))
                    )
                );
            }
            if (   array_key_exists('license', $query)
                && strlen($query['license']))
            {
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('packagelicenseid'),
                        'IN',
                        new midgard_query_value(explode(',',$query['license']))
                    )
                );
            }
            if (   array_key_exists('distribution', $query)
                && strlen($query['distribution']))
            {
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('repoosversionid'),
                        'IN',
                        new midgard_query_value(explode(',',$query['distribution']))
                    )
                );
            }
            if (   array_key_exists('dependency', $query)
                && strlen($query['dependency']))
            {
                $qc->add_constraint(
                    new midgard_query_constraint(
                        new midgard_query_property('repoosuxid'),
                        'IN',
                        new midgard_query_value(explode(',',$query['dependency']))
                    )
                );
            }
            if (   array_key_exists('sortmode', $query)
                && strlen($query['sortmode']))
            {
                switch ($query['sortmode'])
                {
                    case 'new'  :
                                  $qc->add_order(
                                      new midgard_query_property('packagerevised'),
                                      SORT_DESC);
                                  break;
                    case 'alpha':
                                  $qc->add_order(
                                      new midgard_query_property('packagename'),
                                      SORT_ASC);
                                  break;
                    case 'high' :
                                  $qc->add_order(
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
            $qc->add_constraint(
                new midgard_query_constraint(
                    new midgard_query_property('packageid'),
                    '=',
                    new midgard_query_value($args['id'])
                )
            );
        }

        $q->set_constraint($qc);

        // 1st execute to get the total number of records
        // required by OCS
        $q->execute();

        $total = $q->get_results_count();

        if (   array_key_exists('pagesize', $query)
            && strlen($query['pagesize']))
        {
            $this->pagesize = $query['pagesize'];
        }

        $q->set_limit($this->pagesize);

        $page = 0;

        if (   array_key_exists('page', $query)
            && strlen($query['page']))
        {
            $page = $query['page'];
        }

        $offset = $page * $this->pagesize;

        $q->set_offset($offset);

        if ($offset > $total)
        {
            $offset = $total - $this->pagesize;
        }

        // 2nd execute to limit pagesize
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();

        if ($total > 0)
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
            $ocs->writeMeta($total, $this->pagesize);
            $ocs->writeContent($packages);
        }
        else
        {
            // item not found
            $ocs->writeMeta($total, $this->pagesize, 'content not found', 'failed', 101);
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
        $q = new midgard_query_select(new midgard_query_storage('com_meego_license'));

        $q->execute();

        $licenses = $q->list_objects();

        $total = $q->get_results_count();

        $query = $this->request->get_query();

        if (   array_key_exists('pagesize', $query)
            && strlen($query['pagesize']))
        {
            $this->pagesize = $query['pagesize'];
        }

        if ($total > $this->pagesize)
        {
            $page = 0;

            if (   array_key_exists('page', $query)
                && strlen($query['page']))
            {
                $page = $query['page'];
            }

            $offset = $page * $this->pagesize;

            if ($offset > $total)
            {
                $offset = $total - $this->pagesize;
            }

            $licenses = array_slice($licenses, $offset, $this->pagesize);
        }

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($total, $this->pagesize, '', 'ok', '100');
        $ocs->writeLicenses($licenses);

        $ocs->endDocument();

        self::output_xml($ocs);
    }

    /**
     * Returns "dependencies", ie. various UXes (User Experiences)
     *
     * @param array HTTP GET args
     */
    public function get_dependencies(array $args)
    {
        $q = new midgard_query_select(new midgard_query_storage('com_meego_ux'));

        $q->execute();

        $total = $q->get_results_count();

        $query = $this->request->get_query();

        if (   array_key_exists('pagesize', $query)
            && strlen($query['pagesize']))
        {
            $this->pagesize = $query['pagesize'];
        }

        $q->set_limit($this->pagesize);

        $page = 0;

        if (   array_key_exists('page', $query)
            && strlen($query['page']))
        {
            $page = $query['page'];
        }

        $offset = $page * $this->pagesize;

        $q->set_offset($offset);

        if ($offset > $total)
        {
            $offset = $total - $this->pagesize;
        }

        // 2nd execute to limit pagesize
        $q->execute();

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta($total, $this->pagesize);
        $ocs->writeDependencies($q->list_objects());

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
