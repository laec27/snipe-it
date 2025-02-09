<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Setting;
use Exception;
use Input;
use Log;

class Ldap extends Model
{

    /**
     * Makes a connection to LDAP using the settings in Admin > Settings.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return connection
     */

    public static function connectToLdap()
    {

        $ldap_host    = Setting::getSettings()->ldap_server;
        $ldap_version = Setting::getSettings()->ldap_version;
        $ldap_server_cert_ignore = Setting::getSettings()->ldap_server_cert_ignore;
        $ldap_use_tls = Setting::getSettings()->ldap_tls;

        // If we are ignoring the SSL cert we need to setup the environment variable
        // before we create the connection
        if ($ldap_server_cert_ignore=='1') {
            putenv('LDAPTLS_REQCERT=never');
        }

        // If the user specifies where CA Certs are, make sure to use them
        if (env("LDAPTLS_CACERT")) {
            putenv("LDAPTLS_CACERT=".env("LDAPTLS_CACERT"));
        }

        $connection = @ldap_connect($ldap_host);

        if (!$connection) {
            throw new Exception('Could not connect to LDAP server at '.$ldap_host.'. Please check your LDAP server name and port number in your settings.');
        }

        // Needed for AD
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $ldap_version);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 20);

        if (Setting::getSettings()->ldap_client_tls_cert && Setting::getSettings()->ldap_client_tls_key) {
            ldap_set_option($connection, LDAP_OPT_X_TLS_CERTFILE, Setting::get_client_side_cert_path());
            ldap_set_option($connection, LDAP_OPT_X_TLS_KEYFILE, Setting::get_client_side_key_path());
        }

        if ($ldap_use_tls=='1') {
            ldap_start_tls($connection);
        }


        return $connection;
    }


    /**
     * Binds/authenticates the user to LDAP, and returns their attributes.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $username
     * @param $password
     * @param bool|false $user
     * @return bool true    if the username and/or password provided are valid
     *              false   if the username and/or password provided are invalid
     *         array of ldap_attributes if $user is true
     *
     */
    static function findAndBindUserLdap($username, $password)
    {
        $settings = Setting::getSettings();
        $connection = Ldap::connectToLdap();
        $ldap_username_field     = $settings->ldap_username_field;
        $baseDn      = $settings->ldap_basedn;
        $userDn      = $ldap_username_field.'='.$username.','.$settings->ldap_basedn;

        if ($settings->is_ad =='1') {
            // Check if they are using the userprincipalname for the username field.
            // If they are, we can skip building the UPN to authenticate against AD
            if ($ldap_username_field=='userprincipalname') {
                $userDn = $username;
            } else {
                // In case they haven't added an AD domain
                    $userDn  = ($settings->ad_domain != '') ?  $username.'@'.$settings->ad_domain : $username.'@'.$settings->email_domain;
            }

        }

        \Log::debug('Attempting to login using distinguished name:'.$userDn);

        
        $filterQuery = $settings->ldap_auth_filter_query . $username;
        $filter = Setting::getSettings()->ldap_filter;
        $filterQuery = "({$filter}({$filterQuery}))";

        \Log::debug('Filter query: '.$filterQuery);


        if (!$ldapbind = @ldap_bind($connection, $userDn, $password)) {
            if(!$ldapbind = Ldap::bindAdminToLdap($connection)){
                    return false;
            }
        }

        if (!$results = ldap_search($connection, $baseDn, $filterQuery)) {
            throw new Exception('Could not search LDAP: ');
        }

        if (!$entry = ldap_first_entry($connection, $results)) {
            return false;
        }

        if (!$user =  ldap_get_attributes($connection, $entry)) {
            return false;
        }

        return array_change_key_case($user);

    }


    /**
     * Binds/authenticates an admin to LDAP for LDAP searching/syncing.
     * Here we also return a better error if the app key is donked.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param bool|false $user
     * @return bool true    if the username and/or password provided are valid
     *              false   if the username and/or password provided are invalid
     *
     */
    static function bindAdminToLdap($connection)
    {

        $ldap_username     = Setting::getSettings()->ldap_uname;

        // Lets return some nicer messages for users who donked their app key, and disable LDAP
        try {
            $ldap_pass    = \Crypt::decrypt(Setting::getSettings()->ldap_pword);
        } catch (Exception $e) {

            throw new Exception('Your app key has changed! Could not decrypt LDAP password using your current app key, so LDAP authentication has been disabled. Login with a local account, update the LDAP password and re-enable it in Admin > Settings.');
        }


        if (!$ldapbind = @ldap_bind($connection, $ldap_username, $ldap_pass)) {
            throw new Exception('Could not bind to LDAP: '.ldap_error($connection));
        }

    }


    /**
     * Parse and map LDAP attributes based on settings
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     *
     * @param $ldapatttibutes
     * @return array|bool
     */
    static function parseAndMapLdapAttributes($ldapattributes)
    {
        //Get LDAP attribute config
        $ldap_result_username = Setting::getSettings()->ldap_username_field;
        $ldap_result_emp_num = Setting::getSettings()->ldap_emp_num;
        $ldap_result_last_name = Setting::getSettings()->ldap_lname_field;
        $ldap_result_first_name = Setting::getSettings()->ldap_fname_field;
        $ldap_result_email = Setting::getSettings()->ldap_email;
        $ldap_result_phone = Setting::getSettings()->ldap_phone;
        $ldap_result_jobtitle = Setting::getSettings()->ldap_jobtitle;
        $ldap_result_country      = Setting::getSettings()->ldap_country;
        $ldap_result_dept = Setting::getSettings()->ldap_dept;
        // Get LDAP user data
        $item = array();
        $item["username"] = isset($ldapattributes[$ldap_result_username][0]) ? $ldapattributes[$ldap_result_username][0] : "";
        $item["employee_number"] = isset($ldapattributes[$ldap_result_emp_num][0]) ? $ldapattributes[$ldap_result_emp_num][0] : "";
        $item["lastname"] = isset($ldapattributes[$ldap_result_last_name][0]) ? $ldapattributes[$ldap_result_last_name][0] : "";
        $item["firstname"] = isset($ldapattributes[$ldap_result_first_name][0]) ? $ldapattributes[$ldap_result_first_name][0] : "";
        $item["email"] = isset($ldapattributes[$ldap_result_email][0]) ? $ldapattributes[$ldap_result_email][0] : "" ;
        $item["telephone"] = isset($ldapattributes[$ldap_result_phone][0]) ?$ldapattributes[$ldap_result_phone][0] : "";
        $item["jobtitle"] = isset($ldapattributes[$ldap_result_jobtitle][0]) ? $ldapattributes[$ldap_result_jobtitle][0] : "";
        $item["country"] = isset($ldapattributes[$ldap_result_country][0]) ? $ldapattributes[$ldap_result_country][0] : "";
        $item["department"] = isset($ldapattributes[$ldap_result_dept][0]) ? $ldapattributes[$ldap_result_dept][0] : "";
        return $item;


    }

    /**
     * Create user from LDAP attributes
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $ldapatttibutes
     * @return array|bool
     */
    static function createUserFromLdap($ldapatttibutes)
    {
        $item = Ldap::parseAndMapLdapAttributes($ldapatttibutes);


        // Create user from LDAP data
        if (!empty($item["username"])) {
            $user = new User;
            $user->first_name = $item["firstname"];
            $user->last_name = $item["lastname"];
            $user->username = $item["username"];
            $user->email = $item["email"];

            if (Setting::getSettings()->ldap_pw_sync=='1') {
                $user->password = bcrypt(Input::get("password"));
            } else {
                $pass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 25);
                $user->password = bcrypt($pass);
            }

            $user->activated = 1;
            $user->ldap_import = 1;
            $user->notes = 'Imported on first login from LDAP';

            if ($user->save()) {
                return $user;
            } else {
                LOG::debug('Could not create user.'.$user->getErrors());
                throw new Exception("Could not create user: ".$user->getErrors());
            }
        }

        return false;

    }

    /**
     * Searches LDAP
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @param $ldapatttibutes
     * @param $base_dn
     * @return array|bool
     */
    static function findLdapUsers($base_dn = null)
    {

        $ldapconn = Ldap::connectToLdap();
        $ldap_bind = Ldap::bindAdminToLdap($ldapconn);
        // Default to global base DN if nothing else is provided.
        if (is_null($base_dn)) {
            $base_dn = Setting::getSettings()->ldap_basedn;
        }
        $filter = Setting::getSettings()->ldap_filter;

        // Set up LDAP pagination for very large databases
        $page_size = 500;
        $cookie = '';
        $result_set = array();
        $global_count = 0;

        // Perform the search
        do {

            // Paginate (non-critical, if not supported by server)
            if (!$ldap_paging = @ldap_control_paged_result($ldapconn, $page_size, false, $cookie)) {
                throw new Exception('Problem with your LDAP connection. Try checking the Use TLS setting in Admin > Settings. ');
            }

            if ($filter != '' && substr($filter, 0, 1) != '(') { // wrap parens around NON-EMPTY filters that DON'T have them, for back-compatibility with AdLdap2-based filters
                $filter = "($filter)";
            } elseif ($filter == '') {
                $filter = "(cn=*)";
            }


            $search_results = ldap_search($ldapconn, $base_dn, $filter);

            if (!$search_results) {
                return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_search').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
            }

            // Get results from page
            $results = ldap_get_entries($ldapconn, $search_results);
            if (!$results) {
                return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_get_entries').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
            }

            // Add results to result set
            $global_count += $results['count'];
            $result_set = array_merge($result_set, $results);

            @ldap_control_paged_result_response($ldapconn, $search_results, $cookie);

        } while ($cookie !== null && $cookie != '');


        // Clean up after search
        $result_set['count'] = $global_count;
        $results = $result_set;
        @ldap_control_paged_result($ldapconn, 0);

        return $results;


    }

    static function findLdapUsersGroups($base_dn = null)
    {

        $ldapconn = Ldap::connectToLdap();
        $ldap_bind = Ldap::bindAdminToLdap($ldapconn);
        
        $base_dn = Setting::getSettings()->ldap_grp_basedn;
        
        $filter = Setting::getSettings()->ldap_grp_filter;

        // Set up LDAP pagination for very large databases
        $page_size = 500;
        $cookie = '';
        $result_set = array();
        $global_count = 0;

        // Perform the search
        do {
            Log::debug("in do");
            // Paginate (non-critical, if not supported by server)
            if (!$ldap_paging = @ldap_control_paged_result($ldapconn, $page_size, false, $cookie)) {
                throw new Exception('Problem with your LDAP connection. Try checking the Use TLS setting in Admin > Settings. ');
            }

            if ($filter != '' && substr($filter, 0, 1) != '(') { // wrap parens around NON-EMPTY filters that DON'T have them, for back-compatibility with AdLdap2-based filters
                $filter = "($filter)";
            } elseif ($filter == '') {
                $filter = "(cn=*)";
            }


            $search_results = ldap_search($ldapconn, $base_dn, $filter);
            

            if (!$search_results) {
                return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_search').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
            }

            // Get results from page
            $results = ldap_get_entries($ldapconn, $search_results);
           
            if (!$results) {
                return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_get_entries').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
            }

            // Add results to result set
            $global_count += $results['count'];
            $result_set = array_merge($result_set, $results);

            @ldap_control_paged_result_response($ldapconn, $search_results, $cookie);

        } while ($cookie !== null && $cookie != '');

        // Clean up after search
        $result_set['count'] = $global_count;
        $results = $result_set;
        @ldap_control_paged_result($ldapconn, 0);

        return $results;


    }

    static function findLdapUsersGroupsMembers($cn = null)
    {

        $ldapconn = Ldap::connectToLdap();
        $ldap_bind = Ldap::bindAdminToLdap($ldapconn);

        $base_dn = Setting::getSettings()->ldap_grp_basedn;

        $filter = "&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=".$cn.",".Setting::getSettings()->ldap_grp_basedn.")";

       // Set up LDAP pagination for very large databases
       $page_size = 500;
       $cookie = '';
       $result_set = array();
       $global_count = 0;

       // Perform the search
       do {
           // Paginate (non-critical, if not supported by server)
           if (!$ldap_paging = @ldap_control_paged_result($ldapconn, $page_size, false, $cookie)) {
               throw new Exception('Problem with your LDAP connection. Try checking the Use TLS setting in Admin > Settings. ');
           }

           if ($filter != '' && substr($filter, 0, 1) != '(') { // wrap parens around NON-EMPTY filters that DON'T have them, for back-compatibility with AdLdap2-based filters
               $filter = "($filter)";
           }

           Log::debug('soon search perform');
           $search_results = ldap_search($ldapconn, $base_dn, $filter);
           Log::debug('result: ');
           
           if (!$search_results) {
               return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_search').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
           }

           // Get results from page
           $results = ldap_get_entries($ldapconn, $search_results);
           Log::debug($results);

           if (!$results) {
               return redirect()->route('users.index')->with('error', trans('admin/users/message.error.ldap_could_not_get_entries').ldap_error($ldapconn)); // FIXME this is never called in any routed context - only from the Artisan command. So this redirect will never work.
           }

           // Add results to result set
           $global_count += $results['count'];
           $result_set = array_merge($result_set, $results);

           @ldap_control_paged_result_response($ldapconn, $search_results, $cookie);

       } while ($cookie !== null && $cookie != '');

       // Clean up after search
       $result_set['count'] = $global_count;
       $results = $result_set;
       @ldap_control_paged_result($ldapconn, 0);

       return $results;

    }
}
