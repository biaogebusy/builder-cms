# MDXEditor

## Why the editor is included in AI core?

Check the reasons for this [here](https://www.drupal.org/project/ai/issues/3570097).

## How can I use the editor?

All form elements of type `textarea` can use the editor. You need to add the attribute
`data-mdxeditor` to your element and the editor will be added automatically. If you
need to pass some configuration to the editor, the attribute should be a unique identifier
that will also be used in `drupalSettings`. For example for typeahead plugin config:

```php
    $typeahead_config = [
      // Here comes some configuration for typeahead plugin.
    ];
    $editor_id = '<some_unique_id>';
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#attributes' => [
        'data-mdxeditor' => $editor_id,
      ],
      '#attached' => [
        'drupalSettings' => [
          'mdxeditor' => [
            $editor_id => [
              'plugins' => [
                'typeaheadPlugin' => $typeahead_config,
              ],
            ],
          ],
        ],
      ],
    ];
```

## Why build editor is not included?

The built assets are only included for tagged versions of the module. If you use the branch
for development you need to build the assets yourself. For this you will need to have `npm`
running locally. The minimum required version of `node` is `20.14.0`.

To build the editor assets follow the instructions:

1. Go to `ui/mdxeditor` folder.
2. Run `npm install`
3. Run `npm run build`
4. Do not commit the changes from `ui/mdxeditor/dist` folder.
