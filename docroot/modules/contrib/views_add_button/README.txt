#####################################################################
##                     Views Add Button README                     ##
#####################################################################

### INTRODUCTION ###

Have you ever wanted to create "add" buttons with tokens from your
contextual filters? Did you ever need a custom list of nodes with an
add button at the top that checks for entity create access, without
having to write a custom handler or put a view inside a view? Then
this module is for you!

Views Add Button lets you make an "add an entity" button of any
type, and you may specify CSS classes and destination parameters for
your link. You may also create multiple buttons with differing
parameters if needed: just add multiple instances of the button
field.

### REQUIREMENTS ###

 - Views

### INSTALLATION ###

Views Add Button installs like most Drupal modules:

# Composer

 - Go to your project root, and require drupal/views_add_button
   - composer require drupal/views_add_button
 - Go to your modules page (Extend) and enable. No further setup is
   needed

# Download

 - Download the tar or zip file to your modules/contrib directory,
   and extract
 - Go to your modules page (Extend) and enable. No further setup is
   needed


### CONFIGURATION ###

Once installed, in the Views header and footer "Global: Entity Add
Button" will be made available. The button has the following options,
and all options *except* Entity Type support tokens.

# Entity Type
Here, you may select the entity type and bundle you want to generate
an add button for.

# Entity Context
Certain entities require extra route parameters to be set. For
example, The Group module, handled by Views Add Button: Group, needs
the group ID to be set in this field. This is not used by most
entities.

# Button Text for the Add Button
The text to be shown for the generated link.

# Query String
A query string to add to the generated URL. Do not add the '?' to
the string.

# Button Classes
The VAB button is in fact an anchor tag (<a>), and this field appends
classes in order to style the link as a button. If nothing is added,
the link will merely render as a link.

# Additional options
Options common to other Views area plugins (destination parameter,
enabling the use of tokens, etc.) are also available for the Add
Button.

### Creating a Plugin ###

Please review the Node, Taxonomy, and User plugins found under
/src/Plugin/views_add_button to supplement this README.

# Placement
Views Add Button classes should go in /src/Plugin/views_add_button

# Annotation

Views Add Button Plugins are annotated as @ViewsAddButton, and have
these parameters:

 - id: textual ID of the plugin, usually views_add_button_[entity_type]
 - label = a translated (@Translation()) string of a human-readable
   label for the plugin
 - target_entity: The entity this plugin is written for. Should be
   unique: do not install two plugins with the same target entity

# Class Functions
Your plugin class should have the following two functions:
 - description: Provides a description of the plugin
 - generate_url: Generate a Drupal\Core\Url that points to the add
   link.
