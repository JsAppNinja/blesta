<?php
/**
 * Language definitions for the Admin Company Taxes settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyTaxes.!success.basic_updated'] = "The Basic Tax settings were successfully updated!";
$lang['AdminCompanyTaxes.!success.taxrule_created'] = "The tax rule has been successfully created!";
$lang['AdminCompanyTaxes.!success.taxrule_updated'] = "The tax rule has been successfully updated!";
$lang['AdminCompanyTaxes.!success.rule_deleted'] = "The tax rule has been successfully deleted.";

$lang['AdminCompanyTaxes.countries.all'] = "-- All --";
$lang['AdminCompanyTaxes.states.all'] = "-- All --";


// Basic Tax settings
$lang['AdminCompanyTaxes.basic.page_title'] = "Settings > Company > Taxes > Basic Tax Settings";
$lang['AdminCompanyTaxes.basic.boxtitle_basic'] = "Basic Tax Settings";

$lang['AdminCompanyTaxes.basic.field_enable_tax'] = "Enable Tax";
$lang['AdminCompanyTaxes.basic.note_enable_tax'] = "Check this option to enable tax for this company.";
$lang['AdminCompanyTaxes.basic.field_cascade_tax'] = "Cascade Tax";
$lang['AdminCompanyTaxes.basic.note_cascade_tax'] = "If enabled, tax level 1 will first be assessed on the invoice total, and tax level 2 would be assessed on this new total including tax level 1. This results in a tax on tax. Othewise tax level 1 and tax level 2 are assessed only on the pre-tax invoice total.";
$lang['AdminCompanyTaxes.basic.field_setup_fee_tax'] = "Tax Setup Fees";
$lang['AdminCompanyTaxes.basic.note_setup_fee_tax'] = "If enabled, any setup fees will be taxed.";
$lang['AdminCompanyTaxes.basic.field_cancelation_fee_tax'] = "Tax Cancelation Fees";
$lang['AdminCompanyTaxes.basic.note_cancelation_fee_tax'] = "If enabled, any cancelation fees will be taxed.";
$lang['AdminCompanyTaxes.basic.field_taxid'] = "Tax ID/VATIN";

$lang['AdminCompanyTaxes.basic.field_addsubmit'] = "Update Settings";


// Tax Rules
$lang['AdminCompanyTaxes.rules.page_title'] = "Settings > Company > Taxes > Tax Rules";
$lang['AdminCompanyTaxes.rules.no_results'] = "There are no level %1\$s tax rules."; // %1$s is the tax level number

$lang['AdminCompanyTaxes.rules.categorylink_addrule'] = "Add Tax Rule";
$lang['AdminCompanyTaxes.rules.boxtitle_rules'] = "Tax Rules";

$lang['AdminCompanyTaxes.rules.heading_level1'] = "Level 1 Rules";
$lang['AdminCompanyTaxes.rules.heading_level2'] = "Level 2 Rules";

$lang['AdminCompanyTaxes.rules.text_name'] = "Name";
$lang['AdminCompanyTaxes.rules.text_type'] = "Type";
$lang['AdminCompanyTaxes.rules.text_amount'] = "Amount";
$lang['AdminCompanyTaxes.rules.text_country'] = "Country";
$lang['AdminCompanyTaxes.rules.text_state'] = "State/Province";
$lang['AdminCompanyTaxes.rules.text_options'] = "Options";
$lang['AdminCompanyTaxes.rules.text_all'] = "All";
$lang['AdminCompanyTaxes.rules.option_edit'] = "Edit";
$lang['AdminCompanyTaxes.rules.option_delete'] = "Delete";
$lang['AdminCompanyTaxes.rules.confirm_delete'] = "Are you sure you want to delete this tax rule?";


$lang['AdminCompanyTaxes.!tooltip.note_taxtype'] = "Inclusive will display the tax as part of the total. Exclusive will list the tax separately and not include it in the total.";

// Add Tax Rule
$lang['AdminCompanyTaxes.add.page_title'] = "Settings > Company > Taxes > Add Tax Rule";
$lang['AdminCompanyTaxes.add.boxtitle_add'] = "Add Tax Rule";

$lang['AdminCompanyTaxes.add.field_taxtype'] = "Tax Type";
$lang['AdminCompanyTaxes.add.field_taxlevel'] = "Tax Level";
$lang['AdminCompanyTaxes.add.field_level1'] = "Level 1";
$lang['AdminCompanyTaxes.add.field_level2'] = "Level 2";
$lang['AdminCompanyTaxes.add.field_name'] = "Name of Tax";
$lang['AdminCompanyTaxes.add.field_amount'] = "Amount";
$lang['AdminCompanyTaxes.add.field_country'] = "Country";
$lang['AdminCompanyTaxes.add.field_state'] = "State/Province";

$lang['AdminCompanyTaxes.add.field_addsubmit'] = "Create Rule";


// Edit Tax Rule
$lang['AdminCompanyTaxes.edit.page_title'] = "Settings > Company > Taxes > Edit Tax Rule";
$lang['AdminCompanyTaxes.edit.boxtitle_edit'] = "Edit Tax Rule";

$lang['AdminCompanyTaxes.edit.field_taxtype'] = "Tax Type";
$lang['AdminCompanyTaxes.edit.field_taxlevel'] = "Tax Level";
$lang['AdminCompanyTaxes.edit.field_level1'] = "Level 1";
$lang['AdminCompanyTaxes.edit.field_level2'] = "Level 2";
$lang['AdminCompanyTaxes.edit.field_name'] = "Name of Tax";
$lang['AdminCompanyTaxes.edit.field_amount'] = "Amount";
$lang['AdminCompanyTaxes.edit.field_country'] = "Country";
$lang['AdminCompanyTaxes.edit.field_state'] = "State/Province";

$lang['AdminCompanyTaxes.edit.field_editsubmit'] = "Edit Rule";
?>