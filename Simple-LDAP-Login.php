<?php
/*
Plugin Name: Simple LDAP Login
Plugin URI: http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/ 
Description:  Authenticates Wordpress usernames against LDAP.
Version: 1.3
Author: Clifton H. Griffin II
Author URI: http://clifgriffin.com
*/
require_once( WP_PLUGIN_DIR."/simple-ldap-login/adLDAP.php");
require_once( ABSPATH . WPINC . '/registration.php');
//Admin
function simpleldap_menu()
{
	include 'Simple-LDAP-Login-Admin.php';
}

function simpleldap_admin_actions()
{
    add_options_page("Simple LDAP Login", "Simple LDAP Login", 10, "simple-ldap-login", "simpleldap_menu");
}
function simpleldap_activation_hook()
{
	//Store settings
	add_option("simpleldap_account_suffix", "@mydomain.local");
	add_option("simpleldap_base_dn", "DC=mydomain,DC=local");
	add_option("simpleldap_domain_controllers", "dc01.mydomain.local");
	
	//Version 1.3
	add_option("simpleldap_directory_type", "directory_ad");
	add_option("simpleldap_login_mode", "mode_normal");
	add_option("simpleldap_group", "");
	add_option("simpleldap_account_type", "Contributor");
}
//For adLDAP
$options=array(
	"account_suffix"=>get_option("simpleldap_account_suffix"),
	"base_dn"=>get_option("simpleldap_base_dn"),
	"domain_controllers"=>explode(";",get_option("simpleldap_domain_controllers")),	
);
//For OpenLDAP
$ar_ldaphosts = explode(";",get_option("simpleldap_domain_controllers"));
$ldaphosts = ""; //string to hold each host separated by space
foreach ($ar_ldaphosts as $host)
{
	$ldaphosts .= $host." ";
}
define ('LDAP_HOST', $ldaphosts);
define ('LDAP_PORT', 389);
define ('LDAP_VERSION', 3);
define ('BASE_DN', get_option('simpleldap_base_dn'));
define ('LOGIN', 'uid');

//Add the menu
add_action('admin_menu', 'simpleldap_admin_actions');

//Redefine wp_authenticate
if ( !function_exists('wp_authenticate') ) :
function wp_authenticate($username, $password) {
	global $options;
	
	//Setup adLDAP object
	$adldap = new adLDAP($options);
	
	$username = sanitize_user($username);

	if ( '' == $username )
		return new WP_Error('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

	if ( '' == $password )
		return new WP_Error('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

	$user = get_userdatabylogin($username);
	
	if ( !$user || ($user->user_login != $username) ) 
	{
		//No user, are we supposed to create one?
		switch(get_option('simpleldap_login_mode'))
		{
			case "mode_create_all":
				switch(get_option('simpleldap_directory_type'))
				{
					case "directory_ad":
						//Active Directory create all
						if($adldap->authenticate($username,$password))
						{
							$userinfo = $adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
							//Create WP account
							$userData = array(
								'user_pass'     => $password,
								'user_login'    => $userinfo[0][samaccountname][0],
								'user_nicename' => $userinfo[0][givenname][0] .' '.$userinfo[0][sn][0],
								'user_email'    => $userinfo[0][mail][0],
								'display_name'  => $userinfo[0][givenname][0] .' '.$userinfo[0][sn][0],
								'first_name'    => $userinfo[0][givenname][0],
								'last_name'     => $userinfo[0][sn][0],
								'role'			=> get_option('simpleldap_account_type')
								);
							print_r($userData);
							wp_insert_user($userData);
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
					case "directory_ol":
						//OpenLDAP create all 
						$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
							or die("Can't connect to LDAP server.");
						ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
						$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
						if ($ldapbind == true) 
						{
							//Seems to authenticate
							//We already bound, foo' so we're going to try a search
							$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail'));
							$ldapuser = ldap_get_entries($ldap, $result);
					
							if ($ldapuser['count'] == 1) {
								//Create user using wp standard include
								$userData = array(
									'user_pass'     => $password,
									'user_login'    => $ldapuser[0][LOGIN][0],
									'user_nicename' => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
									'user_email'    => $ldapuser[0]['mail'][0],
									'display_name'  => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
									'first_name'    => $ldapuser[0]['givenname'][0],
									'last_name'     => $ldapuser[0]['sn'][0]
									);
								wp_insert_user($userData);
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
				}
				break;
			case "mode_create_group":
				switch(get_option('simpleldap_directory_type'))
				{
					case "directory_ad":
						//Active Directory create group
						if($adldap->authenticate($username,$password))
						{
							if($adldap->user_ingroup($username,get_option('simpleldap_group')))
							{
								$userinfo = $adldap->user_info($username, array("samaccountname","givenname","sn","mail"));
								//Create WP account
								$userData = array(
									'user_pass'     => $password,
									'user_login'    => $userinfo[0][samaccountname][0],
									'user_nicename' => $userinfo[0][givenname][0].' '.$userinfo[0][sn][0],
									'user_email'    => $userinfo[0][mail][0],
									'display_name'  => $userinfo[0][givenname][0].' '.$userinfo[0][sn][0],
									'first_name'    => $userinfo[0][givenname][0],
									'last_name'     => $userinfo[0][sn][0],
									'role'			=> get_option('simpleldap_account_type')
								);
								wp_insert_user($userData);
							}
							else
							{
								//User authenticated, but isn't in group!
								do_action( 'wp_login_failed', $username );				
								return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation, the LDAP credentials provided are correct, but the user is not in an allowed group.'));
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
						}
						break;
					case "directory_ol":
						//OpenLDAP create group
						$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
							or die("Can't connect to LDAP server.");
						ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
						$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
						if ($ldapbind == true) 
						{
							//Seems to authenticate
							//We already bound, foo' so we're going to try a search for the user
							$result = ldap_search($ldap, BASE_DN, '(' . LOGIN . '=' . $username . ')', array(LOGIN, 'sn', 'givenname', 'mail', 'memberof'));
							$ldapuser = ldap_get_entries($ldap, $result);
						
							if ($ldapuser['count'] == 1) 
							{
								//Ok, we should have the user, all the info, including which groups he is a member of. 
								//Now let's make sure he's in the right group before proceeding.
								$groups = array();
								foreach($ldapuser[0][memberof][0] as $group)
								{
									$temp = substr($group, 0, stripos($group, ","));
								 	// Strip the CN= and change to lowercase for easy handling
								  	$temp = strtolower(str_replace("CN=", "", $temp));
								  	$groups[] .= $temp;
								}	
								if(in_array(get_option('simpleldap_group'),$groups))
								{						
									//Create user using wp standard include
									$userData = array(
										'user_pass'     => $password,
										'user_login'    => $ldapuser[0][LOGIN][0],
										'user_nicename' => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
										'user_email'    => $ldapuser[0]['mail'][0],
										'display_name'  => $ldapuser[0]['givenname'][0].' '.$ldapuser[0]['sn'][0],
										'first_name'    => $ldapuser[0]['givenname'][0],
										'last_name'     => $ldapuser[0]['sn'][0]
										);
									
									wp_insert_user($userData);
								}
								else
								{
									do_action( 'wp_login_failed', $username );				
									return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation, the LDAP credentials provided are correct, but the user is not in an allowed group.'));
								}
							}
							else
							{
								do_action( 'wp_login_failed', $username );				
								return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode allows account creation but the LDAP credentials provided are incorrect.'));
							}
						}
						else
						{
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Simple LDAP Login could not bind to OpenLDAP.'));
						}
						break;
				}
				break;
			default:
				do_action( 'wp_login_failed', $username );				
				return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username. Simple LDAP Login mode does not permit account creation.'));
		}
	}


	$user = get_userdatabylogin($username);
	
	if ( !$user || ($user->user_login != $username) ) {	
	
		do_action( 'wp_login_failed', $username );
		return new WP_Error('invalid_username', __('<strong>ERROR</strong>: Invalid username.'));
	}

	$user = apply_filters('wp_authenticate_user', $user, $password);
	if ( is_wp_error($user) ) {
		do_action( 'wp_login_failed', $username );
		return $user;
	}
	
	if ( !wp_check_password($password, $user->user_pass, $user->ID) ) {
		switch(get_option("simpleldap_directory_type"))
		{
			case "directory_ad":
				if ($adldap -> authenticate($user->user_login,$password)){
					return new WP_User($user->ID);
				}
				break;
			case "directory_ol":
				//OpenLDAP create group
				$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
					or die("Can't connect to LDAP server.");
				ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
				$ldapbind = @ldap_bind($ldap, LOGIN .'=' . $username . ',' . BASE_DN, $password);
				if ($ldapbind == true) 
				{
					return new WP_User($user->ID);
				}
				break;		
		}
		do_action( 'wp_login_failed', $username );
		return new WP_Error('incorrect_password', __('<strong>ERROR</strong>: Incorrect password.'));
	}

	return new WP_User($user->ID);
}
endif;
register_activation_hook( __FILE__, 'simpleldap_activation_hook' );
?>
