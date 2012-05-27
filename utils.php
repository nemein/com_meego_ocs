<?php
/**
 * @package com_meego_ocs
 * @author Ferenc Szekely
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_ocs_utils
{
    /**
     * Returns the curretnly logged in user's object
     *
     * @return object midgard_user object of the current user
     */
    public static function get_current_user()
    {
        $mvc = midgardmvc_core::get_instance();
        return $mvc->authentication->get_user();
    }

    /**
     * Prepares proper login and password elements for authentication
     *
     * @return array
     */
    public function prepare_tokens($args = null)
    {
        $tokens = array('login' => '', 'password' => '');

        if (isset($_SERVER['HTTP_AUTHORIZATION']))
        {
            //prepare tokens from Basic Auth header
            $auth_params = explode(":", base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            $tokens['login'] = $auth_params[0];
            unset($auth_params[0]);
            $tokens['password'] = implode('', $auth_params);
        }
        else if (isset($_POST['login']))
        {
            $tokens['login'] = $_POST['login'];
            if (isset($_POST['password']))
            {
                $tokens['password'] = $_POST['password'];
            }
        }

        return $tokens;
    }

    /**
     * Performs authentication
     *
     * Limitation: only LDAP auth is supported properly at the moment
     *
     * @return boolean
     */
    public function authenticate()
    {
        $auth = null;

        switch (midgardmvc_core::get_instance()->configuration->ocs_authentication)
        {
            case 'LDAP':
                $tokens = self::prepare_tokens();
                $auth = self::ldap_auth($tokens);
                break;
            case 'basic':
            default:
                $auth = new midgardmvc_core_services_authentication_basic();
                $e = new Exception("Vote posting requires Basic authentication");
                $auth->handle_exception($e);
        }

        return $auth;
    }

    /**
     * Returns the currently logged in user's object
     *
     * @return object midgard_user object of the current user
     */
    public static function ldap_auth($tokens = null)
    {
        if (is_array($tokens)
            && array_key_exists('login', $tokens)
            && array_key_exists('password', $tokens))
        {
            $ldap = new com_meego_packages_services_authentication_ldap();
            $session = $ldap->create_login_session($tokens, null);
            return $session;
        }
        return null;
    }

    /**
     * Indicates if an account is valid or not
     *
     * @return bollean true: valid account; false: invalid account
     */
    public static function ldap_check($tokens = null)
    {
        $retval = null;

        if (   is_array($tokens)
            && array_key_exists('login', $tokens))
        {
            $ldap = new com_meego_packages_services_authentication_ldap();
            $retval = $ldap->ldap_check($tokens);
        }

        return $retval;
    }

    /**
     * Checks if a user has rated a certain package
     * @param integer package id
     * @param guid of the user
     *
     * @return boolean true: if user has rated, false otherwise
     */
    public function user_has_voted($application_id = null, $author_guid = null)
    {
        $retval = false;

        if (! $application_id)
        {
            // throw an exception or something..
            return null;
        }

        if (! $author_guid)
        {
            // ok, get the current user then
            $user = $this->get_current_user();

            if (! $user)
            {
                // not logged in, to bad
                return null;
            }
            $author_guid = $user->person;
        }

        // query select
        $storage = new midgard_query_storage('com_meego_package_ratings');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint_group('AND');

        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('id'),
            '=',
            new midgard_query_value($application_id)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('commentid'),
            '=',
            new midgard_query_value(0)
        ));
        $qc->add_constraint(new midgard_query_constraint(
            new midgard_query_property('authorguid'),
            '=',
            new midgard_query_value($author_guid)
        ));

        $q->set_constraint($qc);
        $q->execute();

        $ratings = $q->list_objects();

        if (count($ratings))
        {
            $retval = true;
        }

        return $retval;
    }

    /**
     * End the run with an OCS error message
     */
    public static function end_with_error($message, $status)
    {
        $ocs = new com_meego_ocs_OCSWriter();
        $ocs->writeError($message, $status);
        $ocs->endDocument();
        self::output_xml($ocs);
    }

    /**
     * @todo: docs
     */
    public static function output_xml($xml)
    {
        $mvc = midgardmvc_core::get_instance();
        $mvc->dispatcher->header('Content-type: application/xml; charset=utf-8');
        echo $xml->outputMemory(true);
        $mvc->dispatcher->end_request();
        unset($mvc);
    }
}
