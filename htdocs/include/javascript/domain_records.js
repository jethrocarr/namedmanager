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
	$(".delete_undo").click(function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
});




/*
	add_recordrow_mx

	Add new form rows for MX records
*/
function add_recordrow_mx()
{
	previous_row	= $("input[name='record_mx_" + (num_records_mx-1) + "_prio']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_prio']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio").removeClass("helpmessage").val("");
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_ttl']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl").removeClass("helpmessage").val("");
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_content']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content").removeClass("helpmessage").val("");
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
	$("input[name^='record_mx_" + (num_records_mx-1) + "_delete_undo']").siblings(".delete_undo").click(function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
}


/*
	add_recordrow_ns

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_ns()
{
	previous_row	= $("input[name='record_ns_" + (num_records_ns-1) + "_name']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name^='record_ns_" + (num_records_ns-1) + "_ttl']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_ttl").removeClass("helpmessage");
	$(new_row).children().children("input[name^='record_ns_" + (num_records_ns-1) + "_name']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_name").removeClass("helpmessage").val("");
	$(new_row).children().children("input[name^='record_ns_" + (num_records_ns-1) + "_content']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_content").removeClass("helpmessage").val("");
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
	$("input[name^='record_ns_" + (num_records_ns-1) + "_delete_undo']").siblings(".delete_undo").click(function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
}


/*
	add_recordrow_custom

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_custom()
{
	previous_row	= $("input[name='record_custom_" + (num_records_custom-1) + "_name']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("select[name^='record_custom_" + (num_records_custom-1) + "_type']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_type");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_ttl']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_ttl").val("");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_name']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name").val("");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_content']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content").val("");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_delete_undo']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_delete_undo").val("false");
	
	//remove function calls from previous row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_custom++;
	$("input[name='num_records_custom']").val(num_records_custom);
	
	//add function calls to new row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);	
	$("input[name^='record_custom_" + (num_records_custom-1) + "_delete_undo']").siblings(".delete_undo").click(function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
}


/*
 * 	delete_row
 * 
 * 	grey out row (darken) and set hidden delete variable to true
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

