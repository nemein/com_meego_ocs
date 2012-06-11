<?php
/**
 * @see http://freedesktop.org/wiki/Specifications/open-collaboration-services#CONTENT
 */
class com_meego_ocs_controllers_comments
{
    var $mvc = null;
    var $user = null;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
        $this->user = com_meego_ocs_utils::get_current_user();
        $this->mvc = midgardmvc_core::get_instance();
    }

    /**
     * @todo: docs
     */
    public function get_comments(array $args)
    {
        if ($args['type'] != 1)
        {
            throw new midgardmvc_exception_notfound("Only CONTENT type supported");
        }

        $cnt = 0;

        $package = new com_meego_package();
        $ocs = new com_meego_ocs_OCSWriter();

        try
        {
            $package->get_by_id((int) $args['contentid1']);
        }
        catch (midgard_error_exception $e)
        {
            $error = true;
            $this->mvc->log(__CLASS__, 'Probably missing package with id:  ' . $args['contentid1'] . '.', 'warning');

            $ocs->writeError('Package not found', 101);
            $ocs->endDocument();
            self::output_xml($ocs);
        }

        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('authorguid'),
            '<>',
            new midgard_query_value('')
        ));

        $qc->add_constraint(new midgard_query_constraint (
            new midgard_query_property('name'),
            '=',
            new midgard_query_value($package->name)
        ));

        $q->set_constraint($qc);

        $q->add_order(new midgard_query_property('posted', $storage), SORT_ASC);

        // First run a query of the whole set to get results count
        $q->execute();
        $cnt = $q->get_results_count();

        list($limit, $offset) = $this->limit_and_offset_from_query();

        $q->set_limit($limit);
        $q->set_offset($offset);
        $q->execute();

        $comments = $q->list_objects();

        foreach ($comments as $comment)
        {
            if (   $comment->commentid == 0
                && ! $this->mvc->configuration->show_ratings_without_comments)
            {
                // skip the rating if it has no comment and the configuration excludes such ratings
                --$cnt;
            }
        }

        $ocs->writeMeta($cnt);
        $ocs->startElement('data');

        // todo: again this loop..  a bit redundant, but works for now
        foreach ($comments as $comment)
        {
            if (   $comment->commentid == 0
                && ! $this->mvc->configuration->show_ratings_without_comments)
            {
                // skip the rating if it has no comment and the configuration excludes such ratings
                continue;
            }
            $this->comment_to_ocs($comment, $ocs);
        }

        $ocs->endElement();
        $ocs->endDocument();

        self::output_xml($ocs);
    }

    /**
     * @todo: docs
     */
    protected function limit_and_offset_from_query()
    {
        $query = $this->request->get_query();

        $page = 0;
        if (isset($query['page']))
        {
            $page = $query['page'];
        }

        $pagesize = $this->mvc->configuration->list_pagesize;
        if (isset($query['pagesize']))
        {
            $pagesize = $query['pagesize'];
        }

        return array($pagesize, $page * $pagesize);
    }

    /**
     * @todo: docs
     */
    private function comment_to_ocs(com_meego_package_ratings $rating, com_meego_ocs_OCSWriter $ocs)
    {
        $ocs->startElement('comment');
        $ocs->writeElement('id', $rating->commentid);
        $ocs->writeElement('subject', $rating->version . ':' . $rating->title);
        $ocs->writeElement('text', $rating->comment);

        // todo: no support of subcomments yet

        $userid = '';
        $user = com_meego_packages_utils::get_user_by_person_guid($rating->authorguid);

        if ($user)
        {
            $userid = $user->login;
        }

        $ocs->writeElement('user', $userid);
        $ocs->writeElement('date', $rating->posted->format('c'));
        $ocs->writeElement('score', $rating->rating);
        $ocs->endElement();
    }

    /**
     * Process a comment post
     */
    public function post_add(array $args)
    {
        $success = true;

        if (! $this->user)
        {
            // Voting requires authentication
            $auth = com_meego_ocs_utils::authenticate($args);

            if (! $auth)
            {
                return null;
            }
        }

        $ocs = new com_meego_ocs_OCSWriter();

        if (! isset($_POST['content']))
        {
            $ocs->writeError('Content must not be empty', 101);
            $ocs->endDocument();
            self::output_xml($ocs);
            return;
        }

        if (! (isset($_POST['message'])
            || isset($_POST['subject'])))
        {
            $ocs->writeError('Message or subject must not be empty', 102);
            $ocs->endDocument();
            self::output_xml($ocs);
            return;
        }

        if (   $_POST['type'] != '1'
            && $_POST['type'] != '8')
        {
            $ocs->writeError('Content type: ' . $_POST['type'] . ' is not supported.', 104);
            $ocs->endDocument();
            self::output_xml($ocs);
            return;
        }

        $package = new com_meego_package();
        $package->get_by_id((int) $_POST['content']);

        if (! $package->guid)
        {
            $success = false;
            $this->mvc->log(__CLASS__, 'Package with id: ' . $_POST['content'] . ' not found.', 'error');
        }

        if ($success)
        {
            switch ($_POST['type'])
            {
                case 1:
                    $message = 'Rating via OCS failed. Could not create rating object for package ' . $package->name . '(id: ' . $package->id . ').';
                    $comment = new com_meego_comments_comment();

                    if (   isset($_POST['parent'])
                        && ! empty($_POST['parent']))
                    {
                        $parent = new com_meego_comments_comment();
                        $parent->get_by_id((int) $_POST['parent']);
                        if ($parent->to != $package->guid)
                        {
                            $success = false;
                            $this->mvc->log(__CLASS__, $message . ' Parent comment is not related to the content item', 'error');
                        }
                        $comment->up = $parent->id;
                    }

                    $comment->to = $package->guid;
                    $comment->content = $_POST['message'];

                    if (   isset($_POST['subject'])
                        && ! empty($_POST['subject']))
                    {
                        $comment->title = $_POST['subject'];
                    }

                    $comment->create();

                    if ($comment->guid)
                    {
                        $rating = new com_meego_ratings_rating();

                        $rating->to = $package->guid;
                        // for comments we have no votes
                        $rating->rating = 0;

                        $rating->comment = $comment->id;

                        $success = $rating->create();

                        if ($success)
                        {
                            $message = 'Rating via OCS finished. New rating object is: ' . $rating->guid . '.';
                        }
                    }
                    break;
                case 8:
                    $name = substr($_POST['message'], 0, strpos($_POST['message'], ':'));
                    $workflows = $this->mvc->configuration->workflows;

                    if (array_key_exists($name, $workflows))
                    {
                        if (is_object($package))
                        {
                            $this->mvc->component->load_library('Workflow');
                            $workflow_definition = new $workflows[$name]['provider'];
                            $values = $workflow_definition->start($package);

                            if (array_key_exists('execution', $values))
                            {
                                // get the db form and fill in the fields
                                $form = new midgardmvc_ui_forms_form($values['review_form']);

                                $transaction = new midgard_transaction();
                                $transaction->begin();

                                $instance = new midgardmvc_ui_forms_form_instance();
                                $instance->form = $form->id;
                                $instance->relatedobject = $package->guid;
                                $instance->create();

                                if (isset($instance->guid))
                                {
                                    // give values to the db fields taken from the posted values and store each of them
                                    // use the form instance ID as "form" property of the fields
                                    $posted_values = explode(',', substr($_POST['message'], strpos($_POST['message'], ':') + 1));
                                    $db_fields = midgardmvc_ui_forms_generator::list_fields($form);

                                    $i = 0;
                                    foreach ($db_fields as $dbfield)
                                    {
                                        if (! $success)
                                        {
                                            // if 1 field creation failed then end this loop as fast as possible
                                            continue;
                                        }

                                        switch ($dbfield->widget)
                                        {
                                            case 'checkbox':
                                                $holder = "booleanvalue";
                                                $value = $posted_values[$i];
                                                break;
                                            default:
                                                $options = explode(',', $dbfield->options);
                                                $value = $options[(int)$posted_values[$i]];

                                                $holder = "stringvalue";
                                        }

                                        $field_instance = new midgardmvc_ui_forms_form_instance_field();
                                        $field_instance->form = $instance->id;
                                        $field_instance->field = $dbfield->guid;
                                        $field_instance->$holder = $value;

                                        if (! $field_instance->create())
                                        {
                                            $success = false;
                                        }

                                        ++$i;
                                    }

                                    if ($success)
                                    {
                                        $message = 'QA via OCS by user ' . $this->user->login . ' for package: ' . $package->name . ' (id: ' . $package->id . ')';

                                        try
                                        {
                                            $workflow = $workflow_definition->get();
                                            $execution = new midgardmvc_helper_workflow_execution_interactive($workflow, $values['execution']);
                                        }
                                        catch (ezcWorkflowExecutionException $e)
                                        {
                                            $success = false;
                                            $this->mvc->log(__CLASS__, $message . ' failed. Workflow: ' . $values['workflow'] . ' not found. See error: ' . $e->getMessage(), 'error');
                                        }

                                        if ($success)
                                        {
                                            $args = array('review' => $instance->guid);

                                            try
                                            {
                                                $values = $workflow_definition->resume($execution->guid, $args);
                                            }
                                            catch (ezcWorkflowInvalidInputException $e)
                                            {
                                                $success = false;
                                                $this->mvc->log(__CLASS__, $message . ' failed. Maybe a quick re-submit? See error: ' . $e->getMessage(), 'error');
                                            }
                                            $transaction->commit();

                                            $this->mvc->log(__CLASS__, 'New QA form guid: ' . $instance->guid, 'info');
                                        }
                                    }
                                }

                                if (! $success)
                                {
                                    $this->mvc->log(__CLASS__, $message . ' failed. Probably a form instance or a field creation failed.', 'info');
                                    $transaction->rollback();
                                }
                            }
                        }
                    }
                    break;
            }

            if ($success)
            {
                // POST went fine
                $ocs->writeMeta(null, null, 'Posting succeded.', 'ok', 100);
                $this->mvc->log(__CLASS__, $message, 'info');

                // create activity object
                $created = null;
                switch ($_POST['type'])
                {
                    case 1:
                        $verb = 'comment';
                        $summary = 'The user commented an application via OCS.';
                        $creator = $rating->metadata->creator;
                        $created = $rating->metadata->created;
                        $target = $rating->to;
                        break;
                    case 8:
                        $verb = 'review';
                        $summary = 'The user reviewed an application via OCS.';
                        $creator = $instance->metadata->creator;
                        $created = $instance->metadata->created;
                        $target = $instance->relatedobject;
                        break;
                }
                if ($created)
                {
                    $res = midgardmvc_account_controllers_activity::create_activity($creator, $verb, $target, $summary, 'Apps', $created);
                }
                unset($created, $creator, $target);
            }
        }

        if (! $success)
        {
            $ocs->writeError('Comment posting (type: ' . $_POST['type'] . ') failed.');
            $this->mvc->log(__CLASS__, $message . ' failed.', 'info');
        }

        $ocs->endDocument();
        self::output_xml($ocs);
    }

    /**
     * @todo: docs
     */
    private static function output_xml($xml)
    {
        $mvc = midgardmvc_core::get_instance();
        $mvc->dispatcher->header('Content-type: application/xml; charset=utf-8');
        echo $xml->outputMemory(true);

        $mvc->dispatcher->end_request();
    }
}
