# Installation using Composer (recommended)

If you use Composer to manage dependencies, edit your site's "composer.json"
file as follows.

1. Run `composer require --prefer-dist composer/installers` to ensure that
   you have the "composer/installers" package installed. This package
   facilitates the installation of packages into directories other than
   "/vendor" (e.g. "/libraries") using Composer.

2. Add the following to the "installer-paths" section of "composer.json":

        "libraries/{$name}": ["type:drupal-library"],

3. Add the following to the "repositories" section of "composer.json":

        {
            "type": "package",
            "package": {
                "name": "highcharts/highcharts",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/highcharts.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/more",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_more"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/highcharts-more.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/exporting",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_exporting"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/exporting.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/export-data",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_export-data"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/export-data.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/accessibility",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_accessibility"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/accessibility.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/3d",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_3d"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/highcharts-3d.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/annotations",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_annotations"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/annotations.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/boost",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_boost"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/boost.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/coloraxis",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_coloraxis"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/coloraxis.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/data",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_data"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/data.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/high-contrast-light",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_high-contrast-light"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/themes/high-contrast-light.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/pattern-fill",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_pattern-fill"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/pattern-fill.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/no-data-to-display",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_no-data-to-display"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/no-data-to-display.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "highcharts/solid-gauge",
                "version": "12.1.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "highcharts_solid-gauge"
                },
                "dist": {
                    "url": "https://code.highcharts.com/12.1.1/modules/solid-gauge.js",
                    "type": "file"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        }

4. Run `composer require --prefer-dist highcharts/highcharts:12.1.1
highcharts/more:12.1.1 highcharts/exporting:12.1.1
highcharts/export-data:12.1.1 highcharts/accessibility:12.1.1
highcharts/3d:12.1.1 highcharts/annotations:12.1.1 highcharts/boost:12.1.1
highcharts/coloraxis:12.1.1 highcharts/data:12.1.1
highcharts/high-contrast-light:12.1.1 highcharts/pattern-fill:12.1.1
highcharts/no-data-to-display:12.1.1 highcharts/solid-gauge:12.1.1`
- you should find that new directories have been created under "/libraries"
