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
	User Authentication Method

	Two different authentication methods are supported:
	- sql:		Local users inside the application users table are used.
	- ldaponly:	LDAP is used for user AND group validation. 
*/

$config["AUTH_METHOD"] = "sql";



/*
	LDAP Database Configuration

	When authenticating against an LDAP directory, we assume the following:
	Users:	ou=User,ou=auth,dc=example,dc=com
	Groups:	ou=Group,ou=auth,dc=example,dc=com

	Note that users MUST belong to a POSIX group called "namedadmins" in order to
	get access into Named.

	A typical OpenLDAP setup will meet this, but environments such as RHDS, Novell or
	Active Directory may require code adjustments.
*/
//$config["ldap_host"]		= "auth.example.com";			// hostname of the LDAP server
//$config["ldap_port"]		= "389";				// LDAP server port
//$config["ldap_dn"]		= "ou=auth,dc=example,dc=com";		// DN to run queries under
//$config["ldap_user_dn"]	= "ou=People,dc=example,dc=com";	// optional DN to run user queries under
//$config["ldap_group_dn"]	= "ou=Group,dc=example,dc=com";		// optional DN to run group queries under
//$config["ldap_manager_user"]	= "cn=Manager,dc=example,dc=com";	// LDAP manager
//$config["ldap_manager_pwd"]	= "password";
//$config["ldap_ssl"]		= "enable";				// use TLS/SSL - enable/disable



/*
	Force debugging on for all users + scripts
	(note: debugging can be enabled on a per-user basis by an admin via the web interface)
*/
// $_SESSION["user"]["debug"] = "on";


?>
