<?php
// Success messages
$lang['AdminDepartments.!success.department_created'] = "The %1\$s department was successfully created."; // %1$s is the name of the department
$lang['AdminDepartments.!success.department_updated'] = "The %1\$s department was successfully updated."; // %1$s is the name of the department
$lang['AdminDepartments.!success.department_deleted'] = "The %1\$s department was successfully deleted."; // %1$s is the name of the department


// Page titles
$lang['AdminDepartments.index.page_title'] = "Support Manager > Departments";
$lang['AdminDepartments.add.page_title'] = "Support Manager > Departments > Add Department";
$lang['AdminDepartments.edit.page_title'] = "Support Manager > Departments > Edit Department";


// Index
$lang['AdminDepartments.index.categorylink_adddepartment'] = "Add Department";
$lang['AdminDepartments.index.boxtitle_departments'] = "Departments";

$lang['AdminDepartments.index.heading_name'] = "Name";
$lang['AdminDepartments.index.heading_description'] = "Description";
$lang['AdminDepartments.index.heading_email'] = "Email";
$lang['AdminDepartments.index.heading_assigned_staff'] = "Assigned Staff";
$lang['AdminDepartments.index.heading_default_priority'] = "Default Priority";
$lang['AdminDepartments.index.heading_options'] = "Options";
$lang['AdminDepartments.index.option_edit'] = "Edit";
$lang['AdminDepartments.index.option_delete'] = "Delete";
$lang['AdminDepartments.index.confirm_delete'] = "Departments with tickets assigned to them may not be deleted until all tickets have been re-assigned to an alternate department. Are you sure you want to delete this department?";

$lang['AdminDepartments.index.no_results'] = "There are no departments.";

$lang['AdminDepartments.assigned_staff.heading_assigned_staff'] = "Assigned Staff";
$lang['AdminDepartments.assigned_staff.heading_staff'] = "Staff";
$lang['AdminDepartments.assigned_staff.no_results'] = "There are no staff assigned to this department.";

$lang['AdminDepartments.!tooltip.piping_config'] = "Set your piping path as shown, but be sure to update it to point to where PHP is installed if it differs from what is shown.";
$lang['AdminDepartments.!tooltip.close_ticket_interval'] = "All tickets with a status other than %1\$s whose last reply is from a staff member will be automatically closed if no replies have been made within the selected amount of time."; // %1$s is the ticket status In Progress

$lang['AdminDepartments.!text.add_response'] = "Set an Auto-Close Predefined Response";
$lang['AdminDepartments.!text.no_selected_response'] = "No auto-close response selected.";
$lang['AdminDepartments.!text.remove_response'] = "Remove";


// Add department
$lang['AdminDepartments.add.boxtitle_adddepartment'] = "Add Department";

$lang['AdminDepartments.add.field_name'] = "Name";
$lang['AdminDepartments.add.field_description'] = "Description";
$lang['AdminDepartments.add.field_clients_only'] = "Allow only clients to open or reply to tickets";
$lang['AdminDepartments.add.field_email'] = "Email";
$lang['AdminDepartments.add.field_override_from_email'] = "Override the from address set in email templates with the email address set for this department";
$lang['AdminDepartments.add.field_method'] = "Email Handling";
$lang['AdminDepartments.add.field_piping_config'] = "Piping Configuration";
$lang['AdminDepartments.add.field_default_priority'] = "Default Priority";
$lang['AdminDepartments.add.field_security'] = "Security";
$lang['AdminDepartments.add.field_box_name'] = "Box Name";
$lang['AdminDepartments.add.field_mark_messages'] = "Mark Messages as";
$lang['AdminDepartments.add.field_host'] = "Host";
$lang['AdminDepartments.add.field_user'] = "User";
$lang['AdminDepartments.add.field_pass'] = "Pass";
$lang['AdminDepartments.add.field_port'] = "Port";
$lang['AdminDepartments.add.field_close_ticket_interval'] = "Automatically Close Tickets";
$lang['AdminDepartments.add.field_response_id'] = "Auto-Close Ticket Response";
$lang['AdminDepartments.add.field_status'] = "Status";
$lang['AdminDepartments.add.field_addsubmit'] = "Add Department";


// Edit department
$lang['AdminDepartments.edit.boxtitle_adddepartment'] = "Edit Department";

$lang['AdminDepartments.edit.field_name'] = "Name";
$lang['AdminDepartments.edit.field_description'] = "Description";
$lang['AdminDepartments.edit.field_clients_only'] = "Allow only clients to open or reply to tickets";
$lang['AdminDepartments.edit.field_email'] = "Email";
$lang['AdminDepartments.edit.field_override_from_email'] = "Override the from address set in email templates with the email address set for this department";
$lang['AdminDepartments.edit.field_method'] = "Email Handling";
$lang['AdminDepartments.edit.field_piping_config'] = "Piping Configuration";
$lang['AdminDepartments.edit.field_default_priority'] = "Default Priority";
$lang['AdminDepartments.edit.field_security'] = "Security";
$lang['AdminDepartments.edit.field_box_name'] = "Box Name";
$lang['AdminDepartments.edit.field_mark_messages'] = "Mark Messages as";
$lang['AdminDepartments.edit.field_host'] = "Host";
$lang['AdminDepartments.edit.field_user'] = "User";
$lang['AdminDepartments.edit.field_pass'] = "Pass";
$lang['AdminDepartments.edit.field_port'] = "Port";
$lang['AdminDepartments.edit.field_response_id'] = "Auto-Close Ticket Response";
$lang['AdminDepartments.edit.field_status'] = "Status";
$lang['AdminDepartments.edit.field_addsubmit'] = "Edit Department";
?>