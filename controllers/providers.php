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
        $protocol = 'http';
        if ($_SERVER['SERVER_PORT'] == 443)
        {
            $protocol = 'https';
        }

        $host = $_SERVER['SERVER_NAME'];
        if (   $_SERVER['SERVER_PORT'] != 80
            && $_SERVER['SERVER_PORT'] != 443)
        {
            $host = "{$host}:{$_SERVER['SERVER_PORT']}";
        }

        return "{$protocol}://{$host}{$path}";
    }
}
