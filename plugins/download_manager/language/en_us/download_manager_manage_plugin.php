<?php
/**
 * Download Manager manage plugin language
 */

// Success messages
$lang['DownloadManagerManagePlugin.!success.category_added'] = "The category has been successfully created.";
$lang['DownloadManagerManagePlugin.!success.category_updated'] = "The category has been successfully updated.";
$lang['DownloadManagerManagePlugin.!success.category_deleted'] = "The category has been successfully deleted.";
$lang['DownloadManagerManagePlugin.!success.file_added'] = "The file has been successfully added.";
$lang['DownloadManagerManagePlugin.!success.file_updated'] = "The file has been successfully updated.";
$lang['DownloadManagerManagePlugin.!success.file_deleted'] = "The file has been successfully deleted.";


// Tooltips
$lang['DownloadManagerManagePlugin.!tooltip.path_to_file'] = "Enter the absolute path to the file on the file system.";


// Text
$lang['DownloadManagerManagePlugin.!text.root_directory'] = "Home Directory";
$lang['DownloadManagerManagePlugin.!text.open_parenthesis'] = "(";
$lang['DownloadManagerManagePlugin.!text.forward_slash'] = "/";
$lang['DownloadManagerManagePlugin.!text.closed_parenthesis'] = ")";


// Modal
$lang['DownloadManagerManagePlugin.modal.delete_file'] = "Are you sure you want to delete this file?";
$lang['DownloadManagerManagePlugin.modal.delete_category'] = "Are you sure you want to delete this category? All subcategories and files within this category will be moved to the parent category.";


// Index
$lang['DownloadManagerManagePlugin.index.page_title'] = "Download Manager > Manage";
$lang['DownloadManagerManagePlugin.index.boxtitle_downloadmanager'] = "Download Manager";

$lang['DownloadManagerManagePlugin.index.add_download'] = "Add Download Here";
$lang['DownloadManagerManagePlugin.index.add_category'] = "Add Category Here";

$lang['DownloadManagerManagePlugin.index.go_back'] = "Go up a level";

$lang['DownloadManagerManagePlugin.index.edit'] = "Edit";
$lang['DownloadManagerManagePlugin.index.delete'] = "Delete";

$lang['DownloadManagerManagePlugin.index.no_downloads'] = "There are no downloads in this section.";


// Add download
$lang['DownloadManagerManagePlugin.add.page_title'] = "Download Manager > Add Download";

$lang['DownloadManagerManagePlugin.add.boxtitle_root'] = "Add Download to the %1\$s"; // %1$s is the name of the root directory
$lang['DownloadManagerManagePlugin.add.boxtitle_add'] = "Add Download to Category [%1\$s]"; // %1$s is the name of the category the download is to be uploaded to

$lang['DownloadManagerManagePlugin.add.field_public'] = "Publicly Available";
$lang['DownloadManagerManagePlugin.add.field_logged_in'] = "Must be logged in";
$lang['DownloadManagerManagePlugin.add.field_name'] = "Name";
$lang['DownloadManagerManagePlugin.add.field_available_to_client_groups'] = "Available to Client Groups";
$lang['DownloadManagerManagePlugin.add.field_available_to_packages'] = "Available to Packages";
$lang['DownloadManagerManagePlugin.add.text_clientgroups'] = "Selected Client Groups";
$lang['DownloadManagerManagePlugin.add.text_packagegroups'] = "Selected Packages";
$lang['DownloadManagerManagePlugin.add.text_availableclientgroups'] = "Available Client Groups";
$lang['DownloadManagerManagePlugin.add.text_availablepackages'] = "Available Packages";
$lang['DownloadManagerManagePlugin.add.field_upload'] = "Upload File";
$lang['DownloadManagerManagePlugin.add.field_path'] = "Specify Path to File";
$lang['DownloadManagerManagePlugin.add.field_file'] = "File";
$lang['DownloadManagerManagePlugin.add.field_file_name'] = "Path to File";

$lang['DownloadManagerManagePlugin.add.submit_add'] = "Add Download";
$lang['DownloadManagerManagePlugin.add.submit_cancel'] = "Cancel";


// Edit download
$lang['DownloadManagerManagePlugin.edit.page_title'] = "Download Manager > Add Download";

$lang['DownloadManagerManagePlugin.edit.boxtitle_edit'] = "Update Download";

$lang['DownloadManagerManagePlugin.edit.field_public'] = "Publicly Available";
$lang['DownloadManagerManagePlugin.edit.field_logged_in'] = "Must be logged in";
$lang['DownloadManagerManagePlugin.edit.field_name'] = "Name";
$lang['DownloadManagerManagePlugin.edit.field_available_to_client_groups'] = "Available to Client Groups";
$lang['DownloadManagerManagePlugin.edit.field_available_to_packages'] = "Available to Packages";
$lang['DownloadManagerManagePlugin.edit.text_clientgroups'] = "Selected Client Groups";
$lang['DownloadManagerManagePlugin.edit.text_packagegroups'] = "Selected Packages";
$lang['DownloadManagerManagePlugin.edit.text_availableclientgroups'] = "Available Client Groups";
$lang['DownloadManagerManagePlugin.edit.text_availablepackages'] = "Available Packages";
$lang['DownloadManagerManagePlugin.edit.field_upload'] = "Upload File";
$lang['DownloadManagerManagePlugin.edit.field_path'] = "Specify Path to File";
$lang['DownloadManagerManagePlugin.edit.field_file'] = "File";
$lang['DownloadManagerManagePlugin.edit.field_file_name'] = "Path to File";

$lang['DownloadManagerManagePlugin.edit.submit_edit'] = "Update Download";
$lang['DownloadManagerManagePlugin.edit.submit_cancel'] = "Cancel";


// Add category
$lang['DownloadManagerManagePlugin.addcategory.page_title'] = "Download Manager > Add Category";

$lang['DownloadManagerManagePlugin.addcategory.boxtitle_root'] = "Add Category to the %1\$s"; // %1$s is the name of the root directory
$lang['DownloadManagerManagePlugin.addcategory.boxtitle_addcategory'] = "Add Category to Category [%1\$s]"; // %1$s is the name of the category that this category is to be nested under

$lang['DownloadManagerManagePlugin.addcategory.field_name'] = "Name";
$lang['DownloadManagerManagePlugin.addcategory.field_description'] = "Description";

$lang['DownloadManagerManagePlugin.addcategory.submit_add'] = "Create Category";
$lang['DownloadManagerManagePlugin.addcategory.submit_cancel'] = "Cancel";


// Edit category
$lang['DownloadManagerManagePlugin.editcategory.page_title'] = "Download Manager > Update Category";

$lang['DownloadManagerManagePlugin.editcategory.boxtitle_editcategory'] = "Update Category [%1\$s]"; // %1$s is the name of the category

$lang['DownloadManagerManagePlugin.editcategory.field_name'] = "Name";
$lang['DownloadManagerManagePlugin.editcategory.field_description'] = "Description";

$lang['DownloadManagerManagePlugin.editcategory.submit_edit'] = "Update Category";
$lang['DownloadManagerManagePlugin.editcategory.submit_cancel'] = "Cancel";
?>