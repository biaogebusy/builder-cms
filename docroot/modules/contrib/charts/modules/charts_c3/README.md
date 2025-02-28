#Installation Using Composer (recommended)

If you use Composer to manage dependencies, edit your site's "composer.json"
file as follows.

1. Run the following command to ensure that you have the "composer/installers"
   package installed. This package facilitates the installation of packages into
   directories other than "/vendor" (e.g. "/libraries") using Composer.

        composer require --prefer-dist composer/installers

2. Add the following to the "installer-paths" section of "composer.json":

        "libraries/{$name}": ["type:drupal-library"],

3. Add the following to the "repositories" section of "composer.json":

        {
            "type": "package",
            "package": {
                "name": "c3js/c3",
                "version": "v0.7.20",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "c3"
                },
                "dist": {
                    "url": "https://github.com/c3js/c3/archive/v0.7.20.zip",
                    "type": "zip"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "d3/d3",
                "version": "v4.9.1",
                "type": "drupal-library",
                "extra": {
                    "installer-name": "d3"
                },
                "dist": {
                    "url": "https://github.com/d3/d3/archive/v4.9.1.zip",
                    "type": "zip"
                },
                "require": {
                    "composer/installers": "^1.0 || ^2.0"
                }
            }
        }

4. This and the following step is optional but recommended. The reason for
   them is that when installing the C3.js package with Composer,
   additional files are added into the library directory. These files are not
   necessary and can be potentially harmful to your site, so it's best to remove
   them. So: create a new directory in your project root called "scripts".
5. Inside that directory, create a new file called "clean-c3js.sh" and
   paste the following into it:

        #!/usr/bin/env bash
        set -eu
        declare -a directories=(
          "web/libraries/c3/.circleci"
          "web/libraries/c3/.github"
          "web/libraries/c3/data"
          "web/libraries/c3/design"
          "web/libraries/c3/docs"
          "web/libraries/c3/extensions"
          "web/libraries/c3/htdocs"
          "web/libraries/c3/spec"
          "web/libraries/c3/src"
        )
        counter=0
        echo "Deleting unneeded directories inside web/libraries/c3"
        for directory in "${directories[@]}"
          do
            if [ -d $directory ]; then
              echo "Deleting $directory"
              rm -rf $directory
              counter=$((counter+1))
            fi
          done
        echo "$counter folders were deleted"
        declare -a files=(
          "web/libraries/c3/CONTRIBUTING.md"
          "web/libraries/c3/README.md"
          "web/libraries/c3/LICENSE"
          "web/libraries/c3/package.json"
          "web/libraries/c3/.bmp.yml"
          "web/libraries/c3/.editorconfig"
          "web/libraries/c3/.gitignore"
          "web/libraries/c3/.jshintrc"
          "web/libraries/c3/.prettierrc.json"
          "web/libraries/c3/.travis.yml"
          "web/libraries/c3/bower.json"
          "web/libraries/c3/codecov.yml"
          "web/libraries/c3/component.json"
          "web/libraries/c3/config.rb"
          "web/libraries/c3/Gemfile"
          "web/libraries/c3/Gemfile.lock"
          "web/libraries/c3/karma.conf.js"
          "web/libraries/c3/MAINTAINANCE.md"
          "web/libraries/c3/rollup.config.js"
          "web/libraries/c3/tsconfig.json"
          "web/libraries/c3/yarn.lock"
        )
        counter=0
        echo "Deleting unneeded files inside web/libraries/c3"
        for file in "${files[@]}"
          do
            if [[ -f $file ]]; then
              echo "Deleting $file"
              rm $file
              counter=$((counter+1))
            fi
          done
        echo "$counter files were deleted"

6. Add a "scripts" entry to your "composer.json" file as shown below. If
   "scripts" already exists, you will need to do a little more to incorporate
   the code below.

        "scripts": {
            "clean-c3js": "chmod +x scripts/clean-c3js.sh &&
             ./scripts/clean-c3js.sh",
            "post-install-cmd": [
              "@clean-c3js"
            ],
            "post-update-cmd": [
              "@clean-c3js"
            ]
        }

7. Run the following command; you should find that new directories have been
   created under "/libraries".

        composer require --prefer-dist c3js/c3:0.7.20 d3/d3:4.9.1
