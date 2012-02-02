<?php
/**
 * @see http://freedesktop.org/wiki/Specifications/open-collaboration-services#PERSON
 */
class com_meego_ocs_controllers_person
{
    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;
    }

    /**
     * Process posts
     */
    public function post_check(array $args)
    {
        $auth = null;

        $ocs = new com_meego_ocs_OCSWriter();

        if (! isset($_POST['login']))
        {
            $ocs->writeError('The login argument is mandatory', 101);
            $ocs->endDocument();
            self::output_xml($ocs);
            return;
        }

        $tokens = com_meego_ocs_utils::prepare_tokens();

        switch (midgardmvc_core::get_instance()->configuration->ocs_authentication)
        {
            case 'LDAP':
                $info = com_meego_ocs_utils::ldap_check($tokens);
                break;
            case 'basic':
            default:
                $info = new midgardmvc_core_services_authentication_basic();
                $e = new Exception("Requires HTTP Basic authentication");
                $info->handle_exception($e);
        }

        if (! $info)
        {
            $ocs->writeError('Invalid account', 102);
            $ocs->endDocument();
            self::output_xml($ocs);
            return;
        }

        $ocs->writeMeta(null, null, 'Valid account', 'ok', 100);
        $ocs->writePersonCheck($info['username'], $info['email']);

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
