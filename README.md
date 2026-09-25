Installing the Extension
--------------------------------------------------

While you're free to manually install the Ometria extension (the use of the `app/code` folder structure supports this), we recommend using Magento's [PHP composer](https://getcomposer.org/) integration to install the extension.  All Magento 2 systems have a `composer.json` file, and this file is how developers **and** Magento Marketplace users get new packages in and out of their system.

Installing the extension is a 3 step process

1. Add the `ometria/magento2` composer package to your project's composer.json as a required dependency
2. Enable the modules via Magento's standard command line tool
3. Install the enabled modules

Support
-------

If you have any concerns or questions, please send an email to support@ometria.com
with all relevant details that are needed to investigate or resolve the issue.

Quick Start
--------------------------------------------------
After backing up your composer.json file

    cp composer.json composer.json.bak

Run

    composer require ometria/magento2
    php bin/magento module:enable Ometria_AbandonedCarts Ometria_Api Ometria_Core
    php bin/magento setup:upgrade

After running the above, the Ometria extension will be installed, ready for configuration.

Please note, if you are running PHP OPcache on your server and have configured it not to clear automatically then you will need to clear the OPcache in order for the new module to become available after the above steps.

Composer Details
--------------------------------------------------
The first command

    composer require ometria/magento2

adds the latest stable version of `ometria/magento2` to your `composer.json` file's `require` section and downloads the extension source code to `vendor/ometria`.

    #File: composer.json
    //...
    "require": {
        //...
        "ometria/magento2": "^2.7"
    },
    //...

The extension is published on [Packagist](https://packagist.org/packages/ometria/magento2), which composer consults by default.  No additional repository configuration is required.

**Verifying you have the genuine package.** The official package name is `ometria/magento2`, and its source repository is listed on Packagist as `https://github.com/Ometria/magento2-extension`.  Both are shown on the [package page](https://packagist.org/packages/ometria/magento2) — please check them before installing, and contact support@ometria.com if either does not match.

The remaining two commands are **Magento** commands.  This command enables the three modules that make up the Ometria extension

    php bin/magento module:enable Ometria_AbandonedCarts Ometria_Api Ometria_Core

Once a module is enabled, the rest of Magento can "see" it. The last command tells Magento to actually install the module.

    php bin/magento setup:upgrade

Upgrading the Extension
--------------------------------------------------

Composer can be used to upgrade an existing install of the module to the latest release using the following commands:

    composer update ometria/magento2
    php bin/magento setup:upgrade

This pulls the latest code permitted by the version constraint in your `composer.json` file's `require` section, then re-running the Magento `setup:upgrade` command ensures the module is installed correctly at the new version.

If you installed the module manually in to app/code please ensure you remove all of the existing module files before replacing with the new files from the latest release and re-running the Magento `setup:upgrade` command. 

**Important:** Changing a Magento system running in production is **not** a recommended practice.  Depending on your system software, or other running extensions, running `setup:upgrade` may trigger undesired behaviors.  As with installing **any** new software on your system, don't forget to take appropriate backup steps, and to test your new module in a development or staging environment before deploying to production.

Installing from Source
--------------------------------------------------

Most installations should use the Packagist instructions above.  If you need to track a git branch rather than a tagged release — for example to test an unreleased fix — you can add this GitHub repository to your project as a composer repository instead

    composer.phar config repositories.ometria vcs https://github.com/Ometria/magento2-extension

which adds the following to your `composer.json`

    #File: composer.json
    //...
    "repositories": {
        "ometria": {
            "type": "vcs",
            "url": "https://github.com/Ometria/magento2-extension"
        }
    },
    //...

This tells composer it should look for additional packages in this GitHub repository, and takes precedence over Packagist for this package.  You can then require a branch directly

    composer require ometria/magento2:dev-master

Installing from source is intended for development and testing.  Production systems should use the tagged releases published on Packagist.
