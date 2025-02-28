
***
## <a name="updating"></a>UPDATE SOP
Please ignore any documentation if already aware of Drupal site building. This
is for the sake of completed documentation for those who may need it.

If using drush, running  `drush cr`, `drush updb` and `drush cr` should be
enough. If not, and or there are still remaining errors, the following will
help.

Visit any of the following URLs **before** updating Blazy, or its sub-modules.
Keep the `Performance` page open on a separate tab till the update is performed.
This will be your last resort if updates have errors. Never reload this page.

1. Always test updates at DEV or STAGING environments like a pro so nothing
   breaks your PRODUCTION site until everything is thoroughly reviewed.
   Have a restore point aka backup with
   [backup_migrate](https://drupal.org/project/backup_migrate) module, etc.

2. [/admin/config/development/maintenance](/admin/config/development/maintenance)  

   Be sure to put your site on maintenance mode.

3. [/admin/config/development/performance](/admin/config/development/performance)  
   * Hit **Clear all caches** button once the new Blazy in place, immediately
     after running `composer update`...  
     Do not run `/update.php` yet until all caches are cleared up! Even if
     `/update.php` looks like taking care of this.
     Clearing cache should fix most issues with or without updates. If any, this
     step will also make sure a smooth update, since all code base, including
     those dynamic ones generated at `../files/php`, are now synced.
     Any blocking code changes will no longer block the update process. Most
     reported errors are due to failing to clear cache in the first place prior
     to running updates.
   * Regenerate CSS and JS as the latest fixes may contain changes to the
     assets. Ignore below if you are aware, and found no asset changes from
     commits. Normally clearing cache suffices when no asset changes are found.
     * Uncheck CSS and JS aggregation options under Bandwidth optimization.
     * Save.
     * [Ignorable] See one of Blazy related pages if display is expected.
     * [Ignorable] Only clear cache if needed.
     * Check both options again.
     * Save again.
     * [Ignorable] Press F5, or CMD/ CTRL + R to refresh browser cache if
       needed.

4. [Admin status](/admin/reports/status)

   Check for any pending update, and run `/update.php` from browser address bar.
   Do not view your website till the update is performed.

5. If Twig templates are customized, compare against the latest. If having lots
   of customized works, review the latest `blazy.api.php`, if any new changes.

6. Put your site back online.

7. Read more the [TROUBLESHOOTING](#troubleshooting) section for common trouble
   solutions.


## <a name="wsod"></a>WSOD - WORST CASE UPDATE SOP  
This might or might not be related to Blazy updates. At times, we got a WSOD.
The following should do a total rebuild if a WSOD is not easily fixed by the
above regular Update SOP:  
1. Rename or delete `composer.lock` file and `vendor` folder at Drupal docroot.
2. Run `composer update -W -n`, and or plus any additional arguments as per your
   install so to re-configure composer including its `vendor` folder.
3. Only if any issues with asset re-generations, rename or delete folders:
   + `web/sites/default/files/css`
   + `web/sites/default/files/js`
4. Run `composer clear-cache`, if necessary. Will slow it down temporarily!
5. Run `drush cr`, `drush updb` and `drush cr`. Note the silly sequence!


**Note the order!**  
It is very important to follow as is for successful updates. If you don't follow
the above SOP, and stuck on a broken site, no need to uninstall modules which
will remove all configuration, formatter, etc. Instead try downgrading the
module versions, clear cache, and follow the SOP strictly before re-updating.
Check [this](https://drupal.org/node/3263027#comment-14402693) out for hints
on testing updates against Blazy ecosystem.

## BROKEN MODULES
Alpha, Beta, DEV releases are for developers only. Beware of possible breakage.

However if it is broken, running `drush cr`, `drush updb` and `drush cr` during
DEV releases should fix most issues as we add new services, or change things.
If you don't drush, before any module update:

1. Always open a separate tab:

   [Performance](/admin/config/development/performance)
2. And so you are ready to hit **Clear all caches** button if any issue. Do not
   reload this page.
3. Instead view other browser tabs, and simply hit the button if any
   issue.
4. Run `/update.php` as required.
5. D7 only, at worst case, know how to run
   [Registry Rebuild](https://www.drupal.org/project/registry_rebuild) safely.
