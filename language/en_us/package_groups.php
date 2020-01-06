<?php
/**
 * Language definitions for the Package Groups model
 * 
 * @package blesta
 * @subpackage blesta.language.en_us
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

$lang['PackageGroups.!error.name.empty'] = "Please specify a package group name.";
$lang['PackageGroups.!error.type.format'] = "Invalid group type.";
$lang['PackageGroups.!error.company_id.exists'] = "Invalid company ID.";
$lang['PackageGroups.!error.parents.format'] = "At least one parent group ID given is a non-standard group unavailable for use as a parent.";
$lang['PackageGroups.!error.allow_upgrades.format'] = "Whether packages within this group can be upgraded/downgraded must be set to 1 or 0.";


// Group types
$lang['PackageGroups.gettypes.standard'] = "Standard";
$lang['PackageGroups.gettypes.addon'] = "Add-on";
?>