<?php
/*
	Summary/Welcome page for NamedManager
*/

if (!user_online())
{
	// Because this is the default page to be directed to, if the user is not
	// logged in, they should go straight to the login page.
	//
	// All other pages will display an error and prompt the user to login.
	//
	include_once("user/login.php");
}
else
{
	class page_output
	{
		function check_permissions()
		{
			// only allow namedadmins group members to have access
			if (user_permissions_get("namedadmins"))
			{
				return 1;
			}
			else
			{
				log_write("error", "page_output", "You do not have permissions to access this interface, request your administrator to assign you to the namedadmins group");
				return 0;
			}
		}


		function check_requirements()
		{
			// nothing todo
			return 1;
		}
			
		function execute()
		{
			// nothing todo
			return 1;
		}

		function render_html()
		{
			print "<h3>OVERVIEW</h3>";
			//print "<p>Welcome to <a target=\"new\" href=\"http://www.amberdms.com/namedmanager\">LDAPAuthManager</a>, an open-source, PHP web-based LDAP authentication management interface designed to make it easy to manage users running on centralised authentication environments.</p>";
			print "<p>Welcome to NamedManager, a PHP web-based DNS management interface for managing DNS zones.</p>";


		}
	}
}

?>
