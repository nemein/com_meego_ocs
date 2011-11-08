<?php
//
// http://freedesktop.org/wiki/Specifications/open-collaboration-services#CONTENT
//
class com_meego_ocs_controllers_content
{
    var $request = null;
    var $mvc = null;

    // @todo: make the default page size configurable
    //        and set it in the constructor
    var $pagesize = 100;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->mvc = midgardmvc_core::get_instance();
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
     * Returns "distributions", ie. various OS releases, ie. meego 1.2
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
        $constraints = array();

        $query = $this->request->get_query();

        $storage = new midgard_query_storage('com_meego_package_details');
        $q = new midgard_query_select($storage);

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

                $constraints[] = $group_constraint;
            }
            if (   array_key_exists('categories', $query)
                && strlen($query['categories']))
            {
                $constraints[] = new midgard_query_constraint(
                    new midgard_query_property('basecategory'),
                    'IN',
                    new midgard_query_value(explode('x', $query['categories']))
                );
            }
            if (   array_key_exists('license', $query)
                && strlen($query['license']))
            {
                $constraints[] = new midgard_query_constraint(
                    new midgard_query_property('packagelicenseid'),
                    'IN',
                    new midgard_query_value(explode(',', $query['license']))
                );
            }
            if (   array_key_exists('distribution', $query)
                && strlen($query['distribution']))
            {
                $constraints[] = new midgard_query_constraint(
                    new midgard_query_property('repoosversionid'),
                    'IN',
                    new midgard_query_value(explode(',', $query['distribution']))
                );
            }
            if (   array_key_exists('dependency', $query)
                && strlen($query['dependency']))
            {
                $constraints[] = new midgard_query_constraint(
                    new midgard_query_property('repoosuxid'),
                    'IN',
                    new midgard_query_value(explode(',', $query['dependency']))
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
                                      new midgard_query_property('statscachedratings'),
                                      SORT_DESC);
                                  break;
                    case 'down' :
                                  $q->add_order(
                                      new midgard_query_property('statscachedratings'),
                                      SORT_ASC);
                                  break;
                    default     :
                                  throw new midgardmvc_exception_notfound("Unknown sort mode.");
                                  break;
                }
            }
        }

        if (isset($args['id']))
        {
            $constraints[] = new midgard_query_constraint(
                new midgard_query_property('packageid'),
                '=',
                new midgard_query_value($args['id'])
            );
        }

        $qc = null;

        if (count($constraints) > 1)
        {
            $qc = new midgard_query_constraint_group('AND');
            foreach($constraints as $constraint)
            {
                $qc->add_constraint($constraint);
            }
        }
        else
        {
            if (isset($constraints[0]))
            {
                $qc = $constraints[0];
            }
        }

        if (is_object($qc))
        {
            $q->set_constraint($qc);
        }

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
            $localpackages = array();
            $packages = $q->list_objects();

            foreach ($packages as $package)
            {
                // filtering
                $filtered = false;

                // filter packages by their titles (see configuration: package_filters)
                foreach ($this->mvc->configuration->package_filters as $filter)
                {
                    if (   ! $filtered
                        && preg_match($filter, $package->packagename))
                    {
                        $filtered = true;
                    }
                }

                if ($filtered)
                {
                    --$total;
                    continue;
                }

                // need to group the packages so that they appear as applications
                // for that we need an associative array
                if (array_key_exists($package->packagetitle, $localpackages))
                {
                    // if there are multiple version of the same packages then we
                    // should keep the latest only
                    if ($package->packageversion <= $localpackages[$package->packagetitle]->packageversion)
                    {
                        continue;
                    }
                }

                // set a special flag if the package is from a testing repository
                $package->testing = false;

                foreach ($this->mvc->configuration->top_projects as $top_project)
                {
                    if ($top_project['staging'] == $package->repoprojectname)
                    {
                        $package->testing = true;
                    }
                }

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

                // get attachments
                $origpackage = new com_meego_package($package->packageguid);
                $package->attachments = $origpackage->list_attachments();
                unset ($origpackage);

                // generate the URL of the package instance
                if (isset($args['id']))
                {
                    $path = midgardmvc_core::get_instance()->dispatcher->generate_url
                    (
                        'package_instance',
                        array
                        (
                            'package' => $package->packagename,
                            'version' => $package->packageversion,
                            'project' => $package->repoprojectname,
                            'repository' => $package->reponame,
                            'arch' => $package->repoarch
                        ),
                        '/'
                    );

                    $package->commentsurl = com_meego_ocs_controllers_providers::generate_url($path);
                }

                $localpackages[] = $package;
            }

            // write the xml content
            $ocs->writeMeta($total, $this->pagesize);
            $ocs->writeContent(array_values($localpackages));
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

    /**
     * Process vote posts
     */
    public function post_vote(array $args)
    {
        // Voting requires basic auth
        $basic_auth = new midgardmvc_core_services_authentication_basic();
        $e = new Exception("Vote posting requires Basic authentication");
        $basic_auth->handle_exception($e);

        $ocs = new com_meego_ocs_OCSWriter();

        $primary = new com_meego_package();
        $primary->get_by_id((int) $args['contentid']);

        if (! $primary->guid)
        {
            $ocs->writeError('Content not found', 101);
        }
        else
        {
            $rating = new com_meego_ratings_rating();

            $rating->to = $primary->guid;

            $vote = $_POST['vote'];

            if ($vote > $this->mvc->configuration->maxrate)
            {
                $vote = $this->mvc->configuration->maxrate;
            }

            $rating->rating = $vote;
            // for votes only we have no comments
            $rating->comment = 0;

            if (! $rating->create())
            {
                throw new midgardmvc_exception_notfound("Could not create rating object");
            }

            $ocs->writeMeta(0);

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
