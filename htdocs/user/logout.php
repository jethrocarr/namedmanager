<?php
/*
	user/logout.php

	allows a user to logout of the site.

*/

class page_output
{
	function check_permissions()
	{
		return user_online();
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
		/////////////////////////
	
		print "<h3>USER LOGOUT:</h3>";
		print "<p>Click below to logout. Remember: You must never leave a logged in session unattended!</p>";
	
		print "<form method=\"POST\" action=\"user/logout-process.php\">
		<input type=\"submit\" value=\"Logout\">
		</form>";
		/////////////////////////
	}
}


?>
