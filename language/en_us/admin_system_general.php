<?php
/**
 * Language definitions for the Admin System General settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminSystemGeneral.!success.basic_updated'] = "The Basic Setup settings were successfully updated!";
$lang['AdminSystemGeneral.!success.geoip_updated'] = "The GeoIP settings were successfully updated!";
$lang['AdminSystemGeneral.!success.maintenance_updated'] = "The Maintenance settings were successfully updated!";
$lang['AdminSystemGeneral.!success.license_updated'] = "Your License Key has been successfully updated!";
$lang['AdminSystemGeneral.!success.addtype_created'] = "The payment type \"%1\$s\" has been successfully created!"; // %1$s is the name of the payment type
$lang['AdminSystemGeneral.!success.edittype_updated'] = "The payment type \"%1\$s\" has been successfully updated!"; // %1$s is the name of the payment type
$lang['AdminSystemGeneral.!success.deletetype_deleted'] = "The payment type \"%1\$s\" has been successfully deleted!"; // %1$s is the name of the payment type

// Error messages
$lang['AdminSystemGeneral.!error.geoip_mbstring_required'] = "The mbstring extension is required for this feature.";


// Basic settings
$lang['AdminSystemGeneral.basic.page_title'] = "Settings > System > General > Basic Setup";
$lang['AdminSystemGeneral.basic.boxtitle_basic'] = "Basic Setup";
$lang['AdminSystemGeneral.basic.field_root_web_dir'] = "Root Web Directory";
$lang['AdminSystemGeneral.basic.field_tempdir'] = "Temp Directory";
$lang['AdminSystemGeneral.basic.field_uploadsdir'] = "Uploads Directory";
$lang['AdminSystemGeneral.basic.field_log_days'] = "Rotation Policy";
$lang['AdminSystemGeneral.basic.field_basicsubmit'] = "Update Settings";

$lang['AdminSystemGeneral.basic.text_writable'] = "Writable";
$lang['AdminSystemGeneral.basic.text_unwritable'] = "Not Writable";
$lang['AdminSystemGeneral.basic.text_no_log'] = "Never rotate Log";
$lang['AdminSystemGeneral.basic.text_day'] = "Day";
$lang['AdminSystemGeneral.basic.text_days'] = "Days";


// GeoIP settings
$lang['AdminSystemGeneral.geoip.page_title'] = "Settings > System > General > GeoIP Settings";
$lang['AdminSystemGeneral.geoip.boxtitle_geoip'] = "GeoIP";

$lang['AdminSystemGeneral.geoip.text_setup'] = "GeoIP can be enabled here, giving GeoIP location services functionality to Blesta. Enabling it will allow certain features to take advantage of location services.";
$lang['AdminSystemGeneral.geoip.text_geolite'] = "GeoIP requires the GeoLite City binary database, which can be downloaded from <a href=\"%1\$s\">%1\$s</a>. The file should be unzipped and uploaded to %2\$s"; // %1$s is the URL to the GeoIP database file, %2$s is the filesystem path to the GeoIP database file
$lang['AdminSystemGeneral.geoip.text_database_exists'] = "%1\$s exists."; // %1$s is a file system path to the GeoIP database file
$lang['AdminSystemGeneral.geoip.text_database_not_exists'] = "%1\$s does not exist."; // %1$s is a file system path to the GeoIP database file

$lang['AdminSystemGeneral.geoip.field_geoip_enabled'] = "Enable GeoIP";
$lang['AdminSystemGeneral.geoip.field_geoipsubmit'] = "Update Settings";


// Maintenance Settings
$lang['AdminSystemGeneral.maintenance.page_title'] = "Settings > System > General > Maintenance";
$lang['AdminSystemGeneral.maintenance.boxtitle_maintenance'] = "Maintenance";

$lang['AdminSystemGeneral.maintenance.field_maintenancemode'] = "Enable Maintenance Mode";
$lang['AdminSystemGeneral.maintenance.field_maintenancereason'] = "Reason for Maintenance";
$lang['AdminSystemGeneral.maintenance.field_maintenancesubmit'] = "Update Settings";


// License Key Settings
$lang['AdminSystemGeneral.license.page_title'] = "Settings > System > General > License Key";
$lang['AdminSystemGeneral.license.boxtitle_license'] = "License Key";

$lang['AdminSystemGeneral.license.field_license'] = "License Key";
$lang['AdminSystemGeneral.license.field_licensesubmit'] = "Update Settings";


// Payment Types
$lang['AdminSystemGeneral.paymenttypes.page_title'] = "Settings > System > General > Payment Types";
$lang['AdminSystemGeneral.paymenttypes.categorylink_addtype'] = "Create Payment Type";
$lang['AdminSystemGeneral.paymenttypes.boxtitle_types'] = "Payment Types";

$lang['AdminSystemGeneral.paymenttypes.heading_name'] = "Name";
$lang['AdminSystemGeneral.paymenttypes.heading_type'] = "Type";
$lang['AdminSystemGeneral.paymenttypes.heading_is_lang'] = "Uses Language Definition";
$lang['AdminSystemGeneral.paymenttypes.heading_options'] = "Options";

$lang['AdminSystemGeneral.paymenttypes.option_edit'] = "Edit";
$lang['AdminSystemGeneral.paymenttypes.option_delete'] = "Delete";

$lang['AdminSystemGeneral.paymenttypes.modal_delete'] = "Deleting this payment type will cause all transactions that use this payment type to be set to \"other\". Are you sure you want to delete this payment type?";

$lang['AdminSystemGeneral.paymenttypes.text_yes'] = "Yes";
$lang['AdminSystemGeneral.paymenttypes.text_no'] = "No";

$lang['AdminSystemGeneral.paymenttypes.no_results'] = "There are no payment types.";

$lang['AdminSystemGeneral.!paymenttypes.is_lang'] = "Only check this box if you have added a language definition for this payment type in the custom language file.";
$lang['AdminSystemGeneral.!paymenttypes.type'] = "When set to debit, transactions using this payment type are considered income-based while credit is non-income-based.";


// Add payment type
$lang['AdminSystemGeneral.addtype.page_title'] = "Settings > System > General > Create Payment Type";
$lang['AdminSystemGeneral.addtype.boxtitle_addtype'] = "Create Payment Type";

$lang['AdminSystemGeneral.addtype.field_name'] = "Name";
$lang['AdminSystemGeneral.addtype.field_type'] = "Type";
$lang['AdminSystemGeneral.addtype.field_is_lang'] = "Use Language Definition";

$lang['AdminSystemGeneral.addtype.field_typesubmit'] = "Create Payment Type";


// Edit payment type
$lang['AdminSystemGeneral.edittype.page_title'] = "Settings > System > General > Edit Payment Type";
$lang['AdminSystemGeneral.edittype.boxtitle_edittype'] = "Edit Payment Type";

$lang['AdminSystemGeneral.edittype.field_name'] = "Name";
$lang['AdminSystemGeneral.edittype.field_type'] = "Type";
$lang['AdminSystemGeneral.edittype.field_is_lang'] = "Use Language Definition";

$lang['AdminSystemGeneral.edittype.field_typesubmit'] = "Edit Payment Type";
?>