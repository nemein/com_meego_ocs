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
        $this->data['api_url'] = $this->generate_url('/ocs/v1');
    }

    public function generate_url($path)
    {
        $protocol = midgardmvc_core::get_instance()->configuration->ocs_protocol;
        $host = midgardmvc_core::get_instance()->configuration->ocs_host;
        $port = midgardmvc_core::get_instance()->configuration->ocs_port;

        if (   $port != 80
            && $port != 443)
        {
            $host = "{$host}:{$port}";
        }

        return "{$protocol}://{$host}{$path}";
    }
}
