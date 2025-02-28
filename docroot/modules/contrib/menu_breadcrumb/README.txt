
MENU BREADCRUMBS
================

Introduction
------------
The Drupal 7 version of this module implemented the Drupal 6 behaviour of
using the menu position of the current page for the breadcrumb.  It also
added an option to append the page title to the breadcrumb (either as
a clickable url or not), saving the trouble of doing this in the theme,
and hiding the breadcrumb if it only contained the link to the front page.

The Drupal 8 version also supports "Taxonomy Attachment" for each
menu, which provides the same breadcrumb trail to the current
page as to a taxonomy of which it is a member: effectively giving
menu based breadcrumbs to items that aren't on a menu (e.g., blog
entries). Effectively these are "attached" to the breadcrumb trail by
taxonomy membership, inheriting the breadcrumbs of their taxonomy.

Options in the Drupal 8 version therefore also include the ability to
add the current page title, linked or not, as an additional breadcrumb
when a taxonomy attachment has taken place.  More detailed options are
given for dealing with the front page, allowing it to be added or removed.

Installation & Upgrading - RECOMMENDED
--------------------------------------
Follow current instructions on this Drupal documentation page
to install Menu Breadcrumb as a site dependency and upgrade it
along with other site dependencies (as well as Drupal Core itself):

https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies

IF YOU SEE AN ERROR PAGE similar to the following after up- or down-grading the module:

TypeError: Argument <X> passed to Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder::__construct() ...

... you probably just need to rebuild the cache between two versions that use
different argument lists (i.e. system services).  See here for how to rebuild cache:

https://www.drupal.org/docs/user_guide/en/prevent-cache-clear.html

Installation & Upgrading - without Composer
-------------------------------------------

Installation, on older Drupal versions & sites:
1. Copy the menu_breadcrumb folder to your modules/contrib directory.
2. At Administer -> Extend (admin/modules) enable the module.
3. Configure the module settings at Administer -> Configuration ->
     User Interface (admin/config/user-interface/menu-breadcrumb).

Upgrading on older Drupal versions & sites:
Recommended: install drush and run "drush up"

Manually: replace the older menu_breadcrumb folder with the newer version;
then run "update.php" if present (to install any configuration changes).

Since different versions of the module may use different services, if you see errors:

- Try clearing the cache, which fixes breacrumb on taxonomy pages (since this
  module's breadcrumb builder needs to be acknolwedged as higher priority).
- Clearing the cache (at least the router cache) should clear up messages about
  any services missing (known issue upgrading beta1 to beta2).
- If all else fails, try uninstalling & reinstalling the module.


Features
--------
- For a high-level description, see the Drupal 8 Help screen for this module.
- All other features are described by each checkbox on the Configuration page,
  and in the notes on the re-orderable menu list below.

Issues / Feature requests
-------------------------
If you find a bug, or have a feature request, please go to :

http://drupal.org/project/issues/menu_breadcrumb
