
About
-----

SMS Framework provides a modular API for Drupal to interact with SMS messages.

The project bundles the the following optional modules which improve integration
and add extra features.

SMS Actions
-----------

Status: Not upgraded for Drupal 8. See https://www.drupal.org/node/2641888

Provides an action for sending outgoing messages and the ability to define 
custom triggers for incoming messages.

SMS Blast
---------

Allows bulk text messages to be sent to users.

SMS Devel
---------

Development and testing tools to assist developers and site builders working 
with SMS Framework.

SMS Send to Phone
-----------------

Status: Not upgraded for Drupal 8. See https://www.drupal.org/node/331629

Provides ways to share nodes via SMS.

SMS User
--------

Provides integration with Drupal users.

Gateways
--------

SMS gateways are provided by dedicated modules. A list of gateways for SMS
Framework can be found at https://www.drupal.org/node/2641028

Installation
------------

 1. Install the SMS Framework module.
 2. Install the module for your gateway provider. See 
    https://www.drupal.org/node/2641028 for a list of modules.
 3. Add an instance of your new gateway by going to Administration » 
    Configuration » SMS Framework » Gateways. Then use the 'Add gateway' button.
 4. (optional) Configure entity phone numbers at Administration » Configuration 
    » SMS Framework » Phone number settings. Then user the 'Add phone number
    settings' button. Typical scenarios add the 'User/User' bundle. This form
    requires, and provides the option to automatically add, a phone number field
    on the entity.

Optional Configuration
----------------------

SMS Framework automatically adds an improved token selection UI to SMS message
forms if the Token module (https://drupal.org/project/token) is installed.

Documentation
-------------

Documentation for site builders and developers is available in the Drupal.org
handbook pages: http://drupal.org/node/362258
The `sms.api.php` file and `\Drupal\sms\Event\SmsEvents` class contain
information about events which third parties can hook into. There are examples
of operation in the included test modules.

Support
-------

The following sites offer support for SMS Framework:

 * Drupal Groups
   http://groups.drupal.org/sms-framework
 * SMS Framework issue queue
   https://www.drupal.org/project/issues/smsframework
 * Drupal Stack Exchange
   http://drupal.stackexchange.com
 * Freenode IRC
   Channel: #drupal-support
   https://www.drupal.org/irc

Testing
-------

Drupal core phpunit.xml.dist does not run unit tests located in submodules.
Navigate to the SMS Framework directory to run. Your commands should look 
something like:

    # Run all unit tests, from SMS Framework directory:
    $ ../../vendor/bin/phpunit
    # Run a single test:
    $ ../../vendor/bin/phpunit --filter SmsFrameworkQueueTest

Credits
-------

SMS Framework has a long history with its roots as far back in Drupal 5. The
following people contributed major efforts to the project.

 * Aniebiet Udoh (almaudoh)
   2014-
   https://www.drupal.org/u/almaudoh
   https://twitter.com/almaudoh
 * Andrew Pope (aspope)
   2010
   https://www.drupal.org/user/431955
   https://www.facebook.com/pope
 * Reinier Battenberg (batje)
   2013-14
   https://www.drupal.org/u/batje
   https://twitter.com/batje
 * Daniel Phin (dpi)
   2015-
   https://www.drupal.org/u/dpi
   http://dpi.id.au
 * ekes (ekes)
   2013
   https://www.drupal.org/u/ekes
   http://twitter.com/ekes
 * James Mcbryan (mcpuddin)
   2012-13
   https://www.drupal.org/u/mcpuddin
   http://thetechscouts.com/
 * Tylor Sherman (tylor)
   2008
   https://www.drupal.org/u/tylor
   https://twitter.com/tylorsherman
 * Chris Hood (univate)
   2010-12
   https://www.drupal.org/u/univate
   https://twitter.com/univate
 * Will White (will-white)
   2007-9
   https://www.drupal.org/u/will-white
   https://twitter.com/willwhitedc

License
-------

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
