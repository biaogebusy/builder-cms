Asymmetric translations allow you to choose different layouts and blocks for
different languages on content items that override Layout Builder settings for
their entity type.

For example, if the Article node with the ID 34 needs to have two additional
blocks shown among its content that aren't present on other Article nodes, but
the English translation needs to display the first Block on the left, and the
second Block on the right, while the German translation needs the reverse, to
show the first Block on the right, and the second Block on the left.

You can configure 'Manage form display' to allow copying blocks or when creating
a new translation. But when you add an inline block to the layout, the language
will be set automatically to the language of the entity. In case you don't want
this behavior,
add $settings['layout_builder_at_set_content_block_language_to_entity'] = FALSE;
to settings.php.

 **Behind the scenes:**
 Sections are stored as multi-valued fields on Layout Builder Overrides, and
 different layouts translations are stored as translations of these fields. The
 module makes the layout section field translatable when enabling layout builder
 on an entity display. On install, existing layout section fields are also made
 translatable.

 * For a full description visit the project page:
    https://www.drupal.org/project/layout_builder_at

 * To submit bug reports and feature suggestions, or to track changes:
    http://drupal.org/project/issues/layout_builder_at

## CONTENTS OF THIS FILE

 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers

## REQUIREMENTS

This module requires no modules outside of Drupal core.


## SIMILAR MODULES

If you want each translation to have the same layouts and sections see the
[Layout Builder Symmetric Translations](https://www.drupal.org/project/layout_builder_st)
module **the 2 modules do not work together on 1 site**.


## INSTALLATION

 * Install as you would normally install a contributed Drupal module.
   Visit https://www.drupal.org/node/1897420 for further information.


## CONFIGURATION

- Go to `Configuration > Regional > Content Language` to enable translation on
the available Layout fields.


## MAINTAINERS

 **Current maintainers:**
  * Kristof De Jaeger (swentel) - https://www.drupal.org/u/swentel
  * Elber Rodrigues (elber) - https://www.drupal.org/u/elber

 **Supporting organizations:**
  * [eps & kaas](https://www.drupal.org/eps-kaas)
    eps & kaas is a small digital agency that focuses on branding, (Drupal)
    websites and apps (Android and iPhone).We stand by our very personal and
    transparent project management, which is based on our extensive experience
    in the digital world. Visit https://www.epsenkaas.be/ for more information.

 * [Dropsolid](https://www.drupal.org/dropsolid)
    We are a Digital Agency, Drupal integrator and Open Digital Experience
    Platform (DXP) in a single company. Our 70 digital experts help companies,
    governments and organizations to deliver brilliant digital experiences to
    all their stakeholders. Visit https://dropsolid.com/ for more information.
