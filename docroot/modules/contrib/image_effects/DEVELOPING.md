# Developing

Contributions to the module are welcome!

Issues and patches should be filed at [Drupal.org].

## Contributing a new image effect

Please note that in order to keep the codebase clean, the code for new image
effects in this module need to follow the [Drupal coding standards]. Only
patches that fulfill the PHPCS checks will be committed.

In addition, there are some specific rules for the image effects and the image
toolkit operations that need to be obeyed to keep your code reviewable and
maintainable in the future.

New effects **MUST HAVE**:
1. an entry in the `README.md` table where effects are listed, including
applicable toolkits
2. a class name (and file) that follows the `[Effect]ImageEffect` naming
convention
2. an `id` in the plugin annotation that follows the `image_effects_[effect]`
naming convention
3. an appropriate config schema entry in
`config/schema/image_effects.schema.yml`, even if the effect has no parameters
4. an implementation of `::transformDimensions` if the effect changes
height/width of the image
5. if the effect has parameters:
    1. an `image_effects_[effect]_summary` entry in `image_effects_theme()` in `image_effects.module`
    2. a Twig template for the effect summary under
    `templates/image-effects-[effect]-summary.html.twig`
    3. the effect form using `'#type' => 'image_effects_color'` form elements to
    capture color information where needed
    4. the effect form using the image selector plugin to capture an image file
    URL if necessary
    5. the effect form using the font selector plugin to capture a font file URL
    if necessary
    6. the effect form using `'#type' => 'image_effects_px_perc'` form elements
    to capture information that can be expressed either as an absolute number or
    as a percentage
6. if image toolkit operation plugins are needed:
    1. a trait with `::arguments` and `::validateArguments` if image toolkit
    operations are implemented, so that the toolkit specific code resides only
    in the `::execute` method of the operation
    2. implementations of operations for both GD and ImageMagick toolkits - if a
    toolkit cannot support the operation, the implementation should be a no-op
    returning TRUE
7. a PHPUnit functional effect test extending from `ImageEffectsTestBase`,
testing both toolkits, testing changes of size if the effect does so (both for
`::applyEffect` and `::transformDimensions`), and doing pixel level testing of
the resulting image if possible

[Drupal.org]: https://drupal.org/project/issues/image_effects
[Drupal coding standards]: https://www.drupal.org/docs/develop/standards
