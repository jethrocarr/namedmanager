$(document).ready(function()
{
	update_interval = $("#update_interval").val()*1000;
	setInterval("update_log_table()", update_interval);
});


function update_log_table()
{
	highest_id = parseInt($("#highest_id").val());
	$.getJSON("logs/ajax/get_new_logs.php", {highest_id: highest_id}, function(json){
		$(".new_row").removeClass("new_row");
		columns = $("#columns").val().split(",");
		$("#highest_id").val(json["new_highest_id"]);
		for (i=highest_id; i<json["new_highest_id"]; i++)
		{
			if (json[i+1])
			{
				html_string = "<tr class=\"new_row\">";
				for (name in columns)
				{
					html_string += "<td valign=\"top\">" + json[i+1][columns[name]] + "</td>";
				}
				html_string += "</tr>";			
				$(".table_content tr:first").after(html_string);
				$(".new_row td").show();
			}
		}
	});
	
}