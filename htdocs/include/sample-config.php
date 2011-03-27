<?php
/*
	NamedManager Sample Configuration File

	This file provides the core configuration options such as database logins and debug
	options. For further control, the configuration page after login offers additional
	options and features.

	This file should be read-only by the httpd user. All other users should be denied.
*/



/*
	Database Configuration
*/
$config["db_host"] = "localhost";			// hostname of the MySQL server
$config["db_name"] = "myapp";				// database name
$config["db_user"] = "root";				// MySQL user
$config["db_pass"] = "";				// MySQL password (if any)


/*
	LDAP Database Configuration
*/
$config["ldap_host"]		= "auth.example.com";			// hostname of the LDAP server
$config["ldap_port"]		= "389";				// LDAP server port
$config["ldap_dn"]		= "ou=auth,dc=example,dc=com";		// DN to run queries under
$config["ldap_manager_user"]	= "cn=Manager,dc=example,dc=com";	// LDAP manager
$config["ldap_manager_pwd"]	= "password";
$config["ldap_ssl"]		= "enable";				// use TLS/SSL - enable/disable



/*
	Force debugging on for all users + scripts
	(note: debugging can be enabled on a per-user basis by an admin via the web interface)
*/
// $_SESSION["user"]["debug"] = "on";


?>
