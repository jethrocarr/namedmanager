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
//require("inc_sql.php");
//require("inc_ldap.php");

// User + Security Functions
//require("inc_user.php");
//require("inc_security.php");

// Error Handling
require("inc_errors.php");

// Misc Functions
require("inc_misc.php");

// Template processing engines
//require("inc_template_engines.php");

// Functions/classes for data entry and processing
//require("inc_forms.php");
//require("inc_tables.php");
//require("inc_file_uploads.php");

// Journal System
//require("inc_journal.php");

// Menus
//require("inc_menus.php");

// Phone Home Functions
//require("inc_phone_home.php");


log_debug("start", "Framework Load Complete.");


