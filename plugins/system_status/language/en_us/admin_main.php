<?php
$lang['AdminMain.index.health_excellent'] = "The system is in good health and appears to be operating normally.";
$lang['AdminMain.index.health_good'] = "Overall the system is in good health, but there are some items that may require your attention.";
$lang['AdminMain.index.health_fair'] = "There are some issues that affect the system's ability to operate normally.";
$lang['AdminMain.index.health_poor'] = "There are serious issues that affect the system's ability to operate normally. These should be resolved as soon as possible.";

$lang['AdminMain.index.boxtitle_feed'] = "System Status";

$lang['AdminMain.index.cron_serious'] = "Cron has never run.";
$lang['AdminMain.index.cron_minor'] = "Cron has not run in the past 24 hours.";
$lang['AdminMain.index.cron_configure'] = "Configure?";

$lang['AdminMain.index.cron_task_stalled_minor'] = "There are one or more cron tasks that have been executing for more than %1\$s minutes."; // %1$s is the number of minutes
$lang['AdminMain.index.cron_task_stalled_automation'] = "View Automated Tasks";

$lang['AdminMain.index.trial_minor'] = "Your trial license expires on %1\$s."; // %1$s is the date the trial license expires
$lang['AdminMain.index.trial_buy'] = "Buy now?";

$lang['AdminMain.index.invoices_minor'] = "Invoices have not been automatically created via cron in the past 24 hours.";
$lang['AdminMain.index.invoices_configure'] = "Configure?";

$lang['AdminMain.index.backup_minor'] = "No backups have been run in the past 7 days.";

$lang['AdminMain.index.updates_forever'] = "Your support and updates are good forever.";
$lang['AdminMain.index.updates_minor'] = "Your support and updates are good through %1\$s."; // %1$s is the date support expires
$lang['AdminMain.index.updates_serious'] = "Your support and updates expired on %1\$s."; // %1$s is the date support expired
$lang['AdminMain.index.updates_buy'] = "Add support and updates?";
?>