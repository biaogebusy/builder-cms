CONTENTS OF THIS FILE
---------------------

* Introduction
* Configuration
* Caveats
* Maintainers
* Credits

INTRODUCTION
------------

Page Load Progress is a module that sets a screen lock showing a throbber when
the user submits a form that triggers a time consuming task. It unequivocally
indicates a task is being executed, and, as a result, improves the overall user
experience. By forbidding users from clicking around while waiting, it also
prevents task execution failures, e.g. when working with web services.

CONFIGURATION
------------

To configure the behavior of this module go to
admin/config/user-interface/page-load-progress.

CAVEATS
-------

Even though you can leverage a Configuration Management override or a Twig
template to change the default behavior, it is strongly recommended you trigger
the throbber only on form submits and internal links as these are the only
supported use cases. Be warned!

If you must, assign the behavior to external "<a>" elements carefully. "<a>"
elements can be opened in a new browser tab or window, which would leave the
original window locked waiting for reload. Also, "<a>" elements are sometimes
used with modals, so make sure that you identify what classes trigger modal
windows and you use :not() to avoid them, or use specific classes when
assigning the behavior (example "a.not-modal").

MAINTAINERS
-----------

* anavarre (https://www.drupal.org/u/anavarre)
* Dom. (https://www.drupal.org/u/dom)

CREDITS
-------

This module was originally created and maintained for Drupal 7 by Mariano
(https://www.drupal.org/u/mariano)
