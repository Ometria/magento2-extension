Installing the Extension
--------------------------------------------------

While you're free to manually install the Ometria extension (the use of the `app/code` folder structure supports this), we recommend using Magento's [PHP composer](https://getcomposer.org/) integration to install the extension.  All Magento 2 systems have a `composer.json` file, and this file is how developers **and** Magento Marketplace users get new packages in and out of their system.

Installing the extension is a 4 step process

1. Add this GitHub repository to your project's composer.json as a composer repository
2. Add the `ometria/magento2` composer package to your project's composer.json as a required dependency
3. Update your project's composer dependencies
4. Install the downloaded package via Magento's standard command line tool

Support
-------

If you have any concerns or questions, please send an email to support@ometria.com
with all relevant details that are needed to investigate or resolve the issue.

Quick Start
--------------------------------------------------
After backing up your composer.json file

    cp composer.json composer.json.bak

Run

    composer.phar config repositories.ometria vcs https://github.com/Ometria/magento2-extension
    composer require ometria/magento2 --no-update
    composer update ometria/magento2
    php bin/magento module:enable Ometria_AbandonedCarts Ometria_Api Ometria_Core
    php bin/magento setup:upgrade

After running the above, the Ometria extension will be installed, ready for configuration.

Please note, if you are running PHP OPcache on your server and have configured it not to clear automatically then you will need to clear the OPcache in order for the new module to become available after the above steps.

Composer Details
--------------------------------------------------
The first composer command

    composer.phar config repositories.foo vcs https://github.com/Ometria/magento2-extension

add this GitHub repository as a composer repository

    #File: composer.json
    //...
    "repositories": {
        "ometria": {
            "type": "vcs",
            "url": "https://github.com/Ometria/magento2-extension"
        }
    },
    //...

This tells composer it should look for additional packages in this GitHub repository.

The second command

    composer require ometria/magento2 --no-update

add the latest stable version of `ometria/magento2` to your `composer.json` file's `require` section.

    #File: composer.json
    //...
    "require": {
        //...
        "ometria/magento2": "^2.0"
    },
    //...

The third command

    composer update ometria/magento2

Updates any composer packages that match the string `ometria/magento2`.  This is what triggers the download of the Ometria extension source code to `vendor/ometria`.

The final two commands are **Magento** commands.  This command enables the three modules that make up the Ometria extension

    php bin/magento module:enable Ometria_AbandonedCarts Ometria_Api Ometria_Core

Once a module is enabled, the rest of Magento can "see" it. The last command tells Magento to actually install the module.

    php bin/magento setup:upgrade

Upgrading the Extension
--------------------------------------------------

Composer can be used to upgrade an existing install of the module to the latest release using the following commands:

    composer require ometria/magento2 --no-update
    composer update ometria/magento2
    php bin/magento setup:upgrade

This will update your `composer.json` file's `require` section with the latest stable version of `ometria/magento2`. Then the latest code at that version will be pulled in by `composer update`. Finally re-running the Magento `setup:upgrade` command will ensure the module is installed correctly at the new version.

If you installed the module manually in to app/code please ensure you remove all of the existing module files before replacing with the new files from the latest release and re-running the Magento `setup:upgrade` command. 

**Important:** Changing a Magento system running in production is **not** a recommended practice.  Depending on your system software, or other running extensions, running `setup:upgrade` may trigger undesired behaviors.  As with installing **any** new software on your system, don't forget to take appropriate backup steps, and to test your new module in a development or staging environment before deploying to production.
