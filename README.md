# Blesta #

Blesta is a well-written, security-focused, user and developer-friendly client
management, billing, and support application.

## Minimum Requirements ##

* PHP version 5.1.3
* PDO, pdo_mysql, curl (version 7.10.5), and openssl (version 0.9.6) PHP extensions.
* MySQL version 5.0.17
* Apache, IIS, or LiteSpeed Web Server
* ionCube PHP loader

### Note for PHP 5.5 Users
If you are running PHP 5.5, **you must** apply the hotifx from _/hotfix-php5.5/blesta/_
to _/blesta/_ before uploading files to your web server.


For recommended requirements and additional information, please see the
[documentation](http://docs.blesta.com/display/user/Requirements).

## Installation ##

To install, upload the contents of blesta to your web server and visit this
location in your browser.

For more detailed instructions, please see the
[documentation](http://docs.blesta.com/display/user/Installing+Blesta) for
installing Blesta.

## Upgrading ##

Note! Back up your database and files before beginning an upgrade.

To upgrade, overwrite the files in your existing installation and access
~/admin/upgrade in your browser.

For more detailed instructions, please see the
[documentation](http://docs.blesta.com/display/user/Upgrading+Blesta) for
upgrading Blesta.

## Patching ##

Note! Back up your database and files before applying a patch.

Patches contain all patches issued for the minor release. For example, a patch
labeled 3.0.6 will contain all patches issued from 3.0.1, so it is not necessary
to apply patches incrementally.

To patch your installation, overwrite the files in your existing installation
and access ~/admin/upgrade in your browser.

For more detailed instructions, please see the
[documentation](http://docs.blesta.com/display/user/Upgrading+Blesta#UpgradingBlesta-Patchinganexistinginstall)
for patching Blesta.

