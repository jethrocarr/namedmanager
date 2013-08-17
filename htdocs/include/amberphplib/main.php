<?php
/*
	AMBERPHPLIB

	(c) Copyright 2009 Amberdms Ltd

	www.amberdms.com

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License version 3
	only as published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

	If you wish to license any components of this program under a different
	license, please contact sales@amberdms.com for commercial licencing options.
*/




/*
	CORE FUNCTIONS

	These functions are required for basic operation by all major components of
	AMBERPHPLIB, so we define them first.
*/
function log_debug($category, $content)
{
	return log_write("debug", $category, $content);
}

function log_write($type, $category, $content)
{	
	if (!empty($_SESSION["user"]["debug"]))
	{
		// write log record
		$log_record = array();

		$log_record["type"]	= $type;
		$log_record["category"]	= $category;
		$log_record["content"]	= $content;
		$log_record["memory"]	= memory_get_usage();
	
		// this provided PHP 4 compadiblity.
		// TODO: when upgrading to PHP 5, replace with microtime(TRUE).
		list($usec, $sec)		= explode(" ", microtime());
		$log_record["time_usec"]	= $usec;
		$log_record["time_sec"]		= $sec;
		
		$_SESSION["user"]["log_debug"][] = $log_record;
		
		// print log messages when running from CLI
		if (!empty($_SESSION["mode"]))
		{
			if ($_SESSION["mode"] == "cli")
			{
				$content = str_replace("\n", "\\n", $content);	// fix newlines

				print "Debug: ". sprintf("%-10.10s", $type) ." | ". sprintf("%-20.20s", $category) ." | $content\n";
			}
		}
	}

	// also add error messages to the error array
	if ($type == "error")
	{
		$_SESSION["error"]["message"][] = $content;
		
		// print log messages when running from CLI
		if (isset($_SESSION["mode"]) && $_SESSION["mode"] == "cli")
			print "Error: $content\n";
	}

	// also add notification messages to the notification array
	if ($type == "notification")
	{
		$_SESSION["notification"]["message"][] = $content;
		
		// print log messages when running from CLI
		if (isset($_SESSION["mode"]) && ($_SESSION["mode"] == "cli"))
			print "$content\n";
	}

}




/*
	INCLUDE MAJOR AMBERDPHPLIB COMPONENTS
*/

@log_debug("start", "");
@log_debug("start", "AMBERPHPLIB STARTED");
@log_debug("start", "Debugging for: ". str_replace("&", " &", $_SERVER["REQUEST_URI"]) ."");
@log_debug("start", "");


// Important that we require language first, since other functions
// require it.
require("inc_language.php");

// DB SQL processing and execution
require("inc_sql.php");
require("inc_ldap.php");

// User + Security Functions
require("inc_user.php");
require("inc_security.php");

// Error Handling
require("inc_errors.php");

// Misc Functions
require("inc_misc.php");

// Template processing engines
require("inc_template_engines.php");

// Functions/classes for data entry and processing
require("inc_forms.php");
require("inc_tables.php");
require("inc_file_uploads.php");

// Journal System
require("inc_journal.php");

// Menus
require("inc_menus.php");

// Phone Home Functions
require("inc_phone_home.php");


log_debug("start", "Framework Load Complete.");


/*
	Load Application Configuration

	Some configuration is done locally (such as DB auth details), however most configuration is stored
	inside the database to provide easier management, display and validation of configuration.
*/

log_debug("start", "Loading configuration from database");

$sql_config_obj			= New sql_query;
$sql_config_obj->string		= "SELECT name, value FROM config ORDER BY name";
$sql_config_obj->execute();
$sql_config_obj->fetch_array();

foreach ($sql_config_obj->data as $data_config)
{
	if (!isset($GLOBALS["config"][ $data_config["name"] ]))
	{
		$GLOBALS["config"][ $data_config["name"] ] = $data_config["value"];
	}
}

unset($sql_config_obj);



/*
	Run Corrections

	Legacy adjustments to work around limitations that should be fixed
	in future but can't be done at once without potentially breaking applications
*/


// if user debugging is set to disabled, make NULL so reports as empty()
if (isset($_SESSION["user"]["debug"]))
{
	if ($_SESSION["user"]["debug"] == "disabled")
	{
		$_SESSION["user"]["debug"] = NULL;
	}
}



/*
	Configure Local Timezone

	Decent timezone handling was only implemented with PHP 5.2.0, so the ability to select the user's localtime zone
	is limited to users running this software on PHPv5 servers.

	Users of earlier versions will be limited to just using the localtime of the server - the effort required
	to try and add timezone for older users (mainly PHPv4) is not worthwhile when everyone should be moving to PHP 5.2.0+
	in the near future.
*/

if (version_compare(PHP_VERSION, '5.2.0') === 1)
{
	log_debug("start", "Setting timezone based on user/system configuration");
	
	// fetch config option
	if (isset($_SESSION["user"]["timezone"]))
	{
		// fetch from user preferences
		$timezone = $_SESSION["user"]["timezone"];
	}
	else
	{
		// user hasn't chosen a default time format yet - use the system default
		$timezone = sql_get_singlevalue("SELECT value FROM config WHERE name='TIMEZONE_DEFAULT' LIMIT 1");
	}

	// if set to SYSTEM just use the default of the server, otherwise
	// we need to set the timezone here.
	if ($timezone == "SYSTEM")
	{
		// set to the server default
		log_debug("start", "Using server timezone default");
		@date_default_timezone_set(@date_default_timezone_get());
	}
	else
	{
		// set to user selected or application default
		log_debug("start", "Using application configured timezone");

		if (!date_default_timezone_set($timezone))
		{
			log_write("error", "start", "A problem occured trying to set timezone to \"$timezone\"");
		}
		else
		{
			log_debug("start", "Timezone set to \"$timezone\" successfully");
		}
	}

	unset($timezone);
}



/*
	Preload Language DB

	The translation/errorloading options can be handled in one of two ways:
	1. Preload all entries for the selected language (more memory, few SQL queries)
	2. Only load translations as required (more SQL queries, less memory)
*/



// ensure a language has been set in the user's profile, otherwise select
// a default from the main configuration database
if (!isset($_SESSION["user"]["lang"]))
{
	$_SESSION["user"]["lang"] = sql_get_singlevalue("SELECT value FROM config WHERE name='LANGUAGE_DEFAULT' LIMIT 1");
}


// determine whether or not to preload the language
$language_mode			= sql_get_singlevalue("SELECT value FROM config WHERE name='LANGUAGE_LOAD' LIMIT 1");
$GLOBALS["cache"]["lang_mode"]	= $language_mode;

if ($language_mode == "preload")
{
	log_debug("start", "Preloading Language DB");


	// load all transactions
	$sql_obj		= New sql_query;
	$sql_obj->string	= "SELECT label, translation FROM `language` WHERE language='". $_SESSION["user"]["lang"] ."'";
	$sql_obj->execute();
	$sql_obj->fetch_array();

	foreach ($sql_obj->data as $data)
	{
		// add to cache
		$GLOBALS["cache"]["lang"][ $data["label"] ] = $data["translation"];
	}

	log_debug("start", "Completed Language DB Preload");
}



?>
