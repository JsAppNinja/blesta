<?php
/**
 * Language definitions for the Admin Company General settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyGeneral.!success.localization_updated'] = "The localization settings have been successfully updated.";
$lang['AdminCompanyGeneral.!success.encryption_updated'] = "The encryption settings have been successfully updated.";
$lang['AdminCompanyGeneral.!success.contact_type_added'] = "The contact type \"%1\$s\" has been successfully added."; // %1$s is the name of the contact type
$lang['AdminCompanyGeneral.!success.contact_type_updated'] = "The contact type \"%1\$s\" has been successfully updated."; // %1$s is the name of the contact type
$lang['AdminCompanyGeneral.!success.contact_type_deleted'] = "The contact type \"%1\$s\" has been successfully deleted."; // %1$s is the name of the contact type

$lang['AdminCompanyGeneral.!success.language_installed'] = "The language %1\$s has been successfully installed."; // %1$s is the name of the language
$lang['AdminCompanyGeneral.!success.language_uninstalled'] = "The language %1\$s has been successfully uninstalled."; // %1$s is the name of the language


// Localization
$lang['AdminCompanyGeneral.localization.page_title'] = "Settings > Company > General > Localization";
$lang['AdminCompanyGeneral.localization.boxtitle_localization'] = "Localization";
$lang['AdminCompanyGeneral.localization.tz_format'] = "(UTC %1\$s) %2\$s"; // %1$s is the UTC offset, %2$s is the timezone name

$lang['AdminCompanyGeneral.localization.text_language'] = "Default Language";
$lang['AdminCompanyGeneral.localization.text_setlanguage'] = "Client may set Language";
$lang['AdminCompanyGeneral.localization.text_calendar'] = "Calendar Start Day";
$lang['AdminCompanyGeneral.localization.text_sunday'] = "Sunday";
$lang['AdminCompanyGeneral.localization.text_monday'] = "Monday";
$lang['AdminCompanyGeneral.localization.text_timezone'] = "Timezone";
$lang['AdminCompanyGeneral.localization.text_dateformat'] = "Date Format";
$lang['AdminCompanyGeneral.localization.text_datetimeformat'] = "Date Time Format";
$lang['AdminCompanyGeneral.localization.text_country'] = "Default Country";
$lang['AdminCompanyGeneral.localization.text_localizationsubmit'] = "Update Settings";


// Internationalization
$lang['AdminCompanyGeneral.!notice.international_languages'] = "A crowdsourced translation project exists at translate.blesta.com. You may contribute to, and download language translations there. To install, unzip the contents of the file to your Blesta installation directory. Then, refresh this page, and click the Install link.";
$lang['AdminCompanyGeneral.international.page_title'] = "Settings > Company > General > Internationalization";
$lang['AdminCompanyGeneral.international.boxtitle_international'] = "Internationalization";

$lang['AdminCompanyGeneral.international.text_language'] = "Language";
$lang['AdminCompanyGeneral.international.text_iso'] = "ISO 639-1, 3166-1";
$lang['AdminCompanyGeneral.international.text_options'] = "Options";

$lang['AdminCompanyGeneral.international.option_install'] = "Install";
$lang['AdminCompanyGeneral.international.option_uninstall'] = "Uninstall";

$lang['AdminCompanyGeneral.international.confirm_install'] = "Are you sure you want to install the language %1\$s?"; // %1$s is the name of the language
$lang['AdminCompanyGeneral.international.confirm_uninstall'] = "Are you sure you want to uninstall the language %1\$s? This language will be uninstalled and all email templates in this language will be permanently deleted."; // %1$s is the name of the language


// Encryption
$lang['AdminCompanyGeneral.encryption.page_title'] = "Settings > Company > General > Encryption";
$lang['AdminCompanyGeneral.!notice.passphrase'] = "WARNING: Setting a passphrase will prevent locally stored payment accounts from being automatically processed. You will be required to manually batch payments by entering your passphrase. For more information regarding this feature please consult the manual.";
$lang['AdminCompanyGeneral.!notice.passphrase_set'] = "WARNING: A passphrase has been set. You are required to manually batch payments with your passphrase. Changing your passphrase to a blank passphrase will remove this requirement.";

$lang['AdminCompanyGeneral.encryption.boxtitle_encryption'] = "Encryption";

$lang['AdminCompanyGeneral.encryption.field_current_passphrase'] = "Current Private Key Passphrase";
$lang['AdminCompanyGeneral.encryption.field_private_key_passphrase'] = "New Private Key Passphrase";
$lang['AdminCompanyGeneral.encryption.field_confirm_new_passphrase'] = "Confirm Private Key Passphrase";
$lang['AdminCompanyGeneral.encryption.field_agree'] = "I have saved this passphrase to a safe location";

$lang['AdminCompanyGeneral.encryption.field_encryptionsubmit'] = "Update Passphrase";


// Contact Types
$lang['AdminCompanyGeneral.contacttypes.page_title'] = "Settings > Company > General > Contact Types";
$lang['AdminCompanyGeneral.contacttypes.categorylink_addtype'] = "Create Contact Type";
$lang['AdminCompanyGeneral.contacttypes.boxtitle_types'] = "Contact Types";

$lang['AdminCompanyGeneral.contacttypes.heading_name'] = "Name";
$lang['AdminCompanyGeneral.contacttypes.heading_define'] = "Uses Language Definition";
$lang['AdminCompanyGeneral.contacttypes.heading_options'] = "Options";

$lang['AdminCompanyGeneral.contacttypes.text_yes'] = "Yes";
$lang['AdminCompanyGeneral.contacttypes.text_no'] = "No";
$lang['AdminCompanyGeneral.contacttypes.option_edit'] = "Edit";
$lang['AdminCompanyGeneral.contacttypes.option_delete'] = "Delete";

$lang['AdminCompanyGeneral.contacttypes.modal_delete'] = "Deleting this contact type will cause all contacts assigned to this type to be placed into the default \"Billing\" type. Are you sure you want to delete this contact type?";

$lang['AdminCompanyGeneral.contacttypes.no_results'] = "There are no Contact Types.";

$lang['AdminCompanyGeneral.!contacttypes.is_lang'] = "Only check this box if you have added a language definition for this contact type in the custom language file.";


// Add Contact Type
$lang['AdminCompanyGeneral.addcontacttype.page_title'] = "Settings > Company > General > Create Contact Type";
$lang['AdminCompanyGeneral.addcontacttype.boxtitle_addcontacttype'] = "Create Contact Type";

$lang['AdminCompanyGeneral.addcontacttype.field_name'] = "Name";
$lang['AdminCompanyGeneral.addcontacttype.field_is_lang'] = "Use Language Definition";
$lang['AdminCompanyGeneral.addcontacttype.field_contacttypesubmit'] = "Create Contact Type";


// Edit Contact Type
$lang['AdminCompanyGeneral.editcontacttype.page_title'] = "Settings > Company > General > Edit Contact Type";
$lang['AdminCompanyGeneral.editcontacttype.boxtitle_editcontacttype'] = "Edit Contact Type";

$lang['AdminCompanyGeneral.editcontacttype.field_name'] = "Name";
$lang['AdminCompanyGeneral.editcontacttype.field_is_lang'] = "Use Language Definition";
$lang['AdminCompanyGeneral.editcontacttype.field_contacttypesubmit'] = "Edit Contact Type";
?>