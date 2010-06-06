/*
	include/javascript/domain_records.js

	Provides logic for adding additional fields for the records page when required.
*/

var num_records_mx;
var num_records_ns;
var num_records_custom;


$(document).ready(function()
{
	/*
		When any element in the last row is changed (therefore, having data put into it), call a function to create a new row
	*/
	num_records_ns = $("input[name='num_records_ns']").val();
	num_records_mx = $("input[name='num_records_mx']").val();
	num_records_custom = $("input[name='num_records_custom']").val();
	
	$("select[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);

	$("select[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);

	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	
	/*
	 * 	Attach delete function to mouse click on delete link
	 */
	$(".delete_undo").live("click", function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
	
	$(".delete_undo").live("select", function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
	
	/*
	 * 	Change columns for custom records on change
	 */
	$("select[name^='record_custom_']").change(function()
	{
		if ($(this).val() == "CNAME")
		{
			$(this).parent().siblings().children("input[name$='_ttl']").attr("disabled", "disabled");
			$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
			change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name for CNAME, eg www");
			change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Hostname or FQDN of target record");
		}
		else
		{
			$(this).parent().siblings().children("input[name$='_ttl']").removeAttr("disabled");
			$(this).parent().siblings().children("input[name$='_reverse_ptr']").removeAttr("disabled");
			change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name, eg www");
			change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Target IP, eg 192.168.0.1");
		}
	});

});


/*
	add_recordrow_mx

	Add new form rows for MX records
*/
function add_recordrow_mx()
{
	previous_row	= $("input[name='record_mx_" + (num_records_mx-1) + "_prio']").parent().parent();
	new_row			= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_prio']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_prio_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_prio";
	previous_string = "record_mx_" + (num_records_mx-1) + "_prio";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_ttl']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_ttl";
	previous_string = "record_mx_" + (num_records_mx-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");
	
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_content']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_content";
	previous_string = "record_mx_" + (num_records_mx-1) + "_content";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_delete_undo']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_delete_undo").val("false");
	
	//remove function calls from previous row
	$("select[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change");
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change", add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_mx++;
	$("input[name='num_records_mx']").val(num_records_mx);
	
	//add function calls to new row
	$("select[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
}

/*
	add_recordrow_ns

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_ns()
{
	previous_row	= $("input[name='record_ns_" + (num_records_ns-1) + "_name']").parent().parent();
	new_row			= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_ttl']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_ttl");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_ttl_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_ttl";
	previous_string = "record_ns_" + (num_records_ns-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");	
	
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_name']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_name");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_name_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_name_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_name";
	previous_string = "record_ns_" + (num_records_ns-1) + "_name";
	dynamic_help_message(previous_string, new_string);	
	
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_content']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_content");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_content_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_content";
	previous_string = "record_ns_" + (num_records_ns-1) + "_content";
	dynamic_help_message(previous_string, new_string);	
	
	$(new_row).children().children("input[name^='record_ns_" + (num_records_ns-1) + "_delete_undo']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_delete_undo").val("false");
	
	//remove function calls from previous row
	$("select[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change");
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change", add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_ns++;
	$("input[name='num_records_ns']").val(num_records_ns);
	
	//add function calls to new row
	$("select[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);	
}


/*
	add_recordrow_custom

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_custom()
{
	previous_row	= $("input[name='record_custom_" + (num_records_custom-1) + "_name']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("select[name='record_custom_" + (num_records_custom-1) + "_type']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_type");
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_ttl']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_ttl");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_ttl_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_ttl";
	previous_string = "record_custom_" + (num_records_custom-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_name']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_name_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_name";
	previous_string = "record_custom_" + (num_records_custom-1) + "_name";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_content']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_content";
	previous_string = "record_custom_" + (num_records_custom-1) + "_content";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_delete_undo']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_delete_undo").val("false");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_reverse_ptr']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_reverse_ptr").attr('checked', false);
	
	//remove function calls from previous row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_custom++;
	$("input[name='num_records_custom']").val(num_records_custom);
	
	//add function calls to new row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(function()
			{
				if ($(this).val() == "CNAME")
				{
					$(this).parent().siblings().children("input[name$='_ttl']").attr("disabled", "disabled");
					$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
					change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name for CNAME, eg www");
					change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Hostname or FQDN of target record eg webserver1");
				}
				else
				{
					$(this).parent().siblings().children("input[name$='_ttl']").removeAttr("disabled");
					$(this).parent().siblings().children("input[name$='_reverse_ptr']").removeAttr("disabled");
					change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name, eg www");
					change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Target IP, eg 192.168.0.1");
				}
			});
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);	
}


/*
 * 	delete_row
 * 
 * 	grey out row and set hidden delete variable to true
 */
function delete_undo_row(cell)
{
	var status = $(cell).children("input").val();
	if (status == "false")
	{
		$(cell).siblings().fadeTo("slow", 0.1);
		$(cell).children(".delete_undo").children().html("undo");
		$(cell).children("input").val("true");
	}
	else if (status == "true")
	{
		$(cell).siblings().fadeTo("slow", 1);
		$(cell).children(".delete_undo").children().html("delete");
		$(cell).children("input").val("false");
	}
}

function dynamic_help_message(previous_string, new_string, is_ttl)
{
	//check if ttl
	is_ttl = is_ttl || "false";
	//check if helpmessagestatus field is false (false means there is no help message)
	if ($("input[name='" + previous_string + "_helpmessagestatus']").val() != "false")
	{
	//if exists
		//set class to helpmessage
		$("input[name='" + new_string + "']").addClass("helpmessage");
		//check if helpmessagestatus is true (if true, value remains the same, therefore no modifications are needed
			if ($("input[name='" + previous_string + "_helpmessagestatus']").val() != "true")
			{
			//if not true
				//val becomes value of helpmessagestatus field
				$("input[name='" + new_string + "']").val($("input[name='" + previous_string + "_helpmessagestatus']").val());
			}

		//apply on click / blur functions
		$("input[name='" + new_string + "']").click(function()
		{
			var message = $(this).val();
			$(this).siblings("input[name$='helpmessagestatus']").val(message);
			$(this).val("").removeClass("helpmessage").blur(function()
			{
				if ($(this).val().length == 0)
				{
					$(this).addClass("helpmessage").val(message);
					$(this).siblings("input[name$='helpmessagestatus']").val("true");
				}
			});
		});
		
		$("input[name='" + new_string + "']").select(function()
		{
			var message = $(this).val();
			$(this).siblings("input[name$='helpmessagestatus']").val(message);
			$(this).val("").removeClass("helpmessage").blur(function()
			{
				if ($(this).val().length == 0)
				{
					$(this).addClass("helpmessage").val(message);
					$(this).siblings("input[name$='helpmessagestatus']").val("true");
				}
			});
		});
	}
	//if does not exist
	else
	{
		if (is_ttl == "false")
		{
			//val set to ""
			$("input[name='" + new_string + "']").val("");
		}
	}
}

function change_help_message(field, message)
{
	if ($(field).hasClass("helpmessage"))
	{
		$(field).val(message);
		$(field).siblings("input[name$='helpmessagestatus']").val("true");
	}
	else
	{
		if ($(field).val().length < 1)
		{
			$(field).addClass("helpmessage").val(message);
			$(field).siblings("input[name$='helpmessagestatus']").val("true");
		}
		else
		{
			$(field).siblings("input[name$='helpmessagestatus']").val(message);
		}
	}
}
