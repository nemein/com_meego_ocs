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
        $q->add_order(new midgard_query_property('name'), SORT_ASC);
        $q->execute();

        $allcategories = $q->list_objects();

        $ocs = new com_meego_ocs_OCSWriter();

        $ocs->writeMeta(count($allcategories), $this->pagesize);
        $ocs->writeCategories($allcategories);

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
        $q->add_order(new midgard_query_property('name'), SORT_ASC);
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

        $qc = new midgard_query_constraint_group('OR');

        // filter all hidden packages
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('packagehidden'),
            '=',
            new midgard_query_value(1)
        ));

        // filter packages by their names
        foreach ($this->mvc->configuration->sql_package_filters as $filter)
        {
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagename'),
                'LIKE',
                new midgard_query_value($filter)
            ));
            $qc->add_constraint(new midgard_query_constraint(
                new midgard_query_property('packagetitle'),
                'LIKE',
                new midgard_query_value($filter)
            ));
        }
        $q->set_constraint($qc);
        $q->execute();

        $filtered = array();

        foreach ($q->list_objects() as $package)
        {
            $filtered[] = $package->packageid;
        }

        if (count($filtered))
        {
            $constraints[] = new midgard_query_constraint(
                new midgard_query_property('packageid'),
                'NOT IN',
                new midgard_query_value($filtered)
            );
        }

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
                                      new midgard_query_property('packagecreated'),
                                      SORT_DESC);
                                  break;
                    case 'high' :
                                  $q->add_order(
                                      new midgard_query_property('statscachedratingvalue'),
                                      SORT_DESC);
                                  //sort by name too
                                  $q->add_order(
                                      new midgard_query_property('packagetitle'),
                                      SORT_ASC);
                                break;
                    case 'down' :
                                  //sort by name too
                                  $q->add_order(
                                      new midgard_query_property('statscachedratingvalue'),
                                      SORT_ASC);
                    case 'alpha':
                    default     :
                                  $q->add_order(
                                      new midgard_query_property('packagetitle'),
                                      SORT_ASC);
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
            $packageids = array();
            $localpackages = array();
            $packages = $q->list_objects();

            foreach ($packages as $package)
            {
                if (in_array($package->packageid, $packageids))
                {
                    // mimic distinct (Midgard Ratatoskr does not support it on SQL level)
                    --$total;
                    continue;
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

                // get the roles
                $package->roles = '';
                $roles = com_meego_packages_controllers_application::get_roles($package->packageguid);

                if (count($roles))
                {
                    $package->roles = serialize($roles);
                }
                // get the voters (who rated the package)
                $package->ratings = '';
                $ratings = com_meego_packages_controllers_application::prepare_ratings($package->packagename, true);

                if (count($ratings['ratings']))
                {
                    foreach ($ratings['ratings'] as $rating)
                    {
                        $ratings_[] = array(
                            'user' => $rating->user,
                            'version' => $rating->version,
                            'rate' => $rating->rating,
                            'date' => $rating->date
                        );
                    }
                    $package->ratings = serialize($ratings_);
                }

                //get history
                $args = array(
                    'os' => $package->repoos,
                    'version' => $package->repoosversion,
                    'ux' => $package->repoosux,
                    'packagename' => $package->packagename
                );

                $package->history = null;

                // set $this->data['packages']
                com_meego_packages_controllers_application::get_history($args);

                if (   is_array($this->data['packages'][$package->packagename]['all'])
                    && count($this->data['packages'][$package->packagename]['all']))
                {
                    $packagehistory = array();

                    foreach ($this->data['packages'][$package->packagename]['all'] as $item)
                    {
                        $packagehistory[$item['type']][$item['released'] . ':' . $item['version']] = $item['packageid'];
                    }

                    $package->history = serialize($packagehistory);
                }

                $localpackages[] = $package;
                $packageids[] = $package->packageid;
            }

            // write the xml content
            $ocs->writeMeta($total, $this->pagesize);
            $ocs->writeContent(array_values($localpackages));
            unset($packageids, $localpackages);
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
        $ocs = new com_meego_ocs_OCSWriter();

        // Voting requires authentication
        if (! com_meego_ocs_utils::authenticate($args))
        {
            // extend the OCS spec with a custom status code
            $this->mvc->log(__CLASS__, 'Attempt to vote by anonymous. No luck.', 'info');
            $ocs->writeError('Voting requires authentication. Please login first.', 102);
        }
        else
        {
            $primary = new com_meego_package();
            $primary->get_by_id((int) $args['contentid']);

            if (! $primary->guid)
            {
                $this->mvc->log(__CLASS__, 'Package with id:  (with id:' . $args['contentid'] . ') can not be found', 'info');
                $ocs->writeError('Content not found', 101);
            }
            else
            {
                $voted = false;
                $user = com_meego_ocs_utils::get_current_user();

                // the multiple voting is configurable, pls check the config file
                if (! $this->mvc->configuration->allow_multiple_voting)
                {
                    // if not allowed then check if the user has voted already
                    if (com_meego_ocs_utils::user_has_voted($primary->id, $user->person))
                    {
                        $this->mvc->log(__CLASS__, "$user->login has already voted for $primary->name (with id: $primary->id) and multiple votings are disabled", 'info');
                        $ocs->writeError('Multiple voting not allowed and user has already voted this object.', 103);
                    }
                }

                if (! $ocs->error)
                {
                    $rating = new com_meego_ratings_rating();
                    $rating->to = $primary->guid;
                    $vote = $_POST['vote'];

                    // incoming votes are ranging between 0 and 100
                    // our internal scale is different: 0 - 5
                    $vote = round($vote / 20);

                    if ($vote > $this->mvc->configuration->maxrate)
                    {
                        $vote = $this->mvc->configuration->maxrate;
                    }

                    $rating->rating = $vote;
                    // for votes only we have no comments
                    $rating->comment = 0;

                    if (! $rating->create())
                    {
                        $this->mvc->log(__CLASS__, 'Failed to create rating object. User: ' . $user->login . ', application: ' . $primary->name . ' (with id: ' . $primary->id . ')', 'info');
                        throw new midgardmvc_exception_notfound("Could not create rating object");
                    }

                    $args = array('to' => $rating->to);
                    com_meego_ratings_caching_controllers_rating::calculate_average($args);

                    $ocs->writeMeta(0);

                    $this->mvc->log(__CLASS__, 'Rating (' . $rating->rating . ') submitted by ' . $user->login . ' for ' . $primary->name . ' (with id: ' . $primary->id . ')', 'info');
                }
            }
        }

        $ocs->endDocument();
        self::output_xml($ocs);
    }

    private static function output_xml($xml)
    {
        midgardmvc_core::get_instance()->dispatcher->header('Content-type: application/xml; charset=utf-8');
        echo $xml->outputMemory(true);

        midgardmvc_core::get_instance()->dispatcher->end_request();
    }
}
