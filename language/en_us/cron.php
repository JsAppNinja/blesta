<?php
/**
 * Language definitions for the Cron controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Errors
$lang['Cron.!error.cron.failed'] = "Cron failed to log.";
$lang['Cron.!error.task_execution.failed'] = "Error: %1\$s %2\$s"; // %1$s is the error message that occurred, %2$s is the stack trace


// Index
$lang['Cron.index.attempt_all'] = "Attempting to run all tasks for %1\$s."; // %1$s is the company name
$lang['Cron.index.completed_all'] = "All tasks have been completed.";
$lang['Cron.index.attempt_all_system'] = "Attempting to run all system tasks.";
$lang['Cron.index.completed_all_system'] = "All system tasks have been completed.";

// Create invoices
$lang['Cron.createinvoices.attempt'] = "Attempting to renew services and create invoices.";
$lang['Cron.createinvoices.completed'] = "The create invoices task has completed.";
$lang['Cron.createinvoices.recurring_invoice_failed'] = "Unable to create a new invoice from recurring invoice #%1\$s for client #%2\$s."; // %1$s is the recurring invoic enumber, %2$s is the client ID
$lang['Cron.createinvoices.recurring_invoice_success'] = "Successfully created a new invoice from recurring invoice #%1\$s for client #%2\$s."; // %1$s is the recurring invoice number, %2$s is the client ID

$lang['Cron.createinvoices.service_invoice_success'] = "Successfully created invoice #%1\$s for client #%2\$s containing services %3\$s."; // %1$s is the invoice ID, %2$s is the client ID, %3$s is a comma-separated list of service IDs
$lang['Cron.createinvoices.service_invoice_error'] = "Invoice failed to generate for client #%2\$s containing services %3\$s because: %1\$s"; // %1$s is a dump of all errors, %2$s is the client ID, %3$s is a comma-separated list of service IDs


// Apply credits
$lang['Cron.applycredits.attempt'] = "Attempting to apply credits to open invoices.";
$lang['Cron.applycredits.completed'] = "The apply credits task has completed.";
$lang['Cron.applycredits.apply_failed'] = "Unable to apply pending credits for client #%1\$s."; // %1$s is the client ID
$lang['Cron.applycredits.apply_success'] = "Successfully applied pending credits from transaction %1\$s for client #%2\$s to invoice #%3\$s in the amount of %4\$s."; // %1$s is the transaction number, %2$s is the client ID, %3$s is the invoice ID, and %4$s is the amount applied
$lang['Cron.applycredits.apply_none'] = "There are no invoices to which credits may be applied.";


// Autodebit invoices
$lang['Cron.autodebitinvoices.attempt'] = "Attempting to auto debit open invoices.";
$lang['Cron.autodebitinvoices.completed'] = "The auto debit invoices task has completed.";
$lang['Cron.autodebitinvoices.charge_attempt'] = "Attempting to auto debit client #%1\$s for all open invoices in the amount of %2\$s."; // %1$s is the client ID, %2$s is the amount due
$lang['Cron.autodebitinvoices.charge_failed'] = "Unable to process the payment.";
$lang['Cron.autodebitinvoices.charge_success'] = "Successfully processed the payment.";


// Payment Reminders
$lang['Cron.paymentreminders.attempt'] = "Attempting to send payment reminders.";
$lang['Cron.paymentreminders.completed'] = "The payment reminders task has completed.";
$lang['Cron.paymentreminders.failed'] = "Unable to send the reminder notice for invoice #%4\$s to contact %1\$s %2\$s from client #%3\$s."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID, %4$s is the invoice ID
$lang['Cron.paymentreminders.success'] = "Successfully delivered the reminder notice for invoice #%4\$s to contact %1\$s %2\$s from client #%3\$s."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID, %4$s is the invoice ID
$lang['Cron.paymentreminders.autodebit_failed'] = "Unable to send the autodebit reminder notice for invoice #%4\$s to contact %1\$s %2\$s from client #%3\$s."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID, %4$s is the invoice ID
$lang['Cron.paymentreminders.autodebit_success'] = "Successfully delivered the autodebit reminder notice for invoice #%4\$s to contact %1\$s %2\$s from client #%3\$s."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID, %4$s is the invoice ID


// Card Expiration Reminders
$lang['Cron.cardexpirationreminders.attempt'] = "Attempting to send card expiration reminders.";
$lang['Cron.cardexpirationreminders.completed'] = "The card expiration reminders task has completed.";
$lang['Cron.cardexpirationreminders.failed'] = "The expiration reminder for contact %1\$s %2\$s from client #%3\$s could not be sent."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID
$lang['Cron.cardexpirationreminders.success'] = "Successfully delivered the expiration reminder for contact %1\$s %2\$s from client #%3\$s."; // %1$s is the contact's first name, %2$s is the contact's last name, %3$s is the client ID


// Deliver invoices
$lang['Cron.deliverinvoices.attempt'] = "Attempting to deliver invoices scheduled for delivery.";
$lang['Cron.deliverinvoices.completed'] = "The deliver invoices task has completed.";
$lang['Cron.deliverinvoices.delivery_error'] = "Unable to deliver %3\$s invoices to client #%1\$s via %2\$s due to error: %4\$s"; // %1$s is the client ID, %2$s is the invoice delivery method used, %3$s is the number of invoices that failed to be delivered, %4$s is the error message
$lang['Cron.deliverinvoices.delivery_error_one'] = "Unable to deliver 1 invoice to client #%1\$s via %2\$s due to error: %3\$s"; // %1$s is the client ID, %2$s is the invoice delivery method used, %3$s is the error message
$lang['Cron.deliverinvoices.delivery_success'] = "Successfully delivered %3\$s invoices to client #%1\$s via %2\$s."; // %1$s is the client ID, %2$s is the invoice delivery method used, %3$s is the number of invoices delivered
$lang['Cron.deliverinvoices.delivery_success_one'] = "Successfully delivered 1 invoice to client #%1\$s via %2\$s."; // %1$s is the client ID, %2$s is the invoice delivery method used
$lang['Cron.deliverinvoices.none'] = "No invoices are scheduled to be delivered.";

$lang['Cron.deliverinvoices.method_email'] = "Email";
$lang['Cron.deliverinvoices.method_interfax'] = "InterFax";
$lang['Cron.deliverinvoices.method_postalmethods'] = "PostalMethods";


// Deliver reports
$lang['Cron.deliverreports.attempt'] = "Attempting to deliver reports.";
$lang['Cron.deliverreports.completed'] = "The deliver reports task has completed.";
$lang['Cron.deliverreports.aging_invoices.attempt'] = "Attempting to send the Aging Invoices report.";
$lang['Cron.deliverreports.aging_invoices.attachment_fail'] = "Unable to generate the Aging Invoices report attachment.";
$lang['Cron.deliverreports.aging_invoices.email_error'] = "The Aging Invoices email failed to send.";
$lang['Cron.deliverreports.aging_invoices.email_success'] = "The Aging Invoices report email has been sent.";
$lang['Cron.deliverreports.invoice_creation.attempt'] = "Attempting to send the Invoice Creation report.";
$lang['Cron.deliverreports.invoice_creation.attachment_fail'] = "Unable to generate the Invoice Creation report attachment.";
$lang['Cron.deliverreports.invoice_creation.email_error'] = "The Invoice Creation report email failed to send.";
$lang['Cron.deliverreports.invoice_creation.email_success'] = "The Invoice Creation report email has been sent.";
$lang['Cron.deliverreports.tax_liability.attempt'] = "Attempting to send the Tax Liability report.";
$lang['Cron.deliverreports.tax_liability.attachment_fail'] = "Unable to generate the Tax Liability report attachment.";
$lang['Cron.deliverreports.tax_liability.email_error'] = "The Tax Liability email failed to send.";
$lang['Cron.deliverreports.tax_liability.email_success'] = "The Tax Liability report email has been sent.";


// Suspend services
$lang['Cron.suspendservices.attempt'] = "Attempting to suspend past due services.";
$lang['Cron.suspendservices.completed'] = "The suspend services task has completed.";

$lang['Cron.suspendservices.suspend_error'] = "The service #%1\$s from client %2\$s could not be suspended."; // %1$s is the service ID, %2$s is the client ID
$lang['Cron.suspendservices.suspend_success'] = "The service #%1\$s from client %2\$s has been suspended."; // %1$s is the service ID, %2$s is the client ID


// Suspend services
$lang['Cron.unsuspendservices.attempt'] = "Attempting to unsuspend paid suspended services.";
$lang['Cron.unsuspendservices.completed'] = "The unsuspend services task has completed.";

$lang['Cron.unsuspendservices.unsuspend_error'] = "The service #%1\$s from client %2\$s could not be unsuspended."; // %1$s is the service ID, %2$s is the client ID
$lang['Cron.unsuspendservices.unsuspend_success'] = "The service #%1\$s from client %2\$s has been unsuspended."; // %1$s is the service ID, %2$s is the client ID


// Cancel Scheduled Services
$lang['Cron.cancelscheduledservices.attempt'] = "Attempting to cancel scheduled services.";
$lang['Cron.cancelscheduledservices.completed'] = "The cancel scheduled services task has completed.";

$lang['Cron.cancelscheduledservices.cancel_error'] = "The service #%1\$s from client #%2\$s could not be canceled."; // %1$s is the service ID, %2$s is the client ID
$lang['Cron.cancelscheduledservices.cancel_success'] = "The service #%1\$s from client #%2\$s has been canceled."; // %1$s is the service ID, %2$s is the client ID


// Add paid pending services
$lang['Cron.addpaidpendingservices.attempt'] = "Attempting to provision paid pending services.";
$lang['Cron.addpaidpendingservices.completed'] = "The paid pending services task has completed.";
$lang['Cron.addpaidpendingservices.service_error'] = "The pending service #%1\$s from client #%2\$s could not be made active."; // %1$s is the service ID, %2$s is the client ID
$lang['Cron.addpaidpendingservices.service_success'] = "The pending service #%1\$s from client #%2\$s is now active."; // %1$s is the service ID, %2$s is the client ID


// Update exchange rates
$lang['Cron.updateexchangerates.attempt'] = "Attempting to update exchange rates.";
$lang['Cron.updateexchangerates.completed'] = "The exchange rates task has completed.";
$lang['Cron.updateexchangerates.success'] = "Exchange rates were updated successfully.";
$lang['Cron.updateexchangerates.failed'] = "Exchange rates could not be updated.";

// Clean logs
$lang['Cron.cleanlogs.attempt'] = "Attempting to clean up old logs.";
$lang['Cron.cleanlogs.completed'] = "The clean logs task has completed.";
$lang['Cron.cleanlogs.logs_gateway_deleted'] = "%1\$s old Gateway logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_module_deleted'] = "%1\$s old Module logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_accountaccess_deleted'] = "%1\$s old Account Access logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_contact_deleted'] = "%1\$s old Contact logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_email_deleted'] = "%1\$s old Email logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_user_deleted'] = "%1\$s old User logs have been deleted."; // %1$s is the number of logs deleted
$lang['Cron.cleanlogs.logs_transaction_deleted'] = "%1\$s old Transaction logs have been deleted."; // %1$s is the number of logs deleted

// Process Service Changes
$lang['Cron.processservicechanges.attempt'] = "Attempting to process service changes.";
$lang['Cron.processservicechanges.completed'] = "The process service changes task has completed.";
$lang['Cron.processservicechanges.process_result'] = "Processing service change #%1\$s resulted in status: %2\$s"; // %1$s is the service change ID, %2$s is the service change status
$lang['Cron.processservicechanges.missing_invoice'] = "Invoice ID #%1\$s not found. Service change #%2\$s for service #%3\$s is changed to status: %4\$s."; // %1$s is the invoice ID, %2$s is the service change ID, %3$s is the service ID, %4$s is the new service change status
$lang['Cron.processservicechanges.service_inactive'] = "Service Change #%1\$s was not processed because its service is inactive."; // %1$s is the service change ID
$lang['Cron.processservicechanges.expired'] = "Service change #%1\$s has expired without payment and changed to status: %2\$s"; // %1$s is the service change ID, %2$s is the service change status

// Process Renewing Services
$lang['Cron.processrenewingservices.attempt'] = "Attempting to process renewing services.";
$lang['Cron.processrenewingservices.completed'] = "The process renewing services task has completed.";
$lang['Cron.processrenewingservices.renew_success'] = "Renewed service #%1\$s for client %2\$s."; // %1$s is the service ID, %2$s is the client ID
$lang['Cron.processrenewingservices.renew_error'] = "Unable to renew service #%1\$s for client %2\$s."; // %1$s is the service ID, %2$s is the client ID

// Plugin tasks
$lang['Cron.plugin.attempt'] = "Attempting plugin cron for %1\$s %2\$s."; // %1$s is the plugin dir, %2$s is the plugin cron task key
$lang['Cron.plugin.completed'] = "Finished plugin cron for %1\$s %2\$s."; // %1$s is the plugin dir, %2$s is the plugin cron task key

// Backup AmazonS3
$lang['Cron.backups_amazons3.attempt'] = "Attempting to backup the database to AmazonS3.";
$lang['Cron.backups_amazons3.completed'] = "The AmazonS3 database backup task has completed.";
$lang['Cron.backups_amazons3.success'] = "The backup completed successfully.";

// Backup SFTP
$lang['Cron.backups_sftp.attempt'] = "Attempting to backup the database via SFTP.";
$lang['Cron.backups_sftp.completed'] = "The SFTP database backup task has completed.";
$lang['Cron.backups_sftp.success'] = "The backup completed successfully.";

// License
$lang['Cron.license.attempt'] = "Attempting to validate the license.";
$lang['Cron.license.completed'] = "The license validation task has completed.";
?>