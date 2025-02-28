CONTENTS
---------------------
   
 * INTRODUCTION
 * REQUIREMENTS
 * INSTALLATION
 * CONFIGURATION
 * QUEUE AND CRON
 * USE OTP LOGIN AS A SERVICE

INTRODUCTION
------------

- This module will allow you to register/login to a site just using mobile
  number/OTP sent to the provided mobile number.

REQUIREMENTS
------------

- sms and sms_user modules that are part of smsframework module.
- basic_auth, restui, rest and serialization (Only if you want to use
    OTP LOGIN AS A SERVICE)

INSTALLATION
------------

- Install as usual. See http://drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

Once the module is installed, a link 'OTP Login Settings' will appear on
   '/admin/config' page under 'People'.

1. Go to '/admin/config/people/otp_login' to configure OTP login related
     settings
   a. Enable 'Activate OTP Login' to enable OTP login.
   b. Choose OTP Platform.
      i. SMS Framework
        - With 'SMS framework', you can use any supported gateway to send OTP.
      ii. Tiniyo API
        - You can use Tiniyo API to send OTP. For this, you need to do below
        mentioned configurations.
          - Enter 'Tiniyo Key (AuthID)'
          - Enter 'Tiniyo Secret (AuthSecretID)'
          - Choose how you want to send OTP. Through SMS, Voice call or Both.
          - Choose length of OTP
        - Note: 'Tiniyo Key (AuthID)' and 'Tiniyo Secret (AuthSecretID)' can be found under https://tiniyo.com/accounts/home when you are logged in.
   c. If you want to purge users who have been blocked / not logged-in for a
      specific number of days, enable the provided checkbox and enter number
        of days.
   d. If you want to purge blocked / never logged-in users at any moment,
        click on tab 'OTP login - purge users'
          (/admin/config/people/otp_login/user_purge) and press button
            'Purge users'.

Place the 'OTP login link' block:
Go to '/admin/structure/block' and place 'OTP login link' block to your
  preferred region.
The link block will be shown only to anonymous users and if
  'Activate OTP Login' is enabled.

In addition, also configure settings related to smsframework module.

1. Go to '/admin/config/smsframework/settings' to configure 'Fallback gateway'
    and 'Phone verification path'.
2. Go to '/admin/config/smsframework/gateways' to add new gateway.
3. Go to '/admin/config/smsframework/phone_number' to add phone number settings:
    a. Click on 'Add phone number settings' button.
    b. Select 'user' bundle.
    c. Under 'Field mapping' -> 'Phone number' select 'Create a new telephone
      field'.
    d. You can choose to keep everything else as-is.

If you want to use OTP LOGIN AS A SERVICE:

1. Go to '/admin/config/services/rest'.
2. Enable 'Generate OTP', 'Submit OTP' and 'Logout OTP' resources.

QUEUE AND CRON
--------------

Considering the fact that, on few sites, there could be hundreds of users who
  would try to login through OTP, our module will not send OTP directly.
    Instead, it will Queue the OTP and will be sent on next cron run.

USE OTP LOGIN AS A SERVICE
------------------------------------

You can use functionalities of this module as a service, so that it can be
  used with your mobile app, for example.

Below are the urls and parameters that needs to be passed to those urls:
1. URL: /otp/generate
   parameters: mobile_number
2. URL: /otp/login
   parameters: otp and mobile_number
3. URL: /otp/logout
   parameters: session_id and mobile_number
