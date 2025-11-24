# CKEditor 5 Template

This project provides a CKEditor 5 plugin for Drupal. 
It allows to insert predefined content.  

It is a successor of the Drupal ckeditor_template module. 
The predecessor is unfortunately only compatible with CKEditor 4. 
To ensure that the template functionality also works with CKEditor 5, 
we have developed the module. 


## Requirements

This module requires that you enable the CKEditor 5 module in Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

- Go to `/admin/config/content/formats` and drag the template button 
from the available buttons to the active tool list and save
- Configure the template file which should be used

Thats all :)


## Usage

The required templates can now be provided in the following format 
in the previously specified template file:

```json
[
    {
      "title": "Link",
      "icon": "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\"><path d=\"M8 16h8v2H8zm0-4h8v2H8zm6-10H6c-1.1 0-2 .9-2 2v16c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z\"/></svg>",
      "description": "Insert a link to the CKEditor 5 Template project page.",
      "html": "<p>Do you know the cool <a href='https://www.drupal.org/project/ckeditor5_template' target='_blank'>CKEditor 5 Template plugin</a>?</p>"
    },
...
]
```

- title | `unique string` (Each title can exist only once)
- icon | `svg content`
- description | `string`
- html | `any custom html code`


You can find an example in the module folder 
`ckeditor5_template/template/ckeditor5_template.json.example`.


## Demo
![ckeditor5_template](https://github.com/vincenthoehn/ckeditor5_template/assets/78547173/9fda4c64-a416-4b10-b46c-360daeded1b8)


## Maintainers
- Daniel Bielke - [dbielke1986](https://www.drupal.org/u/dbielke1986)
- vincent.hoehn - [vincent.hoehn](https://www.drupal.org/u/vincenthoehn)