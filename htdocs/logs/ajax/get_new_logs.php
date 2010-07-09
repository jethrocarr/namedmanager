<?php 
require("../../include/config.php");
require("../../include/amberphplib/main.php");

if(user_permissions_get("namedadmins"))
{
	$highest_id = @security_script_input_predefined("int", $_GET['highest_id']);
	
	
	$sql_obj		= New sql_query;
	$sql_obj->string	= "SELECT logs.id, logs.timestamp, name_servers.server_name, dns_domains.domain_name, logs.username, logs.log_type, logs.log_contents
					FROM logs LEFT JOIN name_servers ON name_servers.id = logs.id_server
						LEFT JOIN dns_domains ON dns_domains.id = logs.id_domain
					WHERE logs.id > " .$highest_id;
	$sql_obj->execute();
	
	$new_highest_id = $highest_id;
	$data["new_highest_id"] = $new_highest_id;
	if ($sql_obj->num_rows())
	{
		$sql_obj->fetch_array();
		$data = array();
		
		foreach($sql_obj->data as $record)
		{
			$id = $record["id"];
			$data[$id]["timestamp"]		= time_format_humandate(date("Y-m-d", $record["timestamp"]))." ".date("H:i:s", $record["timestamp"]);
			$data[$id]["server_name"]	= $record["server_name"];
			$data[$id]["domain_name"]	= $record["domain_name"];
			$data[$id]["username"]		= $record["username"];
			$data[$id]["log_type"]		= $record["log_type"];
			$data[$id]["log_contents"]	= $record["log_contents"];
			
			if($id > $new_highest_id)
			{
				$new_highest_id = $id;
				$data["new_highest_id"] = $new_highest_id;
			}
		}
	
	}
	echo json_encode($data);
	
}
else
{
 	log_write("error", "message", "(AJAX) Invalid product requested");
	die("fatal error");
}

exit(0);
?>