<?php
/**
 * Language definitions for the Admin Company Emails settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminCompanyEmails.!success.edittemplate_updated'] = "The email template settings were successfully updated!";
$lang['AdminCompanyEmails.!success.editsignature_updated'] = "The email signature has been successfully updated!";
$lang['AdminCompanyEmails.!success.addsignature_created'] = "The email signature has been successfully created!";
$lang['AdminCompanyEmails.!success.deletesignature_deleted'] = "The email signature has been successfully deleted!";
$lang['AdminCompanyEmails.!success.mail_updated'] = "The Mail settings have been successfully updated!";


// Common language
$lang['AdminCompanyEmails.!cancel.field_cancel'] = "Cancel";


// Email templates
$lang['AdminCompanyEmails.templates.page_title'] = "Settings > Company > Emails > Email Templates";
$lang['AdminCompanyEmails.templates.boxtitle_templates'] = "Email Templates";

$lang['AdminCompanyEmails.templates.heading_client'] = "Client Emails";
$lang['AdminCompanyEmails.templates.heading_staff'] = "Staff Emails";
$lang['AdminCompanyEmails.templates.heading_shared'] = "Shared Emails";
$lang['AdminCompanyEmails.templates.heading_plugins'] = "Plugin Emails";

$lang['AdminCompanyEmails.templates.text_name'] = "Name";
$lang['AdminCompanyEmails.templates.text_plugin'] = "Plugin";
$lang['AdminCompanyEmails.templates.text_description'] = "Description";
$lang['AdminCompanyEmails.templates.text_options'] = "Options";

$lang['AdminCompanyEmails.templates.option_edit'] = "Edit";

$lang['AdminCompanyEmails.templates.no_results'] = "There are no templates of this type.";

$lang['AdminCompanyEmails.templates.payment_cc_approved_name'] = "Payment Approved (Credit Card)";
$lang['AdminCompanyEmails.templates.payment_cc_approved_desc'] = "Notice sent after a successful credit card payment is approved.";
$lang['AdminCompanyEmails.templates.payment_cc_declined_name'] = "Payment Declined (Credit Card)";
$lang['AdminCompanyEmails.templates.payment_cc_declined_desc'] = "Notice sent after a credit card payment attempt is declined.";
$lang['AdminCompanyEmails.templates.payment_cc_error_name'] = "Payment Error (Credit Card)";
$lang['AdminCompanyEmails.templates.payment_cc_error_desc'] = "Notice sent after a credit card payment attempt results in error.";
$lang['AdminCompanyEmails.templates.payment_ach_approved_name'] = "Payment Approved (ACH)";
$lang['AdminCompanyEmails.templates.payment_ach_approved_desc'] = "Notice sent after a successful ACH payment is approved.";
$lang['AdminCompanyEmails.templates.payment_ach_declined_name'] = "Payment Declined (ACH)";
$lang['AdminCompanyEmails.templates.payment_ach_declined_desc'] = "Notice sent after a ACH payment attempt is declined.";
$lang['AdminCompanyEmails.templates.payment_ach_error_name'] = "Payment Error (ACH)";
$lang['AdminCompanyEmails.templates.payment_ach_error_desc'] = "Notice sent after an ACH payment attempt results in error.";
$lang['AdminCompanyEmails.templates.payment_manual_approved_name'] = "Payment Received (Manual Entry)";
$lang['AdminCompanyEmails.templates.payment_manual_approved_desc'] = "Notice sent after a payment is manually recorded.";
$lang['AdminCompanyEmails.templates.payment_nonmerchant_approved_name'] = "Payment Received (Non-Merchant)";
$lang['AdminCompanyEmails.templates.payment_nonmerchant_approved_desc'] = "Notice sent after a payment is received from a non-merchant gateway.";
$lang['AdminCompanyEmails.templates.credit_card_expiration_name'] = "Credit Card Expiration";
$lang['AdminCompanyEmails.templates.credit_card_expiration_desc'] = "Notice sent when an active credit card is about to expire.";
$lang['AdminCompanyEmails.templates.invoice_delivery_unpaid_name'] = "Invoice Delivery (Unpaid)";
$lang['AdminCompanyEmails.templates.invoice_delivery_unpaid_desc'] = "Notice containing a PDF copy of an unpaid invoice.";
$lang['AdminCompanyEmails.templates.invoice_delivery_paid_name'] = "Invoice Delivery (Paid)";
$lang['AdminCompanyEmails.templates.invoice_delivery_paid_desc'] = "Notice containing a PDF copy of a paid invoice.";
$lang['AdminCompanyEmails.templates.invoice_notice_first_name'] = "Invoice Notice (1st)";
$lang['AdminCompanyEmails.templates.invoice_notice_first_desc'] = "First invoice notice, either a reminder to pay or late notice.";
$lang['AdminCompanyEmails.templates.invoice_notice_second_name'] = "Invoice Notice (2nd)";
$lang['AdminCompanyEmails.templates.invoice_notice_second_desc'] = "Second invoice notice, either a reminder to pay or late notice.";
$lang['AdminCompanyEmails.templates.invoice_notice_third_name'] = "Invoice Notice (3rd)";
$lang['AdminCompanyEmails.templates.invoice_notice_third_desc'] = "Third invoice notice, either a reminder to pay or late notice.";
$lang['AdminCompanyEmails.templates.reset_password_name'] = "Password Reset";
$lang['AdminCompanyEmails.templates.reset_password_desc'] = "Password reset email containing a link to change the account password.";
$lang['AdminCompanyEmails.templates.service_suspension_name'] = "Service Suspension";
$lang['AdminCompanyEmails.templates.service_suspension_desc'] = "Service suspended notice, sent when a service is automatically suspended.";
$lang['AdminCompanyEmails.templates.service_unsuspension_name'] = "Service Unsuspension";
$lang['AdminCompanyEmails.templates.service_unsuspension_desc'] = "Service unsuspended notice, sent when a service is automatically unsuspended.";
$lang['AdminCompanyEmails.templates.account_welcome_name'] = "Account Registration";
$lang['AdminCompanyEmails.templates.account_welcome_desc'] = "Welcome notice sent for new account registrations.";
$lang['AdminCompanyEmails.templates.report_ar_name'] = "Aging Invoices Report";
$lang['AdminCompanyEmails.templates.report_ar_desc'] = "Thirty, Sixety, Ninety day Aging Invoice Reports, delivered once per month.";
$lang['AdminCompanyEmails.templates.report_tax_liability_name'] = "Tax Liability Report";
$lang['AdminCompanyEmails.templates.report_tax_liability_desc'] = "A monthly Tax Liability Report, generated for the previous month.";
$lang['AdminCompanyEmails.templates.report_invoice_creation_name'] = "Invoice Creation Report";
$lang['AdminCompanyEmails.templates.report_invoice_creation_desc'] = "A daily report of invoices generated for the previous day.";
$lang['AdminCompanyEmails.templates.service_suspension_error_name'] = "Suspension Error";
$lang['AdminCompanyEmails.templates.service_suspension_error_desc'] = "Notice sent after a failed attempt to suspend a service.";
$lang['AdminCompanyEmails.templates.service_unsuspension_error_name'] = "Unsuspension Error";
$lang['AdminCompanyEmails.templates.service_unsuspension_error_desc'] = "Notice sent after a failed attempt to unsuspend a service.";
$lang['AdminCompanyEmails.templates.service_cancel_error_name'] = "Cancellation Error";
$lang['AdminCompanyEmails.templates.service_cancel_error_desc'] = "Notice sent after a failed attempt to cancel a service.";
$lang['AdminCompanyEmails.templates.service_creation_error_name'] = "Creation Error";
$lang['AdminCompanyEmails.templates.service_creation_error_desc'] = "Notice sent after a failed attempt to provision a service.";
$lang['AdminCompanyEmails.templates.auto_debit_pending_name'] = "Auto-Debit Pending";
$lang['AdminCompanyEmails.templates.auto_debit_pending_desc'] = "Notice sent that indicates an automatic payment will be attempted soon.";
$lang['AdminCompanyEmails.templates.staff_reset_password_name'] = "Password Reset";
$lang['AdminCompanyEmails.templates.staff_reset_password_desc'] = "Password reset email containing a link to change the account password.";
$lang['AdminCompanyEmails.templates.service_creation_name'] = "Service Creation";
$lang['AdminCompanyEmails.templates.service_creation_desc'] = "Service creation notice, sent when a service has been created.";


// Edit email template
$lang['AdminCompanyEmails.edittemplate.page_title'] = "Settings > Company > Emails > Edit Email Template";
$lang['AdminCompanyEmails.edittemplate.boxtitle_edittemplate'] = "Edit Email Template %1\$s"; // %1$s is the email template group name
$lang['AdminCompanyEmails.edittemplate.text_none'] = "None";

$lang['AdminCompanyEmails.edittemplate.field_status'] = "Enabled";
$lang['AdminCompanyEmails.edittemplate.field_from_name'] = "From Name";
$lang['AdminCompanyEmails.edittemplate.field_from'] = "From Email";
$lang['AdminCompanyEmails.edittemplate.field_subject'] = "Subject";
$lang['AdminCompanyEmails.edittemplate.field_tags'] = "Available Tags";
$lang['AdminCompanyEmails.edittemplate.field_text'] = "Text";
$lang['AdminCompanyEmails.edittemplate.field_html'] = "HTML";
$lang['AdminCompanyEmails.edittemplate.field_email_signature_id'] = "Signature";
$lang['AdminCompanyEmails.edittemplate.field_include_attachments'] = "Include Any Attachments";
$lang['AdminCompanyEmails.edittemplate.field_edittemplatesubmit'] = "Update Template";

$lang['AdminCompanyEmails.edittemplate.note_include_attachments'] = "Uncheck to disable the inclusion of any attachments that would otherwise be sent for emails using this template.";


// Email signatures
$lang['AdminCompanyEmails.signatures.page_title'] = "Settings > Company > Emails > Signatures";
$lang['AdminCompanyEmails.signatures.boxtitle_signatures'] = "Signatures";

$lang['AdminCompanyEmails.signatures.categorylink_newsignature'] = "New Signature";
$lang['AdminCompanyEmails.signatures.no_results'] = "There are no email signatures.";

$lang['AdminCompanyEmails.signatures.text_name'] = "Name";
$lang['AdminCompanyEmails.signatures.text_description'] = "Description";
$lang['AdminCompanyEmails.signatures.text_options'] = "Options";

$lang['AdminCompanyEmails.signatures.option_edit'] = "Edit";
$lang['AdminCompanyEmails.signatures.option_delete'] = "Delete";

$lang['AdminCompanyEmails.signatures.confirm_delete'] = "Are you sure you want to delete this email signature?";


// Add email signature
$lang['AdminCompanyEmails.addsignature.page_title'] = "Settings > Company > Emails > Add Signature";
$lang['AdminCompanyEmails.addsignature.boxtitle_addsignature'] = "Add Signature";

$lang['AdminCompanyEmails.addsignature.field_name'] = "Name";
$lang['AdminCompanyEmails.addsignature.field_text'] = "Text";
$lang['AdminCompanyEmails.addsignature.field_html'] = "HTML";
$lang['AdminCompanyEmails.addsignature.field_addsignaturesubmit'] = "Create Signature";

$lang['AdminCompanyEmails.addsignature.text_signatures'] = "Signatures are used for email templates, making it easier to modify email signatures in bulk";


// Edit email signature
$lang['AdminCompanyEmails.editsignature.page_title'] = "Settings > Company > Emails > Edit Signature";
$lang['AdminCompanyEmails.editsignature.boxtitle_editsignature'] = "Edit Signature";

$lang['AdminCompanyEmails.editsignature.field_name'] = "Name";
$lang['AdminCompanyEmails.editsignature.field_text'] = "Text";
$lang['AdminCompanyEmails.editsignature.field_html'] = "HTML";
$lang['AdminCompanyEmails.editsignature.field_editsignaturesubmit'] = "Update Signature";


// Mail
$lang['AdminCompanyEmails.mail.page_title'] = "Settings > Company > Emails > Mail Settings";
$lang['AdminCompanyEmails.mail.boxtitle_mail'] = "Mail Settings";

$lang['AdminCompanyEmails.mail.text_section'] = "This section controls how email is delivered from Blesta. PHP is the simplest delivery method, but SMTP is generally faster and more reliable.";

$lang['AdminCompanyEmails.mail.field_html_email'] = "Enable HTML";
$lang['AdminCompanyEmails.mail.field_mail_delivery'] = "Delivery Method";
$lang['AdminCompanyEmails.mail.field_smtp_security'] = "SMTP Security";
$lang['AdminCompanyEmails.mail.field_smtp_host'] = "SMTP Host";
$lang['AdminCompanyEmails.mail.field_smtp_port'] = "SMTP Port";
$lang['AdminCompanyEmails.mail.field_smtp_user'] = "SMTP User";
$lang['AdminCompanyEmails.mail.field_smtp_password'] = "SMTP Password";
$lang['AdminCompanyEmails.mail.field_submitmail'] = "Update Settings";


// Text
$lang['AdminCompanyEmails.getRequiredMethods.php'] = "PHP";
$lang['AdminCompanyEmails.getRequiredMethods.smtp'] = "SMTP";
$lang['AdminCompanyEmails.getsmtpsecurityoptions.none'] = "None";
$lang['AdminCompanyEmails.getsmtpsecurityoptions.ssl'] = "SSL";
$lang['AdminCompanyEmails.getsmtpsecurityoptions.tls'] = "TLS";
?>