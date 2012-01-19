<?php
/**
 * @package com_meego_ocs
 * @author Ferenc Szekely
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
class com_meego_ocs_utils
{
    public function authenticate($args = null)
    {
        $auth = null;

        if (! is_array($args))
        {
            return null;
        }

        switch (midgardmvc_core::get_instance()->configuration->ocs_authentication)
        {
            case 'LDAP':
                $tokens = array('login' => '', 'password' => '');
                //prepare tokens from Basic Auth header
                $auth_params = explode(":", base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
                $tokens['login'] = $auth_params[0];
                unset($auth_params[0]);
                $tokens['password'] = implode('', $auth_params);
                $auth = com_meego_ocs_utils::ldap_auth($tokens);
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
     * Returns the curretnly logged in user's object
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
            return $ldap->create_login_session($tokens, null);
        }
        return null;
    }
}
