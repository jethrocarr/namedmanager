<?php
/*
	NamedManager Application Libraries

	Provides functions for NamedManager.
*/


@log_debug("start", "");
@log_debug("start", "NAMEDMANAGER LIBRARIES LOADED");
@log_debug("start", "");


// include main code functions
require("inc_changelog.php");
require("inc_domain.php");
require("inc_servers.php");
require("inc_server_groups.php");

// cloud providers
require("inc_cloud_route53.php");

?>
