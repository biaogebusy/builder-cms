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
                 "name": "billboardjs/billboard",
                 "version": "3.10.3",
                 "type": "drupal-library",
                 "extra": {
                     "installer-name": "billboard"
                 },
                 "dist": {
                     "url": "https://registry.npmjs.org/billboard.js/-/billboard.js-3.10.3.tgz",
                     "type": "tar"
                 }
             }
         },
         {
             "type": "package",
             "package": {
                 "name": "d3/d3",
                 "version": "7.8.5",
                 "type": "drupal-library",
                 "extra": {
                     "installer-name": "d3"
                 },
                 "dist": {
                     "url": "https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.js",
                     "type": "file"
                 },
                 "require": {
                     "composer/installers": "^1.0 || ^2.0"
                 }
             }
         }

4. This and the following step is optional but recommended. The reason for
them is that when installing the Billboard.js package with Composer,
additional files are added into the library directory. These files are not
necessary and can be potentially harmful to your site, so it's best to remove
them. So: create a new directory in your project root called "scripts".
5. Inside that directory, create a new file called "clean-billboardjs.sh" and
paste the following into it:

        #!/usr/bin/env bash
        set -eu
        declare -a directories=(
          "web/libraries/billboard/dist-esm"
          "web/libraries/billboard/src"
          "web/libraries/billboard/types"
          "web/libraries/billboard/dist/plugin"
          "web/libraries/billboard/dist/theme"
        )
        counter=0
        echo "Deleting unneeded directories inside web/libraries/billboard"
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
          "web/libraries/billboard/CONTRIBUTING.md"
          "web/libraries/billboard/README.md"
          "web/libraries/billboard/LICENSE"
          "web/libraries/billboard/package.json"
          "web/libraries/billboard/dist/package.json"
        )
        counter=0
        echo "Deleting unneeded files inside web/libraries/billboard"
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
            "clean-billboardjs": "chmod +x scripts/clean-billboardjs.sh &&
             ./scripts/clean-billboardjs.sh",
            "post-install-cmd": [
              "@clean-billboardjs"
            ],
            "post-update-cmd": [
              "@clean-billboardjs"
            ]
        }

7. Run the following command; you should find that new directories have been
created under "/libraries".

        composer require --prefer-dist billboardjs/billboard:3.10.3 d3/d3:7.8.5
