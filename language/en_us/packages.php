<?php
/**
 * Language definitions for the Packages model
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Package errors
$lang['Packages.!error.company_id.exists'] = "Invalid company given.";
$lang['Packages.!error.package_id.exists'] = "The package could not be deleted because a service is currently using it.";
$lang['Packages.!error.module_id.exists'] = "Invalid module given.";
$lang['Packages.!error.name.empty'] = "Please enter a package name.";
$lang['Packages.!error.qty.format'] = "Quantity must be a number.";
$lang['Packages.!error.option_groups[].valid'] = "Invalid configurable package option group given.";
$lang['Packages.!error.module_row.format'] = "Invalid module row given.";
$lang['Packages.!error.module_group.format'] = "Invalid module group given.";
$lang['Packages.!error.taxable.format'] = "Taxable must be a number.";
$lang['Packages.!error.taxable.length'] = "The taxable length may nont exceed 1 character.";
$lang['Packages.!error.single_term.valid'] = "Single term must be either 1 or 0.";
$lang['Packages.!error.status.format'] = "Invalid status.";
$lang['Packages.!error.prorata_day.format'] = "The pro rata day must be between 1 and 28.";
$lang['Packages.!error.prorata_cutoff.format'] = "The pro rata cutoff day must be between 1 and 28.";

// Package email errors
$lang['Packages.!error.email_content[][lang].empty'] = "Please enter a language.";
$lang['Packages.!error.email_content[][lang].length'] = "The language length may not exceed 5 characters.";
$lang['Packages.!error.email_content.parse'] = "Template parse error: %1\$s"; // %1$s is the parse error generated

// Package pricing errors
$lang['Packages.!error.pricing[][term].format'] = "Term must be a number.";
$lang['Packages.!error.pricing[][term].length'] = "Term length may not exceed 5 characters.";
$lang['Packages.!error.pricing[][term].valid'] = "The term must be greater than 0.";
$lang['Packages.!error.pricing[][term].deletable'] = "The term could not be removed because it is used by one or more services.";
$lang['Packages.!error.pricing[][period].format'] = "Invalid period type.";
$lang['Packages.!error.pricing[][price].format'] = "Price must be a number.";
$lang['Packages.!error.pricing[][setup_fee].format'] = "Setup fee must be a number.";
$lang['Packages.!error.pricing[][cancel_fee].format'] = "Cancel fee must be a number.";
$lang['Packages.!error.pricing[][currency].format'] = "Currency code must be 3 characters.";
$lang['Packages.!error.pricing[][id].format'] = "Invalid package pricing ID.";

// Package group errors
$lang['Packages.!error.groups[].exists'] = "Invalid package group ID.";
$lang['Packages.!error.groups[].valid'] = "The package group selected does not belong to the company given.";


// Periods singular
$lang['Packages.getPricingPeriods.day'] = "Day";
$lang['Packages.getPricingPeriods.week'] = "Week";
$lang['Packages.getPricingPeriods.month'] = "Month";
$lang['Packages.getPricingPeriods.year'] = "Year";
$lang['Packages.getPricingPeriods.onetime'] = "One time";

// Periods plural
$lang['Packages.getPricingPeriods.day_plural'] = "Days";
$lang['Packages.getPricingPeriods.week_plural'] = "Weeks";
$lang['Packages.getPricingPeriods.month_plural'] = "Months";
$lang['Packages.getPricingPeriods.year_plural'] = "Years";
$lang['Packages.getPricingPeriods.onetime_plural'] = "One time";

$lang['Packages.getStatusTypes.active'] = "Active";
$lang['Packages.getStatusTypes.inactive'] = "Inactive";
$lang['Packages.getStatusTypes.restricted'] = "Restricted";
?>