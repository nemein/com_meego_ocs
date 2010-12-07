<?php
/**
 * @see http://freedesktop.org/wiki/Specifications/open-collaboration-services#Providerfiles
 */
class com_meego_ocs_controllers_providers
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    public function get_index(array $args)
    {
    }
}
