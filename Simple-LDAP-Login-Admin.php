<html>
<head>
<style>
div.container{
	width: 950px;
}
div.simpleldap_style{
	padding: 5px;
	background: #EBEBEB;
	margin: 10px;
	width:450px;
	height:345px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left; 
}
div.simpleldap_style_test{
	padding: 5px;
	margin: 0px 10px 10px 10px;
	background: #EBEBEB;
	width: 450px;
	height: 280px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left;
}
div.information_pane{
	height:280px;
	width: 450px;
	padding: 5px;
	background: #EBEBEB;
	margin: 0px 10px 10px 0;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float:left;
}
div.advanced{
	padding: 5px;
	background: #EBEBEB;
	margin: 10px 10px 10px 0px;
	width:450px;
	height:345px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left; 
}
div.banner{
	padding 5px;
	margin-left: 10px;
	margin-top: 15px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
}
h1{
	margin: 0;
}
h2{
	margin: 0;
}
h3{
	margin: 0;
}
h4{
	margin: 0;
}
</style>
</head>
<?php 
//Debug
$debug = "false";

//Load settings, etc
$simpleldap_account_suffix = get_option("simpleldap_account_suffix");
$simpleldap_base_dn = get_option("simpleldap_base_dn");
$simpleldap_domain_controllers = get_option("simpleldap_domain_controllers");

//Version 1.3
$simpleldap_directory_type = 	get_option("simpleldap_directory_type");
$simpleldap_login_mode = 	get_option("simpleldap_login_mode");
$simpleldap_group = 	get_option("simpleldap_group");
$simpleldap_account_type = 	get_option("simpleldap_account_type");

//Where are we?
$this_page = $_SERVER['PHP_SELF'].'?page='.$_GET['page'];

//If this is a test, we will use this variable
$bool_test = 0;

//If admin options updated (uses hidden field)
if ('process' == $_POST['stage']) 
{
    update_option('simpleldap_account_suffix', $_POST['account_suffix']);
	update_option('simpleldap_base_dn', $_POST['base_dn']);
	update_option('simpleldap_domain_controllers', $_POST['domain_controller']);

	//Version 1.3
	update_option('simpleldap_directory_type',$_POST['LDAP']);
	update_option('simpleldap_login_mode',$_POST['mode']);
	update_option('simpleldap_group',$_POST['group_name']);
	update_option('simpleldap_account_type',$_POST['create_type']);
	
	$simpleldap_account_suffix = get_option("simpleldap_account_suffix");
	$simpledap_base_dn = get_option("simpleldap_base_dn");
	$simpleldap_domain_controllers = get_option("simpleldap_domain_controllers");	
	
	//Version 1.3
	$simpleldap_directory_type = 	get_option("simpleldap_directory_type");
	$simpleldap_login_mode = 	get_option("simpleldap_login_mode");
	$simpleldap_group = 	get_option("simpleldap_group");
	$simpleldap_account_type = 	get_option("simpleldap_account_type");
}
elseif ('test' == $_POST['stage']) 
{
	global $bool_test;
	global $test_error;
	require_once( WP_PLUGIN_DIR."/simple-ldap-login/adLDAP.php");
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

	if(get_option("simpleldap_directory_type") == "directory_ad")
	{
		$adldap = new adLDAP($options);
		if ($adldap -> authenticate($_POST['test_username'],$_POST['test_password']))
		{
			$bool_test = 1;
		}
		else 
		{
			$bool_test = 2;
		}
	}
	else //assume OpenLDAP
	{
		$ldap = ldap_connect(LDAP_HOST, LDAP_PORT) 
					or die("Can't connect to LDAP server.");
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, LDAP_VERSION);
		$ldapbind = @ldap_bind($ldap, LOGIN .'=' .$_POST['test_username']. ',' . BASE_DN, $_POST['test_password']);
		if ($ldapbind == true) 
		{
			$bool_test = 1;
		}
		else
		{
			$bool_test = 2;
		}
	}
}
?>
<body>
<div class="container">
<div class="banner"><h1>Simple LDAP Login 1.3 Beta</h1></div>
<form style="display::inline;" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&updated=true">
<div class="simpleldap_style">
<h2>Settings</h2>
<h3>These are rather important.</h3>
  <p><strong>Account Suffix:</strong><br />
<input name="account_suffix" type="text" value="<?php  echo $simpleldap_account_suffix; ?>" size="35" /><br />
*Probably the domain portion of your e-mail addresses. Example: @domain.com
 </p><p><strong>Base DN:</strong><br />
<input name="base_dn" type="text" value="<?php  echo $simpleldap_base_dn; ?>" size="35" /><br />
*Example: For subdomain.domain.sufix  you would be use DC=subdomain,DC=domain,DC=suffix 
  </p>
 <p><strong>Domain Controller(s):</strong><br />
<input name="domain_controller" type="text" value="<?php  echo $simpleldap_domain_controllers; ?>" size="60" /><br />
*Separate with semi-colons. Example: dc1.domain.com;dc2.dcomain.com;dc3.domain.com
  </p>
<input type="hidden" name="stage" value="process" />
<input type="submit" name="button_submit" value="<?php _e('Update Options', 'simple-ldap-login') ?> &raquo;" />

</div>
<div class="advanced">
<h2>Advanced</h2>
<h3>For the intrepid and daring among you.</h3>
<p><strong>LDAP Directory:</strong><br/>
<input name="LDAP" type="radio" value="directory_ad" onClick="enable('advanced');" <?php if($simpleldap_directory_type=="directory_ad"){echo "checked";}?>> <label for="directory_ad">Active Directory. (default)</label><br/>
<input name="LDAP" type="radio" value="directory_ol" onClick="disable('advanced');"<?php if($simpleldap_directory_type=="directory_ol"){echo "checked";}?>> <label for="directory_ol">OpenLDAP (BETA, untested)</label><br/>
</p>
<p style="margin-bottom:0px;"><strong>Login mode:</strong><br>
<em>OpenLDAP only allows first two login models. Group membership is not currently supported.</em><br/>
<input name="mode" type="radio" value="mode_normal" <?php if($simpleldap_login_mode=="mode_normal"){echo "checked";}?> > <label for="mode_normal">Authenticate Wordpress users against LDAP. I will create the accounts in wordpress myself. (default)</label><br/>
<input name="mode" type="radio" value="mode_create_all" <?php if($simpleldap_login_mode=="mode_create_all"){echo "checked";}?> > <label for="mode_create_all">Create Wordpress account for anyone who successfully authenticates against LDAP.</label><br/>
<input name="mode" type="radio" value="mode_create_group" <?php if($simpleldap_login_mode=="mode_create_group"){echo "checked";}?>> <label for="mode_create_group">Create Wordpress account for users in specified AD group:</label> <input name="group_name" type="text" value="<?php  echo $simpleldap_group; ?>" size="12"/></p>
<p style="margin-left:15px; margin-top:0px;"><strong>For latter two options, create account as:</strong><br/>
<select name="create_type">
<option value="Administrator" <?php if($simpleldap_account_type=="Administrator"){echo 'selected="selected"';}?> >Administrator</option>
<option value="Editor" <?php if($simpleldap_account_type=="Editor"){echo 'selected="selected"';}?> >Editor</option>
<option value="Author" <?php if($simpleldap_account_type=="Author"){echo 'selected="selected"';}?> >Author</option>
<option value="Contributor" <?php if($simpleldap_account_type=="Contributor"){echo 'selected="selected"';}?> >Contributor</option>
<option value="Subscriber" <?php if($simpleldap_account_type=="Subscriber"){echo 'selected="selected"';}?> >Subscriber</option>
</select>

</p>
</div>
</form>
<div class="simpleldap_style_test">
<h2>Test Settings</h2>
<h3>Use this form to test those settings you saved.* Does <em>not</em> test user creation or group membership.</h3>
<h4>*You did save them, right?</h4>
<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
  <p>Username:<br />
<input name="test_username" type="text" size="35" />
 </p><p>Password:<br />
<input name="test_password" type="password" size="35" />
  </p>
<input type="hidden" name="stage" value="test" />
<input type="submit" name="button_submit" value="<?php _e('Test Settings', 'simple-ldap-login') ?> &raquo;" />
</form>
<p>
<h4>Test Results:</h4>
<?php
if($bool_test == 0)
{
	echo "Nothing to report yet, Commander Nelson.";
}
if($bool_test == 1)
{
	echo "Congratulations! The test succeeded. Your settings are correct, it seems.";
}
elseif($bool_test == 2)
{
	echo "Failure. Your settings do not seem to work yet.";
}
?>
</p>
</div>
<div class="information_pane">
<? echo file_get_contents('http://clifgriffin.com/plugins/simple-ldap-login/news.htm'); ?>
</div>
</div>
<?php
//Echo settings
if($debug == "true")
{
echo "<p style=\"clear:both;\">Debug Info:<br/>";
echo "simpleldap_directory_type: ".get_option("simpleldap_directory_type")."/".$_POST['LDAP']."<br/>";
echo "simpleldap_login_mode: ".get_option("simpleldap_login_mode")."/".$_POST['mode']."<br/>";
echo "simpleldap_group: ".get_option("simpleldap_group")."/".$_POST['group_name']."<br/>";
echo "simpleldap_account_type: ".get_option("simpleldap_account_type")."/".$_POST['create_type']."<br/></p>";
}
?>
</body>
</html>