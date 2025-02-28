CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Fivestar voting module adds a clean, attractive voting widget to nodes and
comments in Drupal. It features:

 * jQuery (1.0 - 1.8) rollover effects and AJAX no-reload voting
 * Customizable star sets
 * Graceful degradation to a HTML rating form when JavaScript is disabled
 * Configurability per node type
 * Anonymous voting support
 * Voting spam protection
 * Easy-to-use integration with Views for lists sorted by rating, or filtered
   by min/max ratings
 * A Fivestar field for use in custom node types
 * An easy-to-use Form API element type for use in other modules

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/fivestar

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/fivestar

 * For more information on module usage, visit the documentation guide:
   https://www.drupal.org/docs/8/modules/fivestar


REQUIREMENTS
------------

This module requires the following modules:

 * Voting API (https://www.drupal.org/project/votingapi)


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

Fivestar has two configuration modes:

 * End-user rating a piece of content: These settings are located on the content
   type settings page. These settings let you expose a rating widget when
   viewing the node, not editing it. Clicking on the widget registers a vote for
   that node, and never anything else.

The configuration for Fivestar is spread between the content type settings page,
Fivestar site settings page, and access permissions. To configure:

1) Configure the site-wide setting for Fivestar, Administer -> Configuration ->
   Fivestar (or go directly to /admin/config/content/fivestar)

2) Activate voting on each content type. For example, if you want Fivestar to
   appear on "Article" nodes, use Administer -> Structure -> Content Types ->
   Article, and check the "Enable Fivestar rating" box under
   the "Fivestar ratings" heading. Repeat for each content type desired.

3) Enable anonymous voting.
   If you want to allow anonymous voting, you'll need to set permissions for
   that. Use Administer -> People -> Permissions, and check the
   "Use Fivestar to rate content" checkboxe for the role(s) you'd like.
   You'll find this permission under the "Fivestar" module heading.


Configuration for Reviews of Content
------------------------------------

Fivestar can be used to quickly setup a rating system for your site visitors to
review a piece of content. When enabling the Comment widget, visitors will
submit a rating on the *original piece of content* along with their comment.
Visitors will not be rating the comments themselves. Fivestar does not allow for
the rating of comments.

1) If it's not already enabled, turn on the comment module at
   Administer -> Modules.

2) Visit the content type you want to enable reviews, such as Administer ->
   Structure -> Content Types -> Article, and select an option under
   the "Comment widget" section.


Configuration as a CCK field / Advanced Rating
----------------------------------------------

Fivestar has extensive CCK support, which makes it so that the user is presented
with a widget to rate some node with the widget while editing a node. It does
not necessary rate the current node, nor does it rate anything if no value is
entered in the Node ID field when configuring the CCK field. The value is
saved in the node (so when you edit it it is still there), but no vote is
registered in VotingAPI without the Node ID field filled out.

An example of a situation where you might want to use the CCK fivestar field is
creating a node type called 'review'. This review type would let users rate
some particular node, and accompany their rating with a review. This could be
combined with a standard rating on the target node, so that some users could
rate the target node using the simple method, or write a complete review to
accompany their rating.

To configure a CCK field for rating a node while creating a new 'review' node:

1) Create a new node type called 'review' at Administer -> Structure ->
Content Types. Configure the type. Do NOT set any fivestar settings on the
content type form! We don't want users to actually be able to rate the reviews
themselves!

2) Edit your new content type, then click on the "Add Field" tab while on the
content type form. Add a field called 'rating' to your new type, make it of type
Fivestar Rating with the Stars radio button.

3) Configure the rating widget to your liking. Most field have help text which
explain their purpose. The Node ID field is the most important field on the page
which determines exactly what node will receive the value of the rating. In a
really simple case, you could just enter the value 10 to always rate on the same
node with nid = 10.

A common scenario is using fivestar with nodecomments to make reviews. If using
nodecomments a separate checkbox appears the Node ID field to allow you easily
select the nodecomment parent as the target of the vote.

Save your field. Now when making new nodes of type 'review', the user will
select a star that will register a vote on the value of the Node ID field.


Views Integration
-----------------
Fivestar depends on the views integration provided by VotinAPI, but adds some
special features to make it work specifically with Fivestar. To display Fivestar
ratings in a view, select the "VotingAPI percent vote result" from the list of
available Fields. This will display the average vote for nodes. Then choose
"Fivestar rating" from the Handler options for the field and the averages will
be displayed as Fivestar ratings.

Fivestar also provides handling for the display of Fivestar CCK fields, they are
in the Field list under "Fivestar Rating: [Field name]".


Creating a Fivestar Set
-----------------------

1. Open your favorite image editor and create an image that is 3 times as high
   as it is wide. The default size for Fivestar (and the easiest to work with)
   is 16x48 pixels.

2. Setup guides at 16 pixels and 32 pixels. This splits your canvas into thirds.

3. Create a star icon in the top third. When satisfied, copy it into the middle
   and bottom thirds of the image. Change the middle and bottom copies to your
   liking. Fivestar will use the top, middle, and bottom images for each state
   of the star.

   Top      -> Off
   Middle   -> On
   Bottom   -> Hover

4. Save your image as "star.png" in a new directory. The name of your directory
   will be the label for your set of stars, spaces are not allowed.

5. Do the same thing for a cancel image, only there are only 2 states for a
   cancel image, so your image will be 16 pixels by 32pixels. Setup a guide at
   16 pixels so your canvas is split in half.

6. Create a cancel icon in the top half. Then duplicate it into the bottom half.
   The cancel states are simply Off and Hover.

   Top      -> Off
   Bottom   -> Hover

7. Save your cancel image as "cancel.png" in the directory create in step 4.

8. Create the CSS stylesheet. The easiest way to make this stylesheet is to copy
   an existing CSS file from another set of stars. The "Basic" set provides an
   excellent example for a 16x16 star, because it only changes the background
   image as necessary. If you're making a larger or smaller size for your stars
   than 16x16 pixels, the "Minimal" and "Outline" sets make for a good example.


Contributing
------------
Have a sweet set of stars you'd like to contribute to the Fivestar module?
Post them to the issue queue: https://drupal.org/project/issues/fivestar


Support
-------
If you experience a problem with fivestar or have a problem, file a
request or issue on the fivestar queue at
https://drupal.org/project/issues/fivestar. DO NOT POST IN THE FORUMS. Posting
in the issue queues is a direct line of communication with the module authors.


MAINTAINERS
-----------

Fivestar was designed by Nate Haug and Jeff Eaton.

This Module Made by Robots: http://www.lullabot.com
