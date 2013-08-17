<?php
/*
	inc_menus.php

	Provides functions for drawing both the main application menu and the navigation menu.
*/


/*
	CLASS MENU_MAIN

	This class provides the functions for querying the structure of the menu from the
	database. This structure can then either be used by:

		a) rendering with one of the provided render functions

		b) using the structure to feed custom render code in the application

*/

class menu_main
{
	var $page;			// page that is currently selected - this value is used by the render
					// functions for showing which level/path is highlighted

	var $menu_order;		// array containing the order of the menu levels
	var $menu_structure;		// all the data for the menu entries that the user has access perms for

	var $option_remember;		// if set, if the page variable doesn't match any known pages in the menu, display
					// the menu using the last known good page. This feature is great for wiki sites
					// where new pages are created all the time, but we don't want the displayed menu
					// vanishing with them.


	/*
		load_data

		reads in the menu structure from the MySQL database and stores
		it in the $this->structure associative array

		Values
		<none>

		Returns
		0	No menu data or user has no permissions to access any menu entries
		1	Menu data returned to class variables.
	*/
	function load_data()
	{
		log_debug("menu_main", "Executing load_data()");



		/*
			Fetch an array of all the user permissions.
		*/
		log_debug("menu_main", "Fetching array of all the permissions the user has for displaying the menu");
		
		$user_permissions = array();

		if (user_online())
		{
			// it's probably the first time we're checking for permissions
			// we should pre-load them all if needed
			if (!isset($GLOBALS["cache"]["user"]["perms"]))
			{
				$obj_user_auth = New user_auth;
				$obj_user_auth->permissions_init();
			}

			// fetch ID of all permissions
			$sql_obj 		= New sql_query;
			$sql_obj->string	= "SELECT id, value FROM permissions";
			$sql_obj->execute();
			$sql_obj->fetch_array();
				

			// build array of all permissions IDs for the groups the user belongs to.
			foreach (array_keys($GLOBALS["cache"]["user"]["perms"]) as $type)
			{
				if ($GLOBALS["cache"]["user"]["perms"][$type] == 1)
				{
					foreach ($sql_obj->data as $data_permids)
					{
						if ($data_permids["value"] == $type)
						{
							$user_permissions[] = $data_permids["id"];
						}
					}
				}
			}

			// (legacy) For system without a public permissions group, add the ID of 0
			$user_permissions[] = "0";
		}
		else
		{
			// user is not logged in - select public menu entries only
			$sql_obj 		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `permissions` WHERE value='public' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();
				
				$user_permissions[] = $sql_obj->data[0]["id"];
			}
			else
			{
				// (legacy) For system without a public permissions group, add the ID of 0
				$user_permissionsp[] = "0";
			}
		}


		// unable to display a menu if there are no permissions
		if (!$user_permissions)
		{
			log_write("debug", "main_menu", "User has no permissions public or private so menu options can not be queried");
			return 0;
		}



		/*
			Fetch data for the entire menu from the database

			We fetch all the data at once, then run though it following the parent value as we run though
			all the items to determine what menu items need to be shown and in what order.

			We know that the single loop will match all the menu items correctly, since the menu items are ordered
			so we run though the order in the same direction. This saves us from having to do heaps of unnessacary loops. :-)
		*/

		log_debug("menu_main", "Loading menu from SQL database...");


		$sql_menu_obj		= New sql_query;
		$sql_menu_obj->string	= "SELECT link, topic, parent, config FROM menu WHERE permid IN (". format_arraytocommastring($user_permissions) .") ORDER BY priority DESC";
		$sql_menu_obj->execute();

		if (!$sql_menu_obj->num_rows())
		{
			log_debug("menu_main", "No menu entires exist for the current user that they have permission to access");
			return 0;
		}


		// fetch menu entires
		$sql_menu_obj->fetch_array();

		// array to store the order of the menu items
		$this->menu_order = array();
	
		// keep track of the topic we are looking for
		$target_topic = "";



		/*
			Apply config filtering

			Some applications have the need to be able to enable/disable specific features
			using boolean options in the config table - by setting the name of the value in
			the config column on the menu entries, a check will be made, and if the menu entry
			config option is unset, the menu options will not be displayed.

			This is typically used for hiding disabled features where for whatever reason, the
			feature can not be disabled using permissions groups.
		*/

		for ($i=0; $i < $sql_menu_obj->data_num_rows; $i++)
		{
			// check feature option (if set)
			if (!empty($sql_menu_obj->data[$i]["config"]))
			{
				@list($config_name, $config_value) = explode('=', $sql_menu_obj->data[$i]["config"], 2);

				if (!$GLOBALS["config"][ $config_name ])
				{
					// config is disabled for this feature
					unset($sql_menu_obj->data[$i]);
				}
				else
				{
					if ($config_value)
					{
						// do value matching
						if ($GLOBALS["config"][ $config_name ] != $config_value)
						{

							// non match, failed
							unset($sql_menu_obj->data[$i]);

						}
					}

					// default is that menu item is enabled since config option exists
				}
			}
		}



		/*
			If the remember option is enabled, check if the page provided exists, if not
			then we will select the last known good page (from session variables).

			If it does exist, we set the session variable to the new page.
		*/
		if ($this->option_remember)
		{
			// check page
			foreach ($sql_menu_obj->data as $data)
			{
				if ($data["link"] == $this->page)
				{
					$_SESSION["amberphplib"]["menu"]["page"] = $this->page;
				}
			}


			// set page
			$this->page = $_SESSION["amberphplib"]["menu"]["page"];
		}


		// loop though the menu items 
		foreach ($sql_menu_obj->data as $data)
		{
			// add each item to menu array
			if ($target_topic != "top")
			{
				if (!$target_topic)
				{
					// use the page link to find the first target
					if ($data["link"] == $this->page)
					{
						$target_topic		= $data["parent"];
						$this->menu_order[]	= $data["parent"];
					}
				}
				else
				{
					// check the topic type
					if ($data["topic"] == $target_topic)
					{
						$target_topic		= $data["parent"];
						$this->menu_order[]	= $data["parent"];
					}
				}
			}
		}


		// now we reverse the order array, so we can
		// render the menus in the correct order
		if ($this->menu_order)
		{
			$this->menu_order = array_reverse($this->menu_order);
		}
		else
		{
			// if we have no sub-menu information, just set
			// to display the top menu only
			$this->menu_order = array("top");
		}

		
		// sort the menu data in the opposite direction for correct rendering
		$this->menu_structure = array_reverse($sql_menu_obj->data);


		// return success
		return 1;

	} // end of load_data



	/*
		render_menu_standard()

		Renders the menu loaded from the database by load_data as rows, with a new row
		droppping down for each selected menu level.

		Good example: Amberdms Billing System

		Values
		<none>

		Returns
		0	failure
		1	success
	*/

	function render_menu_standard()
	{
		log_debug("menu_main", "Executing render_menu_standard()");


		print "<table class=\"menu_table\">";

		// run through the menu order
		for ($i = 0; $i <= count($this->menu_order); $i++)
		{
			print "<tr>";
			print "<td>";
			print "<ul id=\"menu\">";


			// loop though the menu data
			foreach ($this->menu_structure as $data)
			{
				if (isset($this->menu_order[$i]))
				{
					if ($data["parent"] == $this->menu_order[$i])
					{
						// if this entry has no topic, it only exists for the purpose of getting a parent
						// link highlighted. In this case, ignore the current entry.

  						if ($data["topic"])
  						{
 
 							$link_prefix = "index.php?page=";
 							if(strtolower(substr($data['link'], 0, 4)) == 'http') {
 								log_debug("menu_main", "found http in link: " . $data['link']);
 								$link_prefix = '';
 							}
 
 							// highlight the entry, if it's the parent of the next sub menu, or if this is a sub menu.
  							if (isset($this->menu_order[$i + 1]) && $this->menu_order[$i + 1] == $data["topic"])
  							{
 								print "<li><a class=\"menu_current\" href=\"" . $link_prefix . $data["link"] ."\" title=". lang_trans($data["topic"]) .">". lang_trans($data["topic"]) ."</a></li>";
  							}
  							elseif ($data["link"] == $this->page)
  							{
 								print "<li><a class=\"menu_current\" href=\"" . $link_prefix . $data["link"] ."\" title=". lang_trans($data["topic"]) .">". lang_trans($data["topic"]) ."</a></li>";
  							}
  							else
  							{
 								print "<li><a href=\"" . $link_prefix . $data["link"] ."\" title=". lang_trans($data["topic"]) .">". lang_trans($data["topic"]) ."</a></li>";
  							}
  						}
					}
				}

			} // end of loop though menu data

			print "</ul>";
			print "</td>";
			print "</tr>";
			
		}
		
		print "</table>";

	} // end of render_menu_standard



	/*
		render_menu_expanded()

		Renders the top level as a standard row, but once selected levels 2 and 3 are displayed as columns
		beneath the selected entry.

		Example: www.amberdms.com website

		Values
		<none>

		Returns
		0	failure
		1	success
	*/

	function render_menu_expanded()
	{
		log_debug("menu_main", "Executing render_menu_expanded()");


		print "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">";

		// draw the level 0 menu (top)
		print "<tr>";
		print "<td width=\"100%\" cellpadding=\"0\" cellborder=\"0\" cellspacing=\"0\">";
		print "<ul id=\"menu\">";

		// loop though the menu data
		foreach ($this->menu_structure as $data)
		{
			if ($data["parent"] == $this->menu_order[0])
			{
				// if this entry has no topic, it only exists for the purpose of getting a parent
				// link highlighted. In this case, ignore the current entry.

				if ($data["topic"])
				{
					$title = lang_trans($data["topic"]);

					// highlight the entry, if it's the parent of the next sub menu, or if this is a sub menu.
					if ($this->menu_order[$i + 1] == $data["topic"] || $data["link"] == $this->page)
					{
						print "<li><a style=\"background-color: #333333;\" href=\"index.php?page=". $data["link"] ."\" title=". $title ."><b>". $title ."</b></a></li>";
					}
					else
					{
						print "<li><a href=\"index.php?page=". $data["link"] ."\" title=". $title ."><b>". $title ."</b></a></li>";
					}
				}
			}

		} // end of loop though menu data

		print "</ul>";
		print "</td>";
		print "</tr>";


		// draw the level 1 & 2 menus
		if ($this->menu_order[1])
		{
			print "<tr>";
			print "<td width=\"100%\" cellpadding=\"0\" cellborder=\"0\" cellspacing=\"0\" style=\"background-color: #333333;\">";

			print "<table>";
			print "<tr>";
				
				// fetch level 1 data
				foreach ($this->menu_structure as $data)
				{
					if ($data["parent"] == $this->menu_order[1])
					{
						// if this entry has no topic, it only exists for the purpose of getting a parent
						// link highlighted. In this case, ignore the current entry.

						if ($data["topic"])
						{
							// spacer
							print "<td width=\"25\">&nbsp;</td>";


							print "<td valign=\"top\" id=\"menu_level1\">";

							// title row
							print "<a href=\"index.php?page=". $data["link"] ."\" title=". lang_trans($data["topic"]) ."><b>". lang_trans($data["topic"]) ."</b></a><br>";

							// additional level 3 links
							foreach ($this->menu_structure as $data_l3)
							{
								if ($data_l3["parent"] == $data["topic"])
								{
									if ($data_l3["topic"])
									{
										print "<a href=\"index.php?page=". $data_l3["link"] ."\" title=". lang_trans($data_l3["topic"]) .">". lang_trans($data_l3["topic"]) ."</a><br>";
									}
								}
							}

	
							print "</td>";

							// spacer
							print "<td width=\"25\">&nbsp;</td>";
					
						}
					}

				}
	
			print "</tr>";
			print "</table>";


			print "</td>";
			print "</tr>";
		}

		
	

		print "</table>";

	} // end of render_menu_expanded




	/*
		render_menu_breadcrumbs

		Breadcrumbs are used to show the user where they are in the site/application structure, for example
		About Company > Support > Contact Us

		This function renders the breadcrumb trail based on the data from the menu system.

		Values
		<none>

		Returns
		0	failure
		1	success
	*/
	function render_menu_breadcrumbs()
	{
		log_write("debug", "menu_main", "Executing render_breadcrumbs()");


		// only display if the user is not viewing the top of the site
		if (count($this->menu_order) == 1)
		{
			return 0;
		}


		/*
			We now run through the menu order and also fetch the title
			for the current page, and add all the page titles to an array.

			By doing this, we eliminate any duplicated page titles and can
			do a breadcrumb trail with no dupes.
		*/

		$breadcrumbs = array();

		// menu steps
		for ($i = 1; $i <= count($this->menu_order); $i++)
		{
			foreach ($this->menu_structure as $data)
			{
				if ($data["topic"] == $this->menu_order[$i])
				{
					if ($data["topic"])
					{
						if (!in_array($data["topic"], $breadcrumbs))
						{
							// add the page to the breadcrumbs
							$breadcrumbs[] = $data["topic"];
						}
					}
				}
			}
		}

		// current page
		foreach ($this->menu_structure as $data)
		{
			if ($data["link"] == $this->page)
			{
				if ($data["topic"])
				{
					if (!in_array($data["topic"], $breadcrumbs))
					{
						// add the page to the breadcrumbs
						$breadcrumbs[] = $data["topic"];
					}
				}
			}
		}


		/*
			Output the provided bread crumbs
		*/

		print "<p style=\"margin: 0px; padding: 4px;\"><b>";
		print "<a href=\"index.php\">Amberdms</a> &gt; ";

		for ($i=0; $i < count($breadcrumbs); $i++)
		{
			foreach ($this->menu_structure as $data)
			{
				if ($data["topic"] == $breadcrumbs[$i])
				{
					print "<a href=\"index.php?page=". $data["link"] ."\" title=". lang_trans($data["topic"]) .">". lang_trans($data["topic"]) ."</a>";

					if ($i < (count($breadcrumbs) - 1))
					{
						print " &gt; ";
					}
				}
			}
		}

		print "</b></p>";

		return 1;
	}




} // end of class menu_main
					


/*
	CLASS MENU_NAV

	The main application menu is built from the configuration in the MySQL database. However, it is often desirable
	to be able to create a custom menu for the page currently running, for uses such as spliting large pages into
	multiple sections (simular to tabs).

	Amberphplib provides a menu called the "nav menu" which can be used to define custom menus. These menus need to
	be defined at run time by the page being executed.
	        
	Usage (example):

		(The following code should go after user permissions verification, but before the page_render function)

		To enable the nav menu on the page:
		> $_SESSION["nav"]["active"] = 1;

		For each menu entry you wish to have, use the following syntax:
		> $_SESSION["nav"]["query"][] = "page=home/home.php";
		> $_SESSION["nav"]["title"][] = "Return to Home";

		To choose which one will be high-lighted when the menu is drawn, specify which
		page URL should be made current:			
		> $_SESSION["nav"]["current"] = "page=home/home.php"

*/

class menu_nav
{
	var $structure;		// holds the structure of the navigation menu


	/*
		add_item

		Add a new item to the menu bar

		Values
		title		human-readable title of the link
		link		URL in the form of page=<pagename>
		selected	Set to 1 to make this nav item selectred
	*/
	function add_item($title, $link, $selected = NULL)
	{
		log_debug("menu_nav", "Executing add_item($title, $link, $selected)");

		$this->structure["links"][] = $link;
		$this->structure["title"][] = $title;

		if ($selected)
		{
			$this->structure["selected"] = $link;
		}
	}



	/*
		render_html

		Renders the navigiation menu.

	*/
	function render_html()
	{
		log_debug("menu_nav", "Executing render_html()");

		print "<table class=\"menu_nav_table\">";
		print "<tr>";
		print "<td>";

		print "<ul id=\"navmenu\">";

		        $j = count($this->structure["links"]);
			
		        for ($i=0; $i < $j; $i++)
		        {
				// are we viewing the current page?
				if ($this->structure["selected"] == $this->structure["links"][$i])
				{
					print "<li><a class=\"menu_nav_current\" href=\"index.php?". $this->structure["links"][$i] ."\" title=\"". $this->structure["title"][$i] ."\">". $this->structure["title"][$i] ."</a></li>";
				}
				else
				{
					print "<li><a href=\"index.php?". $this->structure["links"][$i] ."\" title=\"". $this->structure["title"][$i] ."\">". $this->structure["title"][$i] ."</a></li>";
				}
			}

		
		print "</ul>";

		print "</td>";
		print "</tr>";
		print "</table>";
	}
	
} // end of class menu_nav


?>
