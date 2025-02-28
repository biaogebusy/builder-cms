# User Default Page module

## Introduction

User Default Page module provide you the facility to customize the
destination that a user is redirected to after logging in or logged out.

This module provides the below facilities:
  * It provides a configuration setting in UI for admin user.
  * You can Add, Edit or Delete a User default page.
  * You can give permission the User default page as per the Roles or
User or Both.
  * You can set the url path for login and logout action along with
success messages.

For a full description of the module, visit the project page:
https://www.drupal.org/project/user_default_page


# Installaltion

Install as you would normally install a contributed drupal module.
See: https://drupal.org/documentation/install/modules-themes/modules-8
for further information.


## Configuration

 * Go to: Configuration >> People >> User Default Page
   (/admin/config/people/user_default_page).
   The table will look empty initially as you have not created a User
   Default Page yet.

 * Create a user default page by clicking on "Add User default page"
   button or from /admin/config/people/user_default_page/add.

 * Select a user role(from: User Roles field), for which you are going
   to use the User Default page or an individual user (from: Select User field)
   or both.

 * Fill the `Redirect to URL` field and `Message` field for both Login
   and Logout fieldset.

 * Click on the save button to save the configuration.
