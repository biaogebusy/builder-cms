CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Forms Steps module provides a UI to create forms workflows using forms
modes. It creates quick and configurable multisteps forms.

 * For a full description of the module visit:
   https://www.drupal.org/project/forms_steps

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/forms_steps


REQUIREMENTS
------------

PHP 7.0+


INSTALLATION
------------

 * Install the Forms Steps module as you would normally install a contributed
   Drupal module. Visit https://www.drupal.org/node/1897420 for further
   information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Workflow > Form Steps to add
       a form step.
    3. Select the Form mode, Content type, Step URL and mode. Save.


USER ACCOUNT IN MULTISTEP FORMS
-------------------------------

Forms Steps allows the creation of user accounts through multiple steps,
but given the security  concerns it can involve we can't authorize an
anonymous user to access any "update" permission, even on a user entity
they have just created.

To allow a user creation in multiple step you have to follow some basic rules.
In any case the first step will always be a user creation and the next ones
a user update.
Privileged users will only be able to access the different create/update steps
with the right permissions, i.e administrative rights like "Administer users".

To allow anonymous users to complete a profile:
* first you'll need to go to /admin/config/people/accounts and set
the Registration policy to "Visitors".
* secondly you'll need to log the user in after the first step, to allow
a proper update of its account,
you can achieve this through two different methods:
    * Login the user through custom code, through the event STEP_CHANGE_EVENT
    or any submit handler.
    * Go to /admin/config/people/accounts and disable the "Require email
    verification when a visitor creates an account"
    option, to allow automatic login from the core to be performed.


ENTITY DISPLAY MODES & MISSING FORM CLASSES
-------------------------------------------

The core allows to create new form modes on any types of entities.

When rendering the page for this form mode, Drupal will require a class
to be provided for the form to be displayed.
Unfortunately such a default class is missing in the core itself for
certain entities (like the User) and the same may happen with contrib modules.

If a form step is defined on such an entity Forms Steps will return
a HTTP Not Found exception and will log a warning message
`You must defined the form mode class for yourself`.

In the worst case scenario an error message asking for a form class
will be displayed, like this one:
```
The "user" entity type did not specify a "[name of the user type]" form class.
in Drupal\Core\Entity\EntityTypeManager->getFormObject()
(line 223 of core/lib/Drupal/Core/Entity/EntityTypeManager.php).
```

This is true for any entity type for which the core don't provide a
default form class.
Until the core provide the required default classes it should be
set through a custom module:
```
/**
 * Implements hook_entity_type_alter().
 */
function my_module_entity_type_alter(array &$entity_types) {
  $formMode = 'my_custom_user_form_mode';
  $entity_types['user']->setFormClass($formMode, 'Drupal\user\ProfileForm');
}
```

IMPORT COMMAND
--------------

Forms Steps provides a drush command to allow you importing an existing
entity to a new or existing workflow instance.

To import a single entity to a new workflow use the following command:
```
drush forms_steps:attach-entity [Form Steps Collection Machine Name] [Entity Type] [Entity Bundle] [Entity Id] [Entity Form Mode Machine Name] [Form Steps Step Machine Name]
```
For example:
```
drush forms_steps:attach-entity my_form_steps node article 1 default step_1
```

Additionally you can add the following option to specify a particular
workflow instance:
```
drush forms_steps:attach-entity [Form Steps Collection Machine Name] [Entity Type] [Entity Bundle] [Entity Id] [Entity Form Mode Machine Name] [Form Steps Step Machine Name] --instance_id=[Workflow Instance Id]
```
For example:
```
drush forms_steps:attach-entity my_form_steps node article 1 default step_1 --instance_id=e479f307-729d-458e-88cb-7aa440cdac89
```

By default the command checks if the entity you are trying to insert in your
workflow entity does exists, to avoid inserting non-existing references.
However, you can bypass this by specifying an additional option:
```
drush forms_steps:attach-entity [Form Steps Collection Machine Name] [Entity Type] [Entity Bundle] [Entity Id] [Entity Form Mode Machine Name] [Form Steps Step Machine Name] --ignore_entity_id_check
```
For example:
```
drush forms_steps:attach-entity my_form_steps node article 1 default step_1 --ignore_entity_id_check
drush forms_steps:attach-entity my_form_steps node article 1 default step_1 --ignore_entity_id_check --instance_id=e479f307-729d-458e-88cb-7aa440cdac89
```

DEVELOPERS
----------

Forms Steps provides some useful way to interact and customize it.

- Events
  - STEP_CHANGE_EVENT : That is triggered when the user jump from
  one step to another just before going to the next step.
  It is possible here with the Drupal\forms_steps\Event\StepChangeEvent
  to do a lot of alteration and change the behavior of your steps.

MAINTAINERS
-----------

 * Nicolas Loye (nicoloye) - https://www.drupal.org/u/nicoloye
 * Hakim Rachidi (HakimR) - https://www.drupal.org/u/hakimr
 * Christophe Klein (christophe.klein) -
 https://www.drupal.org/u/christopheklein67

Supporting organization:

 * Actency - https://www.drupal.org/actency
