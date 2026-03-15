# Social Auth Google

Social Auth Google is a Google authentication integration for
Drupal. It is based on the Social Auth and Social API projects

It adds to the site:

- A new url: `/user/login/google`.
- A settings form at `/admin/config/social-api/social-auth/google`.
- A Google logo in the Social Auth Login block.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/social_auth_google).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/social_auth_google).


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
2. Navigate to Configuration » User authentication » Google and copy
   the Authorized redirect URL field value (the URL should end in
   `/user/login/google/callback`).
   In [Google Cloud Console](https://console.cloud.google.com/):
3. Log in to a Google account.
4. Navigate to APIs & Services and click [Create Project](https://console.cloud.google.com/projectcreate).
5. Set the Project name and Location as desired.
6. Click Create.
7. With the new project selected, navigate to APIs & Services » OAuth consent
   screen.
8. Select the "`External`" User Type and click Create.
9. Set the App name, User support email, and Developer contact information
   fields as desired (all other fields are optional).
10. Click Save and Continue
11. On the Scopes add any desired scopes (none are required).
12. Click Save and Continue.
13. On the Test users steps add at least one email address to be used for
    testing the implementation.
14. Click Save and Continue.
15. Navigate to APIs & Services » Credentials.
16. Click Create Credentials » OAuth client ID.
17. Select "`Web application`" in the Application type field.
18. Set the Name field as desired.
19. In the Authorized redirect URIs section click Add URI.
20. Paste the URL copied from Step 2 in the URI field.
21. Click Create.
22. Copy the new Client Secret (Google will not show it again!) and Client ID
    and save them somewhere safe.
23. Click OK.

In Drupal:

24. Return to Configuration » User authentication » Google
25. Enter the Google client ID in the Client ID field.
26. Enter the Google secret key in the Client secret field.
27. Click Save configuration.
28. Navigate to Structure » Block Layout and place a Social Auth login block
    somewhere on the site (if not already placed).

That's it! Log in with one of the test users added during the setup in Google
Cloud.

When ready log in to Google Cloud Console, navigate to APIs & Services » OAuth
consent screen, and click Publish App to enable access for any user with a
Google account.


## How it works

The user can click on the Google logo on the Social Auth Login block
You can also add a button or link anywhere on the site that points
to `/user/login/google`, so theming and customizing the button or link
is very flexible.

After Google has returned the user to your site, the module compares the user id
or email address provided by Google. If the user has previously registered using
Google or your site already has an account with the same email address, the user
is logged in. If not, a new user account is created. Also, a Google account can
be associated with an authenticated user.


## Support requests

- Before posting a support request, carefully read the installation
  instructions provided in module documentation page.
- Before posting a support request, check the Recent Log entries at
  `admin/reports/dblog`
- Once you have done this, you can post a support request at module issue
  queue: [https://www.drupal.org/project/issues/social_auth_google](https://www.drupal.org/project/issues/social_auth_google)
- When posting a support request, please inform if you were able to see any
  errors in the Recent Log entries.


## Maintainers

- Christopher C. Wells - [wells](https://www.drupal.org/u/wells)
- Getulio Valentin Sánchez - [gvso](https://www.drupal.org/u/gvso)
- Himanshu Dixit - [himanshu-dixit](https://www.drupal.org/u/himanshu-dixit)

**Development sponsored by:**
- [Cascade Public Media](https://www.drupal.org/cascade-public-media)
