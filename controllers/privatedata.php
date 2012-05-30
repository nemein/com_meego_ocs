<?php
/**
 * @see http://www.freedesktop.org/wiki/Specifications/open-collaboration-services#PRIVATE_DATA
 */
class com_meego_ocs_controllers_privatedata
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
     * Process an attribute setting POST
     * args['context'] might be: installed
     * args['key'] holds the package ID
     * @param args see above
     */
    public function post_setattribute(array $args)
    {
        $summary = '';
        $success = true;

        if (! $this->user)
        {
            // this operation requires authentication
            $auth = com_meego_ocs_utils::authenticate($args);
            if (! $auth)
            {
                com_meego_ocs_utils::end_with_error('This interface is available for authenticated users only', 199);
            }
        }

        if (! isset($args['context']))
        {
            com_meego_ocs_utils::end_with_error('Mandatory context missing (e.g. installed)', 102);
        }

        if (! isset($args['key']))
        {
            com_meego_ocs_utils::end_with_error('Mandatory package ID missing', 103);
        }

        // check if the context is supported
        switch ($args['context'])
        {
            case 'save':
                $summary = 'User succesfully installed an application.';
                break;
            case 'unsave':
                $summary = 'User succesfully uninstalled an application.';
                break;
            case 'savefail':
                $summary = 'Application installation failed.';
                break;
            default:
                com_meego_ocs_utils::end_with_error('This context: ' . $args['context'] . ' is not supported', 104);
        }

        $package = new com_meego_package();

        try
        {
            $package->get_by_id((int) $args['key']);
        }
        catch(Exception $e)
        {
            $success = false;
            com_meego_ocs_utils::end_with_error('Package with id: ' . $args['key'] . ' not found', 105);
        }

        if ($success)
        {
            $person = new midgard_person($this->user->person);

            // create new activity object
            $activity = new midgard_activity();
            $activity->actor = $person->id;
            $activity->verb = $args['context'];
            $activity->target = $package->guid;
            $activity->summary = $summary;
            $activity->application = 'Apps';

            $res = $activity->create();
            if (! $res)
            {
                com_meego_ocs_utils::end_with_error('Failed to create activity object.', 106);
            }
            unset($person);
        }

        // everything went fine
        $ocs = new com_meego_ocs_OCSWriter();
        $ocs->writeMeta(null, null, 'Attribute setting succeded.', 'ok', 100);
        $ocs->endDocument();
        com_meego_ocs_utils::output_xml($ocs);
    }
}