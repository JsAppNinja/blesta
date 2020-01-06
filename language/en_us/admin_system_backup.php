<?php
/**
 * Language definitions for the Admin System Automation settings controller/views
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

// Success messages
$lang['AdminSystemBackup.!success.backup_updated'] = "The Backup settings were successfully updated!";
$lang['AdminSystemBackup.!success.sftp_test'] = "SFTP connection was successful!";
$lang['AdminSystemBackup.!success.amazons3_test'] = "The AmazonS3 connection was successful!";
$lang['AdminSystemBackup.!success.backup_uploaded'] = "The backup was successfully sent to the configured remote services!";

// Error messages
$lang['AdminSystemBackup.!error.sftp_test'] = "The SFTP connection failed! Please check your settings and try again.";
$lang['AdminSystemBackup.!error.amazons3_test'] = "The AmazonS3 connection failed! Please check your settings and try again. Note that connection details are case-sensitive.";
$lang['AdminSystemBackup.!error.backup_frequency'] = "Invalid backup frequency.";


// FTP backup settings
$lang['AdminSystemBackup.ftp.page_title'] = "Settings > System > Backup > Secure FTP";
$lang['AdminSystemBackup.ftp.boxtitle_backup'] = "Secure FTP";

$lang['AdminSystemBackup.ftp.field_host'] = "Hostname";
$lang['AdminSystemBackup.ftp.field_port'] = "Port";
$lang['AdminSystemBackup.ftp.field_username'] = "Username";
$lang['AdminSystemBackup.ftp.field_password'] = "Password";
$lang['AdminSystemBackup.ftp.field_path'] = "Path";
$lang['AdminSystemBackup.ftp.field_rate'] = "Backup Every";
$lang['AdminSystemBackup.ftp.field_backupsubmit'] = "Update Settings";
$lang['AdminSystemBackup.ftp.text_test'] = "Test These Settings";


// Amazon S3 backup settings
$lang['AdminSystemBackup.amazon.page_title'] = "Settings > System > Backup > Amazon S3";
$lang['AdminSystemBackup.amazon.boxtitle_backup'] = "Amazon S3";
$lang['AdminSystemBackup.amazon.field_region'] = "Region";
$lang['AdminSystemBackup.amazon.field_accesskey'] = "Access Key";
$lang['AdminSystemBackup.amazon.field_secretkey'] = "Secret Key";
$lang['AdminSystemBackup.amazon.field_bucket'] = "Bucket";
$lang['AdminSystemBackup.amazon.field_rate'] = "Backup Every";
$lang['AdminSystemBackup.amazon.field_backupsubmit'] = "Update Settings";
$lang['AdminSystemBackup.amazon.text_test'] = "Test These Settings";


// On Demand
$lang['AdminSystemBackup.index.page_title'] = "Settings > System > Backup > On Demand";
$lang['AdminSystemBackup.index.boxtitle_index'] = "On Demand";
$lang['AdminSystemBackup.index.field_uploadbackup'] = "Force Offsite Backup";
$lang['AdminSystemBackup.index.field_downloadbackup'] = "Download Backup";
$lang['AdminSystemBackup.index.text_note'] = "Here you can download a backup of your Blesta database to your computer or automatically upload a backup to your configured SFTP and/or Amazon S3 server.";
?>