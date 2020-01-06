<?php
/**
 * Language definitions for the Admin Packages controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminPackages.!success.package_added'] = "The package was successfully created.";
$lang['AdminPackages.!success.package_updated'] = "The package was successfully updated.";
$lang['AdminPackages.!success.package_deleted'] = "The package was successfully deleted.";
$lang['AdminPackages.!success.group_added'] = "The package group \"%1\$s\" has been successfully created."; // %1$s is the package group name
$lang['AdminPackages.!success.group_updated'] = "The package group \"%1\$s\" has been successfully updated."; // %1$s is the package group name
$lang['AdminPackages.!success.group_deleted'] = "The package group\"%1\$s\" has been successfully deleted."; // %1$s is the package group name


// Index
$lang['AdminPackages.index.page_title'] = "Packages";
$lang['AdminPackages.index.boxtitle_packages'] = "Packages";
$lang['AdminPackages.index.categorylink_createpackage'] = "Create Package";

$lang['AdminPackages.index.heading_package'] = "Package ID";
$lang['AdminPackages.index.heading_name'] = "Name";
$lang['AdminPackages.index.heading_module'] = "Module";
$lang['AdminPackages.index.heading_qty'] = "Quantity";
$lang['AdminPackages.index.heading_options'] = "Options";

$lang['AdminPackages.index.category_active'] = "Active";
$lang['AdminPackages.index.category_restricted'] = "Restricted";
$lang['AdminPackages.index.category_inactive'] = "Inactive";

$lang['AdminPackages.index.option_edit'] = "Edit";
$lang['AdminPackages.index.option_copy'] = "Copy";
$lang['AdminPackages.index.option_delete'] = "Delete";

$lang['AdminPackages.index.no_results'] = "There are no packages with this status.";
$lang['AdminPackages.index.qty_unlimited'] = "Unlimited";

$lang['AdminPackages.index.confirm_delete'] = "Are you sure you want to delete this package?";


// Package pricing
$lang['AdminPackages.packagepricing.heading_pricing'] = "Pricing";
$lang['AdminPackages.packagepricing.heading_term'] = "Term";
$lang['AdminPackages.packagepricing.heading_price'] = "Price";
$lang['AdminPackages.packagepricing.heading_setup_fee'] = "Setup Fee";
$lang['AdminPackages.packagepricing.heading_cancellation_fee'] = "Cancellation Fee";
$lang['AdminPackages.packagepricing.pricing_no_results'] = "This package has no pricing details.";


// Tooltips
$lang['AdminPackages.!tooltip.prorata'] = "Pro rata allows a specific day of the month to be set for the renewal of services created with this package. Only monthly and yearly periods will be affected by this option.";
$lang['AdminPackages.!tooltip.prorata_day'] = "The day of the month this service will be set to renew when ordered.";
$lang['AdminPackages.!tooltip.prorata_cutoff'] = "Initial orders placed on or before this day of the month will be charged only for the partial month ending on the next occurring pro rata day. Orders placed after this day will be charged for the partial month and the subsequent service term.";


// Add
$lang['AdminPackages.add.page_title'] = "New Package";
$lang['AdminPackages.add.boxtitle_newpackage'] = "New Package";

$lang['AdminPackages.add.heading_basic'] = "Basic";
$lang['AdminPackages.add.heading_module'] = "Module Options";
$lang['AdminPackages.add.heading_pricing'] = "Pricing";
$lang['AdminPackages.add.heading_email'] = "Welcome Email";
$lang['AdminPackages.add.heading_groups'] = "Group Membership";

$lang['AdminPackages.add.field_module'] = "Module";
$lang['AdminPackages.add.field_packagename'] = "Package Name";
$lang['AdminPackages.add.field_status'] = "Status";
$lang['AdminPackages.add.field_qty'] = "Quantity Available";
$lang['AdminPackages.add.field_qty_unlimited'] = "Unlimited";
$lang['AdminPackages.add.field_activation'] = "Instant Activation";
$lang['AdminPackages.add.field_description'] = "Description";
$lang['AdminPackages.add.field_description_text'] = "Text";
$lang['AdminPackages.add.field_description_html'] = "HTML";
$lang['AdminPackages.add.field_configurable_options'] = "Configurable Options";
$lang['AdminPackages.add.field_taxable'] = "Taxable";
$lang['AdminPackages.add.field_single_term'] = "Cancel at end of term";
$lang['AdminPackages.add.field_modulegroup_any'] = "Any";
$lang['AdminPackages.add.field_prorata'] = "Enable Pro rata";
$lang['AdminPackages.add.field_prorata_day'] = "Pro rata Day";
$lang['AdminPackages.add.field_prorata_cutoff'] = "Pro rata Cutoff Day";

$lang['AdminPackages.add.text_term'] = "Term";
$lang['AdminPackages.add.text_period'] = "Period";
$lang['AdminPackages.add.text_currency'] = "Currency";
$lang['AdminPackages.add.text_price'] = "Price";
$lang['AdminPackages.add.text_setup'] = "Setup Fee";
$lang['AdminPackages.add.text_cancellation'] = "Cancellation Fee";
$lang['AdminPackages.add.text_options'] = "Options";
$lang['AdminPackages.add.text_remove'] = "Remove";
$lang['AdminPackages.add.text_none'] = "None";

$lang['AdminPackages.add.text_tags'] = "Tags:";
$lang['AdminPackages.add.text_group'] = "A package must belong to at least one group to be usable.";
$lang['AdminPackages.add.text_membergroups'] = "Member Groups";
$lang['AdminPackages.add.text_availablegroups'] = "Available Groups";

$lang['AdminPackages.add.field_email'] = "Welcome E-mail";

$lang['AdminPackages.add.field_packagesubmit'] = "Create Package";

$lang['AdminPackages.add.categorylink_addprice'] = "Add Additional Price";

$lang['AdminPackages.add.field_select_group_type'] = "Select from Available Groups";
$lang['AdminPackages.add.field_select_group_type_new'] = "Create a New Group";
$lang['AdminPackages.add.text_group_name'] = "Standard Package Group Name";


// Edit
$lang['AdminPackages.edit.page_title'] = "Update Package";
$lang['AdminPackages.edit.boxtitle_updatepackage'] = "Update Package";

$lang['AdminPackages.edit.heading_basic'] = "Basic";
$lang['AdminPackages.edit.heading_module'] = "Module Options";
$lang['AdminPackages.edit.heading_pricing'] = "Pricing";
$lang['AdminPackages.edit.heading_email'] = "Welcome Email";
$lang['AdminPackages.edit.heading_groups'] = "Group Membership";

$lang['AdminPackages.edit.field_module'] = "Module";
$lang['AdminPackages.edit.field_packagename'] = "Package Name";
$lang['AdminPackages.edit.field_status'] = "Status";
$lang['AdminPackages.edit.field_qty'] = "Quantity Available";
$lang['AdminPackages.edit.field_qty_unlimited'] = "Unlimited";
$lang['AdminPackages.edit.field_activation'] = "Instant Activation";
$lang['AdminPackages.edit.field_description'] = "Description";
$lang['AdminPackages.edit.field_description_text'] = "Text";
$lang['AdminPackages.edit.field_description_html'] = "HTML";
$lang['AdminPackages.edit.field_configurable_options'] = "Configurable Options";
$lang['AdminPackages.edit.field_taxable'] = "Taxable";
$lang['AdminPackages.edit.field_single_term'] = "Cancel at end of term";
$lang['AdminPackages.edit.field_modulegroup_any'] = "Any";
$lang['AdminPackages.edit.field_prorata'] = "Enable Pro rata";
$lang['AdminPackages.edit.field_prorata_day'] = "Pro rata Day";
$lang['AdminPackages.edit.field_prorata_cutoff'] = "Pro rata Cutoff Day";

$lang['AdminPackages.edit.text_term'] = "Term";
$lang['AdminPackages.edit.text_period'] = "Period";
$lang['AdminPackages.edit.text_currency'] = "Currency";
$lang['AdminPackages.edit.text_price'] = "Price";
$lang['AdminPackages.edit.text_setup'] = "Setup Fee";
$lang['AdminPackages.edit.text_cancellation'] = "Cancellation Fee";
$lang['AdminPackages.edit.text_options'] = "Options";
$lang['AdminPackages.edit.text_remove'] = "Remove";
$lang['AdminPackages.edit.text_none'] = "None";

$lang['AdminPackages.edit.text_tags'] = "Tags:";
$lang['AdminPackages.edit.text_group'] = "A package must belong to at least one group to be usable.";
$lang['AdminPackages.edit.text_membergroups'] = "Member Groups";
$lang['AdminPackages.edit.text_availablegroups'] = "Available Groups";

$lang['AdminPackages.edit.field_term'] = "Term";
$lang['AdminPackages.edit.field_setupfee'] = "Setup Fee";
$lang['AdminPackages.edit.field_cancelfee'] = "Cancel Fee";

$lang['AdminPackages.edit.field_email'] = "Welcome E-mail";

$lang['AdminPackages.edit.field_packagesubmit'] = "Update Package";

$lang['AdminPackages.edit.categorylink_addprice'] = "Add Additional Price";


// Package groups
$lang['AdminPackages.groups.page_title'] = "Package Groups";
$lang['AdminPackages.groups.category_standard'] = "Standard";
$lang['AdminPackages.groups.category_addon'] = "Add-on";
$lang['AdminPackages.groups.categorylink_creategroup'] = "Create Group";

$lang['AdminPackages.groups.boxtitle_packagegroups'] = "Package Groups";
$lang['AdminPackages.groups.heading_name'] = "Name";
$lang['AdminPackages.groups.heading_type'] = "Type";
$lang['AdminPackages.groups.heading_options'] = "Options";
$lang['AdminPackages.groups.option_edit'] = "Edit";
$lang['AdminPackages.groups.option_delete'] = "Delete";
$lang['AdminPackages.groups.confirm_delete'] = "Are you sure you want to delete this package group? Any packages assigned to this group will no longer be assigned to this group, and may become unusable.";

$lang['AdminPackages.groups.heading_parent_groups'] = "Parent Groups";
$lang['AdminPackages.groups.heading_group'] = "Group Name";
$lang['AdminPackages.groups.no_results'] = "There are no package groups.";
$lang['AdminPackages.groups.parents_no_results'] = "This add-on group has no parent package groups.";


$lang['AdminPackages.groups.heading_packages'] = "Member Packages";
$lang['AdminPackages.groups.heading_package_name'] = "Package Name";
$lang['AdminPackages.groups.packages_no_results'] = "This package group has no assigned packages.";


// Add Package Group
$lang['AdminPackages.addgroup.page_title'] = "New Package Group";
$lang['AdminPackages.addgroup.boxtitle_addgroup'] = "New Package Group";

$lang['AdminPackages.addgroup.field_name'] = "Name";
$lang['AdminPackages.addgroup.field_type'] = "Type";
$lang['AdminPackages.addgroup.field_description'] = "Description";
$lang['AdminPackages.addgroup.field_allow_upgrades'] = "Allow Upgrades/Downgrades between Packages within this Group";
$lang['AdminPackages.addgroup.text_parentgroups'] = "Parent Groups";
$lang['AdminPackages.addgroup.text_availablegroups'] = "Available Groups";

$lang['AdminPackages.addgroup.field_addgroupsubmit'] = "Create Group";


// Edit Package Group
$lang['AdminPackages.editgroup.page_title'] = "Update Package Group";
$lang['AdminPackages.editgroup.boxtitle_editgroup'] = "Update Package Group";

$lang['AdminPackages.editgroup.field_name'] = "Name";
$lang['AdminPackages.editgroup.field_type'] = "Type";
$lang['AdminPackages.editgroup.field_description'] = "Description";
$lang['AdminPackages.editgroup.field_allow_upgrades'] = "Allow Upgrades/Downgrades between Packages within this Group";
$lang['AdminPackages.editgroup.text_parentgroups'] = "Parent Groups";
$lang['AdminPackages.editgroup.text_availablegroups'] = "Available Groups";

$lang['AdminPackages.editgroup.field_editgroupsubmit'] = "Update Group";
?>