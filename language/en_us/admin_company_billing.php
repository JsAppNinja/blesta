<?php
/**
 * Language definitions for the Admin Company Billing settings controller/views
 *
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyBilling.!success.invoices_updated'] = "The Invoice and Charge settings were successfully updated!";
$lang['AdminCompanyBilling.!success.notices_updated'] = "The Payment Due Notices were successfully updated!";
$lang['AdminCompanyBilling.!success.coupon_created'] = "The coupon has been successfully created!";
$lang['AdminCompanyBilling.!success.coupon_updated'] = "The coupon has been successfully updated!";
$lang['AdminCompanyBilling.!success.coupon_deleted'] = "The coupon has been successfully deleted!";
$lang['AdminCompanyBilling.!success.acceptedtypes_updated'] = "The Accepted Payment Type settings were successfully updated!";
$lang['AdminCompanyBilling.!success.deliverymethods_updated'] = "The Invoice Delivery settings were successfully updated!";
$lang['AdminCompanyBilling.!success.customization_updated'] = "The Invoice Customization settings were successfully updated!";


// Tooltips
$lang['AdminCompanyBilling.!tooltip.coupon_quantity'] = "The quantity represents the maximum number of times this coupon can be used before it is expired.";
$lang['AdminCompanyBilling.!tooltip.coupon_package_type'] = "Selecting \"Exclusive\" indicates that any individual assigned package may use this coupon. Selecting \"Inclusive\" indicates that this coupon can only be applied to all assigned packages together.";
$lang['AdminCompanyBilling.!tooltip.send_payment_notices'] = "This option sets whether clients can be sent any of the available payment notices.";

// Notices
$lang['AdminCompanyBilling.!notice.group_settings'] = "NOTE: These settings only apply to Client Groups that inherit their settings from the Company.";


// Invoices and Charge settings
$lang['AdminCompanyBilling.invoices.page_title'] = "Settings > Company > Billing/Payment > Invoice and Charge Options";
$lang['AdminCompanyBilling.invoices.boxtitle_invoices'] = "Invoice and Charge Options";

$lang['AdminCompanyBilling.invoices.field_invoicedays'] = "Invoice Days Before Renewal";
$lang['AdminCompanyBilling.invoices.field_autodebitdays'] = "Auto Debit Days Before Due Date";
$lang['AdminCompanyBilling.invoices.field_suspenddays'] = "Suspend Services Days After Due";
$lang['AdminCompanyBilling.invoices.field_autodebit_attempts'] = "Auto Debit Attempts";
$lang['AdminCompanyBilling.invoices.note_autodebit_attempts'] = "The number of attempts and failures to process a payment account before that payment account is disabled from being automatically debited.";
$lang['AdminCompanyBilling.invoices.field_autodebit'] = "Enable Auto Debit";
$lang['AdminCompanyBilling.invoices.field_client_invoice'] = "Allow Client to Set Invoice Method";
$lang['AdminCompanyBilling.invoices.field_suspend_services'] = "Invoice Suspended Services";
$lang['AdminCompanyBilling.invoices.field_cancel_services'] = "Allow Clients to Cancel Services";
$lang['AdminCompanyBilling.invoices.field_client_create_addons'] = "Allow Clients to Create Addons for Existing Services";
$lang['AdminCompanyBilling.invoices.field_client_change_service_term'] = "Allow Clients to Change Service Terms";
$lang['AdminCompanyBilling.invoices.field_client_change_service_package'] = "Allow Clients to Change Service Package";
$lang['AdminCompanyBilling.invoices.field_client_prorate_credits'] = "Allow Prorated Credits to be Issued for Service Downgrades";
$lang['AdminCompanyBilling.invoices.field_invoicessubmit'] = "Update Settings";
$lang['AdminCompanyBilling.invoices.field_auto_apply_credits'] = "Automatically Apply Loose Credits";
$lang['AdminCompanyBilling.invoices.field_auto_paid_pending_services'] = "Automatically Provision Paid Pending Services";
$lang['AdminCompanyBilling.invoices.field_show_client_tax_id'] = "Show the Tax ID Field in the Client Interface";
$lang['AdminCompanyBilling.invoices.field_cancel_service_changes_days'] = "Cancel Service Changes Days After Due";
$lang['AdminCompanyBilling.invoices.note_cancel_service_changes_days'] = "Queued service changes will be automatically canceled when their invoice goes unpaid for the selected number of days.";
$lang['AdminCompanyBilling.invoices.field_process_paid_service_changes'] = "Queue Service Changes Until Paid";
$lang['AdminCompanyBilling.invoices.note_process_paid_service_changes'] = "If checked, service changes (i.e. upgrades/downgrades) will be queued and provisioned only after they have been paid. Otherwise, they will be provisioned immediately.";
$lang['AdminCompanyBilling.invoices.field_inv_group_services'] = "Invoice Services Together";
$lang['AdminCompanyBilling.invoices.note_inv_group_services'] = "Creates a single invoice for services that renew on the same day for a client. Unchecking will create a separate invoice for each service.";

$lang['AdminCompanyBilling.invoices.text_never'] = "Never";
$lang['AdminCompanyBilling.invoices.text_sameday'] = "Same Day";
$lang['AdminCompanyBilling.invoices.text_day'] = "%1\$s Day"; // %1$s is the number 1
$lang['AdminCompanyBilling.invoices.text_days'] = "%1\$s Days"; // %1$s is a number of days that is not 1


// Notices
$lang['AdminCompanyBilling.notices.page_title'] = "Settings > Company > Billing/Payment > Payment Due Notices";
$lang['AdminCompanyBilling.notices.boxtitle_notices'] = "Notices";

$lang['AdminCompanyBilling.notices.text_notices'] = "Notices can be used as late notices, or payment reminders.";
$lang['AdminCompanyBilling.notices.text_before'] = "Before";
$lang['AdminCompanyBilling.notices.text_after'] = "After";
$lang['AdminCompanyBilling.notices.text_inv_duedate'] = "Invoice Due Date";
$lang['AdminCompanyBilling.notices.text_day'] = "%1\$s Day"; // %1$s is the number 1
$lang['AdminCompanyBilling.notices.text_days'] = "%1\$s Days"; // %1$s is a number of days that is not 1
$lang['AdminCompanyBilling.notices.text_duedate'] = "Due Date";
$lang['AdminCompanyBilling.notices.text_disabled'] = "Disabled";
$lang['AdminCompanyBilling.notices.text_edit_template'] = "Edit Email Template";

$lang['AdminCompanyBilling.notices.field_send_payment_notices'] = "Allow Payment Notices to be Sent";
$lang['AdminCompanyBilling.notices.field_first_notice'] = "First Notice";
$lang['AdminCompanyBilling.notices.field_second_notice'] = "Second Notice";
$lang['AdminCompanyBilling.notices.field_third_notice'] = "Third Notice";
$lang['AdminCompanyBilling.notices.field_notice_pending_autodebit'] = "Auto-Debit Pending Notice";
$lang['AdminCompanyBilling.notices.field_noticessubmit'] = "Update Settings";


// Coupons
$lang['AdminCompanyBilling.coupons.page_title'] = "Settings > Company > Billing/Payment > Coupons";
$lang['AdminCompanyBilling.coupons.no_results'] = "There are no coupons.";

$lang['AdminCompanyBilling.coupons.categorylink_addcoupon'] = "Add Coupon";

$lang['AdminCompanyBilling.coupons.boxtitle_coupons'] = "Coupons";

$lang['AdminCompanyBilling.coupons.text_code'] = "Code";
$lang['AdminCompanyBilling.coupons.text_discount'] = "Discount";
$lang['AdminCompanyBilling.coupons.text_used'] = "Used";
$lang['AdminCompanyBilling.coupons.text_max'] = "Max";
$lang['AdminCompanyBilling.coupons.text_start_date'] = "Start Date";
$lang['AdminCompanyBilling.coupons.text_end_date'] = "End Date";
$lang['AdminCompanyBilling.coupons.text_options'] = "Options";
$lang['AdminCompanyBilling.coupons.text_currency'] = "Currency";

$lang['AdminCompanyBilling.coupons.text_multiple'] = "Multiple";

$lang['AdminCompanyBilling.coupons.option_edit'] = "Edit";
$lang['AdminCompanyBilling.coupons.option_delete'] = "Delete";

$lang['AdminCompanyBilling.coupons.confirm_delete'] = "Are you sure you want to delete this coupon?";


// Add coupon
$lang['AdminCompanyBilling.addcoupon.page_title'] = "Settings > Company > Billing/Payment > New Coupon";
$lang['AdminCompanyBilling.addcoupon.boxtitle_new'] = "New Coupon";
$lang['AdminCompanyBilling.addcoupon.heading_basic'] = "Basic";

$lang['AdminCompanyBilling.addcoupon.field_status'] = "Enabled";
$lang['AdminCompanyBilling.addcoupon.field_recurring_no'] = "Apply when service is added only";
$lang['AdminCompanyBilling.addcoupon.field_recurring_yes'] = "Apply when service is added or renews";
$lang['AdminCompanyBilling.addcoupon.field_apply_package_options'] = "Apply to Configurable Options";
$lang['AdminCompanyBilling.addcoupon.field_code'] = "Coupon Code";

$lang['AdminCompanyBilling.addcoupon.text_generate_code'] = "Generate code";

$lang['AdminCompanyBilling.addcoupon.heading_limitations'] = "Limitations";

$lang['AdminCompanyBilling.addcoupon.field_start_date'] = "Start Date";
$lang['AdminCompanyBilling.addcoupon.field_end_date'] = "End Date";
$lang['AdminCompanyBilling.addcoupon.field_max_qty'] = "Quantity";
$lang['AdminCompanyBilling.addcoupon.field_limit_recurring_no'] = "Limitations do not apply to renewing services";
$lang['AdminCompanyBilling.addcoupon.field_limit_recurring_yes'] = "Limitations do apply to renewing services";

$lang['AdminCompanyBilling.addcoupon.heading_discount'] = "Discount Options";

$lang['AdminCompanyBilling.addcoupon.categorylink_addcurrency'] = "Add Additional Currency";

$lang['AdminCompanyBilling.addcoupon.text_currency'] = "Currency";
$lang['AdminCompanyBilling.addcoupon.text_type'] = "Type";
$lang['AdminCompanyBilling.addcoupon.text_value'] = "Value";

$lang['AdminCompanyBilling.addcoupon.option_remove'] = "Remove";

$lang['AdminCompanyBilling.addcoupon.heading_packages'] = "Packages";

$lang['AdminCompanyBilling.addcoupon.field_package_group_id'] = "Package Group Filter";
$lang['AdminCompanyBilling.addcoupon.field_type_inclusive'] = "Inclusive";
$lang['AdminCompanyBilling.addcoupon.field_type_exclusive'] = "Exclusive";
$lang['AdminCompanyBilling.addcoupon.field_couponsubmit'] = "Create Coupon";

$lang['AdminCompanyBilling.addcoupon.text_all'] = "All";
$lang['AdminCompanyBilling.addcoupon.text_assigned_packages'] = "Assigned Packages";
$lang['AdminCompanyBilling.addcoupon.text_available_packages'] = "Available Packages";


// Edit coupon
$lang['AdminCompanyBilling.editcoupon.page_title'] = "Settings > Company > Billing/Payment > Edit Coupon";
$lang['AdminCompanyBilling.editcoupon.boxtitle_edit'] = "Edit Coupon";

$lang['AdminCompanyBilling.editcoupon.heading_basic'] = "Basic";

$lang['AdminCompanyBilling.editcoupon.field_recurring_no'] = "Apply when service is added only";
$lang['AdminCompanyBilling.editcoupon.field_recurring_yes'] = "Apply when service is added or renews";
$lang['AdminCompanyBilling.editcoupon.field_apply_package_options'] = "Apply to Configurable Options";
$lang['AdminCompanyBilling.editcoupon.field_code'] = "Coupon Code";

$lang['AdminCompanyBilling.editcoupon.text_generate_code'] = "Generate code";

$lang['AdminCompanyBilling.editcoupon.heading_limitations'] = "Limitations";

$lang['AdminCompanyBilling.editcoupon.field_start_date'] = "Start Date";
$lang['AdminCompanyBilling.editcoupon.field_end_date'] = "End Date";
$lang['AdminCompanyBilling.editcoupon.field_max_qty'] = "Quantity";
$lang['AdminCompanyBilling.editcoupon.field_limit_recurring_no'] = "Limitations do not apply to renewing services";
$lang['AdminCompanyBilling.editcoupon.field_limit_recurring_yes'] = "Limitations do apply to renewing services";

$lang['AdminCompanyBilling.editcoupon.heading_discount'] = "Discount Options";

$lang['AdminCompanyBilling.editcoupon.categorylink_addcurrency'] = "Add Additional Currency";

$lang['AdminCompanyBilling.editcoupon.text_currency'] = "Currency";
$lang['AdminCompanyBilling.editcoupon.text_type'] = "Type";
$lang['AdminCompanyBilling.editcoupon.text_value'] = "Value";

$lang['AdminCompanyBilling.editcoupon.option_remove'] = "Remove";

$lang['AdminCompanyBilling.editcoupon.heading_packages'] = "Packages";

$lang['AdminCompanyBilling.editcoupon.field_package_group_id'] = "Package Group Filter";
$lang['AdminCompanyBilling.editcoupon.field_type_inclusive'] = "Inclusive";
$lang['AdminCompanyBilling.editcoupon.field_type_exclusive'] = "Exclusive";
$lang['AdminCompanyBilling.editcoupon.field_couponsubmit'] = "Edit Coupon";

$lang['AdminCompanyBilling.editcoupon.text_all'] = "All";
$lang['AdminCompanyBilling.editcoupon.text_assigned_packages'] = "Assigned Packages";
$lang['AdminCompanyBilling.editcoupon.text_available_packages'] = "Available Packages";
$lang['AdminCompanyBilling.editcoupon.text_used_qty'] = "(used %1\$s)"; // %1$s is the number of used coupons


// Invoice Customization
$lang['AdminCompanyBilling.customization.page_title'] = "Settings > Company > Billing/Payment > Invoice Customization";
$lang['AdminCompanyBilling.customization.boxtitle_customization'] = "Invoice Customization";
$lang['AdminCompanyBilling.customization.heading_general'] = "Basic Options";
$lang['AdminCompanyBilling.customization.heading_lookandfeel'] = "Look and Feel";

$lang['AdminCompanyBilling.customization.field_inv_format'] = "Invoice Format";
$lang['AdminCompanyBilling.customization.field_inv_draft_format'] = "Invoice Draft Format";
$lang['AdminCompanyBilling.customization.field_inv_proforma_format'] = "Pro Forma Invoice Format";
$lang['AdminCompanyBilling.customization.field_inv_start'] = "Invoice Start Value";
$lang['AdminCompanyBilling.customization.field_inv_proforma_start'] = "Pro Forma Invoice Start Value";
$lang['AdminCompanyBilling.customization.field_inv_increment'] = "Invoice Increment Value";
$lang['AdminCompanyBilling.customization.field_inv_pad_size'] = "Invoice Padding Size";
$lang['AdminCompanyBilling.customization.field_inv_pad_str'] = "Invoice Padding Character";
$lang['AdminCompanyBilling.customization.field_inv_type'] = "Invoice Type";

$lang['AdminCompanyBilling.customization.field_inv_logo'] = "Logo";
$lang['AdminCompanyBilling.customization.field_inv_background'] = "Background";
$lang['AdminCompanyBilling.customization.field_inv_terms'] = "Terms";
$lang['AdminCompanyBilling.customization.field_inv_paper_size'] = "Paper Size";
$lang['AdminCompanyBilling.customization.field_inv_template'] = "Invoice Template";
$lang['AdminCompanyBilling.customization.field_inv_display'] = "Display on Invoice";
$lang['AdminCompanyBilling.customization.field_inv_display_logo'] = "Logo";
$lang['AdminCompanyBilling.customization.field_inv_display_company'] = "Company Name/Address";
$lang['AdminCompanyBilling.customization.field_inv_display_paid_watermark'] = "PAID Watermark";
$lang['AdminCompanyBilling.customization.field_inv_display_payments'] = "Payments/Credits";
$lang['AdminCompanyBilling.customization.field_inv_display_due_date_draft'] = "Date Due - Drafts";
$lang['AdminCompanyBilling.customization.field_inv_display_due_date_proforma'] = "Date Due - Pro Forma";
$lang['AdminCompanyBilling.customization.field_inv_display_due_date_inv'] = "Date Due - Standard";
$lang['AdminCompanyBilling.customization.field_inv_mimetype'] = "Invoice File Type";
$lang['AdminCompanyBilling.customization.field_inv_font'] = "Font Family";
$lang['AdminCompanyBilling.customization.note_inv_font'] = "For additional fonts, unpack your custom TCPDF fonts to the /vendors/tcpdf/fonts/ directory within your installation.";
$lang['AdminCompanyBilling.customization.remove'] = "Remove";

$lang['AdminCompanyBilling.customization.field_customizationsubmit'] = "Update Settings";

$lang['AdminCompanyBilling.customization.note_inv_format'] = "Available tags include: {num} - the invoice number (required); {year} - the four-digit year; {month} - the two-digit month; {day} - the two-digit day of the month.";
$lang['AdminCompanyBilling.customization.note_inv_draft_format'] = "Available tags include: {num} - the invoice number (required); {year} - the four-digit year;  {month} - the two-digit month; {day} - the two-digit day of the month.";
$lang['AdminCompanyBilling.customization.note_inv_proforma_format'] = "Available tags include: {num} - the invoice number (required); {year} - the four-digit year;  {month} - the two-digit month; {day} - the two-digit day of the month.";
$lang['AdminCompanyBilling.customization.note_inv_start'] = "Invoice numbers will begin (and increment) from this starting value.";
$lang['AdminCompanyBilling.customization.note_inv_proforma_start'] = "Invoice numbers will begin (and increment) from this starting value.";
$lang['AdminCompanyBilling.customization.note_inv_increment'] = "Subsequent invoice numbers will increment by this value.";
$lang['AdminCompanyBilling.customization.note_inv_pad_size'] = "The invoice padding size sets the minimum character length of invoice numbers.";
$lang['AdminCompanyBilling.customization.note_inv_pad_str'] = "Invoice numbers whose character length is fewer than the invoice padding size will be padded to the left by the given character.";


// Accepted Payment Types
$lang['AdminCompanyBilling.acceptedtypes.page_title'] = "Settings > Company > Billing/Payment > Accepted Payment Types";
$lang['AdminCompanyBilling.acceptedtypes.boxtitle_types'] = "Accepted Payment Types";

$lang['AdminCompanyBilling.acceptedtypes.field_cc'] = "Credit Card";
$lang['AdminCompanyBilling.acceptedtypes.field_ach'] = "Automated Clearing House";
$lang['AdminCompanyBilling.acceptedtypes.field_typessubmit'] = "Update Settings";
$lang['AdminCompanyBilling.acceptedtypes.text_description'] = "Only the payment types selected are available for processing through gateways, or may be added as payment accounts, even if an active gateway supports the type. Unchecking a type that is already accepted will cause payments of that type to not be processed.";


// Invlice Delivery Methods
$lang['AdminCompanyBilling.deliverymethods.page_title'] = "Settings > Company > Billing/Payment > Invoice Delivery";
$lang['AdminCompanyBilling.deliverymethods.boxtitle_deliverymethods'] = "Invoice Delivery";
$lang['AdminCompanyBilling.deliverymethods.heading_basic'] = "Basic Options";
$lang['AdminCompanyBilling.deliverymethods.heading_interfax'] = "InterFax";
$lang['AdminCompanyBilling.deliverymethods.interfax_desc'] = "InterFax allows you to fax invoices over the internet. <a href=\"http://www.interfax.net/\" target=\"_blank\">Sign up</a> for an InterFax account and start faxing invoices today.";
$lang['AdminCompanyBilling.deliverymethods.heading_postalmethods'] = "PostalMethods";
$lang['AdminCompanyBilling.deliverymethods.postalmethods_desc'] = "PostalMethods prints, stuffs, and mails invoices to your customers for you. <a href=\"http://www.postalmethods.com/\" target=\"_blank\">Sign up</a> for a PostalMethods account and start mailing invoices today.";
$lang['AdminCompanyBilling.deliverymethods.field_delivery_methods'] = "Invoice Delivery Methods";
$lang['AdminCompanyBilling.deliverymethods.field_interfax_username'] = "Username";
$lang['AdminCompanyBilling.deliverymethods.field_interfax_password'] = "Password";
$lang['AdminCompanyBilling.deliverymethods.field_postalmethods_apikey'] = "API Key";
$lang['AdminCompanyBilling.deliverymethods.field_postalmethods_testmode'] = "Test Mode";
$lang['AdminCompanyBilling.deliverymethods.field_postalmethods_replyenvelope'] = "Include a Reply Envelope";
$lang['AdminCompanyBilling.deliverymethods.field_submit'] = "Update Settings";

$lang['AdminCompanyBilling.deliverymethods.note_replyenvelope'] = "Postal Methods will send a reply envelope with each mailing. Note that if this option is checked, all invoices sent to PostalMethods will be in black-and-white.";
