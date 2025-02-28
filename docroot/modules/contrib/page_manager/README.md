# CONTENTS OF THIS FILE

- Introduction
- Requirements
- Recommended Modules
- Installation
- Configuration
- Maintainers

## INTRODUCTION

The Page Manager module supports the creation of new pages, and allows placing
blocks within that page. This module was formerly part of the Ctools suite of
modules.

- For a full description of the module visit
  <https://www.drupal.org/project/page_manager>.

- To submit bug reports and feature suggestions, or to track changes visit
  <https://www.drupal.org/project/issues/page_manager>.

## REQUIREMENTS

CTools - <https://www.drupal.org/project/ctools>

## RECOMMENDED MODULES

Panels in Drupal 8 integrates with Page Manager and offers a custom variant type
which allows users to select different layouts and manage blocks. Panels on its
own does not have much to offer and Page Manager provides a user interface that
helps utilize Panels.

- Panels - <https://www.drupal.org/project/panels>

## INSTALLATION

- Install the Page Manager module as you would normally install a contributed
  Drupal module. Visit <https://www.drupal.org/node/1897420>.

## CONFIGURATION

Navigate to Administration > Extend and enable the Page Manager and Page Manager
UI modules. This provides an interface for customizing page paths, parameters,
and access permissions.

To Add a Page:
1. Navigate to Administration > Structure > Extend and select "Add page".
2. Enter the title of the page in "Administrative title" and the intended path
   into "Path".
3. From the "Variant type" dropdown your choices are "HTTP status code" or
   "Bock Page". Make your selection and select Next.
4. This page also allows users the optional features of "Page access", "Variant
   contexts", and "Variant selection criteria". If you do not check them here
   you will still be able to utilize these features once the new page is
   created. If you are not sure, leave these unchecked.
5. On the "Configure variant" page, type in the Label and Page Title and select
   Next.
6. On the Content page select "Add new block" and select which block to add to
   the page. Select the region where the block will appear and select "Add
   Block".
7. When all of the blocks are placed, select Finish.
8. Enter the name of the custom page into "Page title" and select "Update and
   save".

Integration with Panels:
1. Navigate to Administration > Structure > Extend and select "Add page".
2. Enter the title of the page in "Administrative title" and the intended path
   into "Path". 3. From the "Variant type" choose "Panels" and select Next.
4. On the "Configure" page, select Standard from Builder and select Next.
5. On the "Layout page", select the desired layout and select Finish. If there
   are not any layouts in the drop-down try, rebuilding the site cache.
6. From the Content page, select which blocks appear in which region. Select
   Finish.
7. Enter the name of the custom page into "Page title" and select "Update and
   save".

Page Variants:
Variants allow users to configure multiple layouts on a single page and
configure the selection criteria to determine which variant should be displayed.
1. Navigate to Administration > Structure > Pages and select Edit on the page
   to edit.
2. In the "Variants" tab select there are four choices: "General", "Contexts",
   "Selection criteria", and "Content".
3. "General" allows users to name the variant.
4. "Contexts" allows users to select actions based on context.
5. "Selection criteria" allows users to add conditions.
6. "Content" allows users to place new blocks.
7. The "Add variant" tab on the page edit form allows users to add another
   variant with different configurations.
8. The "Reorder variants" tab on the page edit form allows users to order the
   variants. The order of the variant is important. Any variant that has no
   selection criteria will always return true and be displayed. Ones with a
   selection criteria should be ordered above ones without any.

## MAINTAINERS

- Tim Plunkett (tim.plunkett) - <https://www.drupal.org/u/timplunkett>
- Kris Vanderwater (EclipseGc) - <https://www.drupal.org/u/eclipsegc>
- David Snopek (dsnopek) - <https://www.drupal.org/u/dsnopek>
