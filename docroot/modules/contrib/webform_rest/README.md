## CONTENTS OF THIS FILE

 - Introduction
 - Requirements
 - Installation
 - Configuration
 - Maintainers


## INTRODUCTION

Retrieve and submit webforms via REST.

1. Enable module
2. Enable REST resource "Webform Submit"
3. Enable REST resource "Webform Elements"
4. Enable REST resource "Webform Fields"
5. Enable REST resource "Webform Submission"

Note: This module does not provide UI to enable
REST resources. Use [restui](https://www.drupal.org/project/restui)
module, for example, to enable resource "Webform Submit",
"Webform Elements", etc.


## REQUIREMENTS

This module requires:
- webform
- restui


## INSTALLATION

 - Install as you would normally install a contributed Drupal module. Visit
   <https://www.drupal.org/docs/extending-drupal/installing-modules>
   for further information.

## CONFIGURATION

- Retrieve Webform Elements

Returns all form elements including render array.
GET /webform_rest/{webform_id}/elements?_format=json

- Retrieve Webform Fields

Returns form fields.
GET /webform_rest/{webform_id}/fields?_format=json

- Submit Webform

POST /webform_rest/submit
Example POST data:
{
  "webform_id": "my_webform",
  "checkboxes_field": [
    "Option 3",
    "Option 5"
   ],
   "integer_field": 3,
   "radio_field": "Mail",
   "email": "myemail@mydomain.com.au"
}

- Update Webform Submission

PATCH /webform_rest/{webform_id}/submission/{uuid}?_format=json
Example PATCH data:
{
  "checkboxes_field": [
    "Option 3",
    "Option 5"
   ],
   "integer_field": 3,
   "radio_field": "Mail",
   "email": "myemail@mydomain.com.au"
}

- Retrieve Webform Submission

GET /webform_rest/{webform_id}/submission/{uuid}?_format=json


## MAINTAINERS

Current maintainers:
- Ian McLean (imclean) - <https://www.drupal.org/u/imclean>
- Debora Antunes (dgaspara) - <https://www.drupal.org/u/dgaspara>
- João Marques (joaomarques736) - <https://www.drupal.org/u/joaomarques736>
- Nelson Alves (nsalves) - <https://www.drupal.org/u/nsalves>
