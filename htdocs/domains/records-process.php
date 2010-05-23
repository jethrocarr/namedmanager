<?php
/*
	domains/records-process.php

	access:
		namedadmins

	Updates DNS records for the selected domain name.
*/

// includes
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");



if (user_permissions_get("namedadmins"))
{
	$obj_domain	= New domain;


	/*
		Import form data
	*/

	$obj_domain->id				= @security_form_input_predefined("int", "id_domain", 1, "");




	/*
		DNS records
	*/

	// determine number of rows
	$data["ns"]["num_records"]		= @security_form_input_predefined("int", "num_records_ns", 0, "");
	$data["mx"]["num_records"]		= @security_form_input_predefined("int", "num_records_mx", 0, "");
	$data["custom"]["num_records"]		= @security_form_input_predefined("int", "num_records_custom", 0, "");


	// add valid NS records
	for ($i = 0; $i < $data["ns"]["num_records"]; $i++)
	{
		$data_tmp["type"]		= "NS";
		$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_ns_". $i ."_ttl", 0, "");
		$data_tmp["name"]		= @security_form_input_predefined("any", "record_ns_". $i ."_name", 0, "");
		$data_tmp["content"]		= @security_form_input_predefined("any", "record_ns_". $i ."_content", 0, "");

		if ($data_tmp["content"])
		{
			$data["records"][] = $data_tmp;
		}
	}


	// add valid MX records
	for ($i = 0; $i < $data["mx"]["num_records"]; $i++)
	{	
		$data_tmp["type"]		= "MX";
		$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_mx_". $i ."_ttl", 0, "");
		$data_tmp["prio"]		= @security_form_input_predefined("any", "record_mx_". $i ."_prio", 0, "");
		$data_tmp["content"]		= @security_form_input_predefined("any", "record_mx_". $i ."_content", 0, "");

		if ($data_tmp["content"])
		{
			$data["records"][] = $data_tmp;
		}
	}



	// add custom records
	for ($i = 0; $i < $data["custom"]["num_records"]; $i++)
	{
		$data_tmp["type"]		= @security_form_input_predefined("any", "record_custom_". $i ."_type", 0, "");
		$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_custom_". $i ."_ttl", 0, "");
		$data_tmp["name"]		= @security_form_input_predefined("any", "record_custom_". $i ."_name", 0, "");
		$data_tmp["content"]		= @security_form_input_predefined("any", "record_custom_". $i ."_content", 0, "");

		if ($data_tmp["content"])
		{
			$data["records"][] = $data_tmp;
		}
	}




	/*
		Error Handling
	*/



	// return to input page in event of an error
	if ($_SESSION["error"]["message"])
	{
		$_SESSION["error"]["form"]["domain_records"] = "failed";
		header("Location: ../index.php?page=domains/records.php&id=". $obj_domain->id ."");
		exit(0);
	}



	/*
		Update Database
	*/


	// TODO: this is kind of ugly and there should be a better way of doing this


	// fetch all DNS records
	$obj_domain->load_data();
	$obj_domain->load_data_record_all();
	
	// delete existing records
	foreach ($obj_domain->data["records"] as $record)
	{
		$obj_record		= New domain_records;
		$obj_record->id		= $obj_domain->id;
		$obj_record->id_record	= $record["id_record"];

		$obj_record->action_delete_record();
	}


	// add new records
	foreach ($data["records"] as $record)
	{
		$obj_record		= New domain_records;
		$obj_record->id		= $obj_domain->id;

		$obj_record->data_record["name"]	= $record["name"];
		$obj_record->data_record["type"]	= $record["type"];
		$obj_record->data_record["content"]	= $record["content"];
		$obj_record->data_record["ttl"]		= $record["ttl"];
		$obj_record->data_record["prio"]	= $record["prio"];

		$obj_record->action_update_record();
	}


	// update domain
	$obj_domain->action_update_serial();
	$obj_domain->action_update_ns();

	// clear messages & replace with custom one
	$_SESSION["notification"] = array("Domain updated successfully - name servers scheduled to reload with new domain configuration");

	// display updated details
	header("Location: ../index.php?page=domains/records.php&id=". $obj_record->id);
	exit(0);
	
}
else
{
	// user does not have perms to view this page/isn't logged on
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>
