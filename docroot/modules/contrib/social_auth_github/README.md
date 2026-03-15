# Social Auth GitHub

Social Auth GitHub is a GitHub authentication integration for
Drupal. It is based on the Social Auth and Social API projects

It adds to the site:

- A new url: `/user/login/github`.
- A settings form at `/admin/config/social-api/social-auth/github`.
- A GitHub logo in the Social Auth Login block.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/social_auth_github).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/social_auth_github).


## Table of contents

- Requirements
- Installation
- Configuration
- How it works
- Support requests
- Maintainers


## Requirements

This module requires the following modules:

- [Social Auth](https://drupal.org/project/social_auth)
- [Social API](https://drupal.org/project/social_api)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

In Drupal:

1. Log in as an admin.
2. Navigate to Configuration » User authentication » GitHub and copy
   the Authorized redirect URI field value (the URL should end in
   `/user/login/github/callback`).

In GitHub:

3. Log in to a GitHub account.
4. Navigate to Settings » Developer settings » OAuth Apps.
5. Click New OAuth App
6. Set the Application name, Homepage URL, and Application description as
   desired.
7. Paste the redirect URI value (from Step 2) in the Authorization callback
   URL field.
8. Click Register application.
9. On the new application page click Generate a new client secret.
10. Copy the new secret key (GitHUb will not show it again!) and Client ID
    and save them somewhere safe.

In Drupal:

11. Return to Configuration » User authentication » GitHub
12. Enter the GitHub client ID in the Client ID field.
13. Enter the GitHub secret key in the Client secret field.
14. Click Save configuration.
15. Navigate to Structure » Block Layout and place a Social Auth login block
    somewhere on the site (if not already placed).

That's it! Log in with a GitHub account to test the implementation.


## How it works

The user can click on the GitHub logo in the Social Auth Login block.
You can also add a button or link anywhere on the site that points
to `/user/login/github`, so theming and customizing the button or link
is very flexible.

After GitHub has returned the user to your site, the module compares the
user id or email address provided by GitHub. If the user has previously
registered using GitHub or your site already has an account with the same
email address, the user is logged in. If not, a new user account is created.
Also, a GitHub account can be associated with an authenticated user.


## Support requests

- Before posting a support request, carefully read the installation
  instructions provided in module documentation page.
- Before posting a support request, check the Recent Log entries at
  `admin/reports/dblog`
- Once you have done this, you can post a support request at module issue
  queue: [https://www.drupal.org/project/issues/social_auth_github](https://www.drupal.org/project/issues/social_auth_github)
- When posting a support request, please inform if you were able to see any
  errors in the Recent Log entries.


## Maintainers

- Christopher C. Wells - [wells](https://www.drupal.org/u/wells)
- Getulio Valentin Sánchez - [gvso](https://www.drupal.org/u/gvso)
- Himanshu Dixit - [himanshu-dixit](https://www.drupal.org/u/himanshu-dixit)
- Kifah Meeran - [MaskyS](https://www.drupal.org/u/maskys)

**Development sponsored by:**
- [Cascade Public Media](https://www.drupal.org/cascade-public-media)
