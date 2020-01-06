<?php
/**
 * Language definitions for the Package Options model
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Errors
$lang['PackageOptions.!error.company_id.exists'] = "Invalid company ID.";
$lang['PackageOptions.!error.label.empty'] = "Please enter a label.";
$lang['PackageOptions.!error.label.length'] = "The label may not exceed 128 characters in length.";
$lang['PackageOptions.!error.name.empty'] = "Please enter a name for this option.";
$lang['PackageOptions.!error.name.length'] = "The name may not exceed 128 characters in length.";
$lang['PackageOptions.!error.type.valid'] = "Please select an option type.";
$lang['PackageOptions.!error.values.count'] = "There may only be one option value entry for checkbox or quantity types.";
$lang['PackageOptions.!error.values.select_value'] = "At least one option value contains invalid special characters.";
$lang['PackageOptions.!error.values[][step].valid'] = "A step value may only be set for the quantity type, and must be a value of 1 or greater.";
$lang['PackageOptions.!error.values[][min].valid'] = "A minimum limit value may only be set for the quantity type, and must be a value of 0 or greater.";
$lang['PackageOptions.!error.values[][max].valid'] = "The maximum limit value may only be set for the quantity type, and must be a value of 1 or greater.";
$lang['PackageOptions.!error.values[][name].empty'] = "Please enter a name for the option value.";
$lang['PackageOptions.!error.values[][name].length'] = "The option value name may not exceed 128 characters in length.";
$lang['PackageOptions.!error.values[][value].length'] = "The option value may not exceed 255 characters in length.";
$lang['PackageOptions.!error.values[][id].exists'] = "Invalid package option value ID.";
$lang['PackageOptions.!error.groups.exists'] = "At least one of the package option group IDs given does not exist or does not belong to the same company.";
$lang['PackageOptions.!error.option_id.exists'] = "Invalid package option ID.";


// Pricing errors
$lang['PackageOptions.!error.values[][pricing][][id].exists'] = "Invalid package option pricing ID.";
$lang['PackageOptions.!error.values[][pricing][][term].format'] = "Term must be a number.";
$lang['PackageOptions.!error.values[][pricing][][term].length'] = "Term length may not exceed 5 characters.";
$lang['PackageOptions.!error.values[][pricing][][term].valid'] = "The term must be greater than 0.";
$lang['PackageOptions.!error.values[][pricing][][period].format'] = "Invalid period type.";
$lang['PackageOptions.!error.values[][pricing][][price].format'] = "Price must be a number.";
$lang['PackageOptions.!error.values[][pricing][][setup_fee].format'] = "Setup fee must be a number.";
$lang['PackageOptions.!error.values[][pricing][][currency].format'] = "Currency code must be 3 characters.";


// Types
$lang['PackageOptions.gettypes.checkbox'] = "Checkbox";
$lang['PackageOptions.gettypes.select'] = "Drop-down";
$lang['PackageOptions.gettypes.quantity'] = "Quantity";
$lang['PackageOptions.gettypes.radio'] = "Radio";

// Fields
$lang['PackageOptions.getfields.label_quantity'] = "x %1\$s %2\$s"; // %1$s is the option value, %2$s is the option price
$lang['PackageOptions.getfields.label_quantity_setup'] = "x %1\$s %2\$s + %3\$s setup"; // %1$s is the option value, %2$s is the option price, %3$s is the setup fee
$lang['PackageOptions.getfields.label_radio'] = "%1\$s (%2\$s)"; // %1$s is the option value, %2$s is the option price
$lang['PackageOptions.getfields.label_radio_setup'] = "%1\$s (%2\$s + %3\$s setup)"; // %1$s is the option value, %2$s is the option price, %3$s is the setup fee
$lang['PackageOptions.getfields.label_select'] = "%1\$s (%2\$s)"; // %1$s is the option value, %2$s is the option price
$lang['PackageOptions.getfields.label_select_setup'] = "%1\$s (%2\$s + %3\$s setup)"; // %1$s is the option value, %2$s is the option price, %3$s is the setup fee
$lang['PackageOptions.getfields.label_checkbox'] = "%1\$s (%2\$s)"; // %1$s is the option value, %2$s is the option price
$lang['PackageOptions.getfields.label_checkbox_setup'] = "%1\$s (%2\$s + %3\$s setup)"; // %1$s is the option value, %2$s is the option price, %3$s is the setup fee
?>