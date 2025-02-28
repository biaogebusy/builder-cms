# Autocomplete Deluxe

The Autocomplete Deluxe module is an enhanced autocomplete element that uses the
JQuery UI autocomplete. It will also implement a widget for taxonomy. This
module does not require any 3rd party jQuery libraries.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/autocomplete_deluxe).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/autocomplete_deluxe).


## Table of contents

- Requirements
- Installation
- Configuration
- FAQ
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

To set up a field named Tags which uses an Autocomplete Deluxe widget to set
values for that field from the Tags taxonomy, do the following:

 - Navigate to Administration > Modules and enable the Autocomplete Deluxe
   module.
 - Navigate to Administration  > Structure > Content types and select manage
   fields of the content type you wish to edit.
 - Add a new field of "Term reference" named "Tags". Select the Widget Type
   `"Autocomplete Deluxe"` in the drop down menu. Save.
 - Select the Tags vocabulary.  Save field settings.
 - Customize or keep the default Autocomplete Deluxe settings for the field.
   Save settings.

Now when new content is added the Tags widget allows editors to enter
existing tags as well as create new ones.


## Faq

**Q: Can I use the Autocomplete Deluxe widget as a Views exposed filter?**

**A:** TWhy yes, yes you can!  First, add the field as a traditional exposed
   Autocomplete filter in your view.  Then, create a custom module (see
   `https://www.drupal.org/docs/7/creating-custom-modules` if you have
   never written a module before), where your .module file contains
   something like the following:
```
   function MY_MODULE_form_alter(&$form, Drupal\Core\Form\FormStateInterface
     $form_state, $form_id) {
     // Variables specific to your View.
     $my_exposed_filter_field = 'field_term_ref';
     $my_target_bundle = 'test_vocab';

     if ($form_id == 'views_exposed_form' &&
       isset($form[$my_exposed_filter_field . '_target_id'])) {
       $selection_settings = array(
         'target_bundles' => array($my_target_bundle => $my_target_bundle),
         'sort' => array('field' => '_none'),
         'auto_create' => (BOOL) 0,
         // Even though we've specified '0' for 'auto_create', it seems that
         // a value for 'auto_crteate_bundle' is required for this to work.
         'auto_create_bundle' => 'tags',
         'match_operator' => 'CONTAINS',
       );
       $target_type = 'taxonomy_term';
       $selection_handler = 'default:taxonomy_term';
       $data = serialize($selection_settings) . $target_type .
         $selection_handler;
       $selection_settings_key = Drupal\Component\Utility\Crypt::hmacBase64(
         $data,
         Drupal\Core\Site\Settings::getHashSalt()
       );
       $route_parameters = [
         'target_type' => $target_type,
         'selection_handler' => $selection_handler,
         'selection_settings_key' => $selection_settings_key,
       ];
       $url = Drupal\core\Url::fromRoute(
         'autocomplete_deluxe.autocomplete',
         $route_parameters,
         ['absolute' => TRUE]
       )->toString();

       $form[$my_exposed_filter_field . '_target_id'] = array(
         '#type' => 'autocomplete_deluxe',
         '#autocomplete_deluxe_path' => $url,
         '#selection_settings' => $selection_settings,
         '#multiple' => TRUE,
         '#target_type' => $target_type,
         '#selection_handler' => $selection_handler,
         '#limit' => 10,
         '#size' => 60,
         '#new_terms' => 0,
         '#min_length' => 0,
         '#delimiter' => ',',
         '#not_found_message_allow' => 0,
         '#not_found_message' => "The term '@term' will be added.",
       );
     }
   }
```


## Maintainers

- Edward Chan - [edwardchiapet](https://www.drupal.org/u/edwardchiapet)
- Klaus Purer - [klausi](https://www.drupal.org/u/klausi)
- Mohammed Razem - [Mohammed J. Razem](https://www.drupal.org/u/mohammed-j-razem)
- Mike Priscella - [mpriscella](https://www.drupal.org/u/mpriscella)
- Rajab Natshah - [Rajab Natshah](https://www.drupal.org/u/rajab-natshah)
- Sebastian Gilits - [sepgil](https://www.drupal.org/u/sepgil)
