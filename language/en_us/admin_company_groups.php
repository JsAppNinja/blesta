<?php
/**
 * Language definitions for the Admin Company Client Group settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyGroups.!success.add_created'] = "%1\$s has been successfully created!"; // %1$s is the name of the client group
$lang['AdminCompanyGroups.!success.edit_updated'] = "%1\$s has been successfully edited!"; // %1$s is the name of the client group
$lang['AdminCompanyGroups.!success.delete_deleted'] = "%1\$s was successfully deleted!"; // %1$s is the name of the client group

// Error messages
$lang['AdminCompanyGroups.!error.delete_failed'] = "%1\$s is the default group and cannot be deleted."; // %1$s is the name of the client group


// Index
$lang['AdminCompanyGroups.index.page_title'] = "Settings > Company > Client Groups";
$lang['AdminCompanyGroups.index.boxtitle_groups'] = "Client Groups";
$lang['AdminCompanyGroups.index.categorylink_addgroup'] = "Create Group";

$lang['AdminCompanyGroups.index.text_name'] = "Name";
$lang['AdminCompanyGroups.index.text_description'] = "Description";
$lang['AdminCompanyGroups.index.text_clients'] = "Number of Clients";
$lang['AdminCompanyGroups.index.text_options'] = "Options";

$lang['AdminCompanyGroups.index.option_edit'] = "Edit";
$lang['AdminCompanyGroups.index.option_delete'] = "Delete";

$lang['AdminCompanyGroups.index.no_results'] = "There are no client groups.";

$lang['AdminCompanyGroups.index.confirm_delete'] = "Are you sure you want to delete this client group? All clients in this group will be moved to the default group.";


// Add group
$lang['AdminCompanyGroups.add.page_title'] = "Settings > Company > Client Groups > Create Group";
$lang['AdminCompanyGroups.add.boxtitle_addgroup'] = "Create Group";

$lang['AdminCompanyGroups.add.heading_basic'] = "Basic Options";
$lang['AdminCompanyGroups.add.heading_invoice'] = "Invoice and Charge Options";
$lang['AdminCompanyGroups.add.heading_delivery'] = "Invoice Delivery";
$lang['AdminCompanyGroups.add.heading_payment'] = "Payment Due Notices";

$lang['AdminCompanyGroups.add.field_name'] = "Name";
$lang['AdminCompanyGroups.add.field_color'] = "Color";
$lang['AdminCompanyGroups.add.field_description'] = "Description";
$lang['AdminCompanyGroups.add.field_delivery_methods'] = "Invoice Delivery Methods";
$lang['AdminCompanyGroups.add.field_company_settings'] = "Use Company Settings (uncheck to specify below)";

$lang['AdminCompanyGroups.add.text_addsubmit'] = "Create Group";


// Edit group
$lang['AdminCompanyGroups.edit.page_title'] = "Settings > Company > Client Groups > Edit Group";
$lang['AdminCompanyGroups.edit.boxtitle_editgroup'] = "Edit Group";

$lang['AdminCompanyGroups.edit.heading_basic'] = "Basic Options";
$lang['AdminCompanyGroups.edit.heading_invoice'] = "Invoice and Charge Options";
$lang['AdminCompanyGroups.edit.heading_delivery'] = "Invoice Delivery";
$lang['AdminCompanyGroups.edit.heading_payment'] = "Payment Due Notices";

$lang['AdminCompanyGroups.edit.field_name'] = "Name";
$lang['AdminCompanyGroups.edit.field_color'] = "Color";
$lang['AdminCompanyGroups.edit.field_description'] = "Description";
$lang['AdminCompanyGroups.edit.field_delivery_methods'] = "Invoice Delivery Methods";
$lang['AdminCompanyGroups.edit.field_company_settings'] = "Use Company Settings (uncheck to specify below)";

$lang['AdminCompanyGroups.edit.text_editsubmit'] = "Edit Group";
?>