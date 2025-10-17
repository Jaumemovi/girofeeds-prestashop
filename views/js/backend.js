/*
* Channable Plugin
* for Support please visit www.patworx.de
*
*  @author patworx multimedia GmbH <service@patworx.de>
*  @copyright  2016-2025 patworx multimedia GmbH
*  @license    Released under the GNU General Public License
*/

var assigned_counter = 0;

$(document).ready(function() {	
	if ($("#assign_field").length > 0) {		
		$("#assign_field").on('click', function() {			
			if ($("select[name=fields_available]").val() && $("select[name=fields_available]").val() != '') {
				selected_field_data = $("select[name=fields_available]").val().split(".");
				selected_field_table = selected_field_data[0];
				selected_field_name = selected_field_data[1];			
				addAssignedRow(selected_field_table, selected_field_name, selected_field_name);
			}			
		});		
	}
	$("#show_more_fields_link").on('click', function(e) {
		e.preventDefault;
		$('.hidden_row_tax').show();
		$(this).hide();
	});
});

function addAssignedRow(field_table, field_name, field_in_feed)
{	
	$("#assign_fields_head").show();
	$("#no_assign_fields_message").hide();
	
	var new_row = $('<div class="row channable_assigned_fields_row"></div>');
	new_row.attr("data-field", field_table + '.' + field_name);
	var table_col = $('<div class="col-xs-3"></div>');
	var field_col = $('<div class="col-xs-3"></div>');
	var name_col = $('<div class="col-xs-5"></div>');
	var remove_col = $('<div class=col-xs-1"></div>');
	
	var table_name_input = $('<input type="text" readonly="readonly" class="channable_assigned_fields_tablename" name="assigned_fields[' + assigned_counter + '][tablename]" value="' + field_table + '" />');
	var table_field_input = $('<input type="text" readonly="readonly" class="channable_assigned_fields_field_in_db" name="assigned_fields[' + assigned_counter + '][field_in_db]" value="' + field_name + '" />');
	var feed_field_input = $('<input type="text" name="assigned_fields[' + assigned_counter + '][field_in_feed]" value="' + field_in_feed + '" />');
	var del_action = $('<i class="icon-trash channable-trash"></i>');	
	del_action.on('click', function() {
		new_row.remove();
		disableAlreadyAssignedFields();
	});
	
	table_col.append(table_name_input);
	field_col.append(table_field_input);
	name_col.append(feed_field_input);
	remove_col.append(del_action);
	
	new_row.append(table_col);
	new_row.append(field_col);
	new_row.append(name_col);
	new_row.append(remove_col);
	
	$("#assigned_fields").append(new_row);
	
	assigned_counter = assigned_counter + 1;
	
	disableAlreadyAssignedFields();
}

function disableAlreadyAssignedFields()
{
	$("select[name=fields_available] option").removeAttr("disabled");
	if ($(".channable_assigned_fields_row").length > 0) {
		$(".channable_assigned_fields_row").each(function() {
			var assigned_tab_field = $(this).attr("data-field");
			$("select[name=fields_available] option[value='" + assigned_tab_field + "']").attr("disabled", "disabled");
		});
	}
	return;
}