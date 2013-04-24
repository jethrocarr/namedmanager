<?php
/*
	namedmanager

	Nameserver/zonefile management interface written in PHP.


	(c) Copyright 2010 Amberdms Ltd

	www.amberdms.com/namedmanager


	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License version 3
	only as published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/



/*
	Include configuration + libraries
*/
include("include/config.php");
include("include/amberphplib/main.php");
include("include/application/main.php");


log_debug("index", "Starting index.php");


/*
	Enforce HTTPS
*/
if (!$_SERVER["HTTPS"])
{
	header("Location: https://". $_SERVER["SERVER_NAME"] ."/".  $_SERVER['PHP_SELF'] );
	exit(0);
}




/*
	Fetch the page name to display, and perform security checks
*/

// get the page to display
if (isset($_GET["page"]))
{
	$page = $_GET["page"];
}
else
{
	$page = "home.php";
}

	
// perform security checks on the page
// security_localphp prevents any nasties, and then we check the the page exists.
$page_valid = 0;
if (!security_localphp($page))
{
	log_write("error", "index", "Sorry, the requested page could not be found - please check your URL.");
}
else
{
	if (!@file_exists($page))
	{
		log_write("error", "index", "Sorry, the requested page could not be found - please check your URL.");
	}
	else
        {
		/*
			Load the page
		*/

		log_debug("index", "Loading page $page");


		// include PHP code
		include($page);


		// create new page object
		$page_obj = New page_output;


		// page is valid
		$page_valid = 1;



	}
}



?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN" "http://www.w3.org/TR/REC-html40/strict.dtd">
<html>
<head>
	<title>NamedManager</title>
	<meta name="copyright" content="(C)Copyright 2010 Amberdms Ltd">
	<link rel="shortcut icon" href="images/namedmanager.ico" />

<?php
	// include base CSS file
	print "<link href=\"include/style.css\" rel=\"stylesheet\" type=\"text/css\" />\n";

	// include page-specific css files
	if (isset($page_obj->requires["css"]))
	{
		foreach ($page_obj->requires["css"] as $includefile)
		{
			log_write("debug", "main", "Including additional CSS file from default theme $includefile");
			print "<link href=\"$includefile\" rel=\"stylesheet\" type=\"text/css\" />\n";
		}
	}

	
	
	?>

<script type="text/javascript" src="external/jquery/jquery.js"></script>
<script type="text/javascript" src="include/javascript/javascript.js"></script>


<?php

// include page-specific javascript files
if (isset($page_obj->requires["javascript"]))
{
	foreach ($page_obj->requires["javascript"] as $includefile)
	{
		print "<script type=\"text/javascript\" src=\"$includefile\"></script>\n";
	}
}

?>


</head>

<body>


<!-- Main Structure Table -->
<table width="90%" cellspacing="5" cellpadding="0" align="center">



<!-- Header -->
<tr>
	<td bgcolor="#2f5da8" style="border: 1px #747474 dashed;">
		<table width="100%">
		<tr>
			<td width="50%" align="left"><img src="images/namedmanager-logo.png" alt="NamedManager"></td>
			<td width="50%" align="right" valign="top">
			<?php

			if (user_online())
			{
				print "<p style=\"font-size: 10px; color: #ffffff;\"><b>";
				print "logged on as ". $_SESSION["user"]["name"] ." | ";

				if ($GLOBALS["config"]["AUTH_METHOD"] == "sql")
				{
					print "<a style=\"color: #ffffff\" href=\"index.php?page=user/options.php\">options</a> | ";
				}

				print "<a style=\"color: #ffffff\" href=\"index.php?page=user/logout.php\">logout</a>";																				print "</b></p>";

			}

			?>
			</td>
		</tr>
		</table>
	</td>
</tr>


<?php

	
/*
	Draw the main page menu
*/

if ($page_valid == 1 && user_online())
{
	print "<tr><td>";

	$obj_menu			= New menu_main;
	$obj_menu->page			= $page;

	if ($obj_menu->load_data())
	{
		$obj_menu->render_menu_standard();
	}


	print "</td></tr>";
}
	



/*
	Load the page
*/

if ($page_valid == 1)
{
	// check permissions
	if ($page_obj->check_permissions())
	{
		/*
			Draw navigiation menu
		*/
		
		if (isset($page_obj->obj_menu_nav))
		{
			print "<tr><td>";
			$page_obj->obj_menu_nav->render_html();
			print "</tr></td>";
		}



		/*
			Check data
		*/
		$page_valid = $page_obj->check_requirements();


		/*
			Run page logic, provided that the data was valid
		*/
		if ($page_valid)
		{
			$page_obj->execute();
		}
	}
	else
	{
		// user has no valid permissions
		$page_valid = 0;
		error_render_noperms();
	}
}



/*
	Draw messages
*/

if (!empty($_SESSION["error"]["message"]))
{
	print "<tr><td>";
	log_error_render();
	print "</td></tr>";
}
else
{
	if (!empty($_SESSION["notification"]["message"]))
	{
		print "<tr><td>";
		log_notification_render();
		print "</td></tr>";
	}
}



/*
	Draw page data
*/

if ($page_valid)
{
	// HTML-formatted output
	print "<tr><td bgcolor=\"#ffffff\" style=\"border: 1px #000000 dashed; padding: 5px;\">";
	print "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\"><tr>";

	print "<td valign=\"top\" style=\"padding: 5px;\">";
	$page_obj->render_html();
	print "<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br></td>";

	print "</tr></table>";
	print "</td></tr>";
}
else
{
	// padding
	print "<tr><td bgcolor=\"#ffffff\" style=\"border: 1px #000000 dashed; padding: 5px;\">";
	print "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">";

	print "<td valign=\"top\" style=\"padding: 5px;\">";
	print "<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br></td>";
	
	print "</tr></table>";
	print "</td></tr>";
}


// save query string, so the user can return here if they login. (providing none of the pages are in the user/ folder, as that will break some stuff otherwise.)
if (!preg_match('/^user/', $page))
{
	$_SESSION["login"]["previouspage"] = $_SERVER["QUERY_STRING"];
}

?>




<!-- Page Footer -->
<tr>
	<td bgcolor="#2f5da8" style="border: 1px #747474 dashed;">

	<table width="100%">
	<tr>
		<td align="left">
		<p style="font-size: 10px; color: #ffffff;">NamedManager is licensed under the AGPL</p>
		</td>

		<td align="right">
		<p style="font-size: 10px; color: #ffffff;">Version <?php print $GLOBALS["config"]["app_version"]; ?></p>
		</td>
	</tr>
	</table>
	
	</td>
</tr>

<?php

if (!empty($_SESSION["user"]["log_debug"]))
{
	print "<tr>";
	print "<td bgcolor=\"#ffffff\" style=\"border: 1px #000000 dashed;\">";


	log_debug_render();


	print "</td>";
	print "</tr>";
}

?>


</table>

<br><br><br><br><br>
<br><br><br><br><br>
<br><br><br><br><br>
<br><br><br><br><br>
<br><br><br><br><br>
<br><br><br><br><br>

</body></html>


<?php

// erase error and notification arrays
$_SESSION["user"]["log_debug"] = array();
$_SESSION["error"] = array();
$_SESSION["notification"] = array();

?>
