Flag for Drupal 8
=================

Contents:
 * Introduction
 * Install Node
 * Use node to install yarn
 * Working on flag javascript
 * Building JavaScript

Introduction
------------

We have a javascript environment that parallels that found in D8's core
subdirectory. Modern javascript in the form of es6.js files are transpiled
into backwards compatible javascript targeted to a list of supported browsers.

This document is largely based on the following D8 core change record.

https://www.drupal.org/node/2815083

Note, at the time of this writing Drupal.org's CI does **not** transpile ES6 files
automatically. They **must** be transpiled prior to being committed to the module
repository!

Install Node
------------

There are a variety of ways to install Node JS on your system. In most cases, 
you'll want to use a *package manager* to install Node and keep it updated.
See the following for a complete list on the Node website:

https://nodejs.org/en/download/package-manager/

You may also choose to use Node's own installer:

https://nodejs.org/en/download/current/

To verify installation, open a new terminal session and enter the following:

```shell
node --version
```

If version information is displayed, Node is installed!

Install Yarn
------------

Like Drupal core, Flag module relies on the Yarn dependency manager to transpile
ES6 to vanilla JS. 

You can install Yarn using the `npm` command from Node:

```shell
npm i -g yarn
```

Building JS
-----------

You can build Flag's `*.es6.js` files into regular JS using Yarn:

```shell
cd path/to/module
yarn install
yarn run build:js
```

This will create .js transpiled versions of the .es6.js files. Source maps will not be 
included with the built files.

If you want to only transpile a specific file you can pass the `--file` switch:

```shell
yarn run build:js -- --file misc/drupal.es6.js
````

Using the Yarn Watcher
----------------------

Often you will want to have the JS be rebuilt on any change to the source `*.es6.js` files.
In that case, you will want to use the Yarn watcher.  

```shell
cd path/to/module
yarn install
yarn run watch:js
```

The watcher will transpile any changes to `*.es6.js` files to `*.js`.

If you want to build source maps during development use, `yarn run watch:js-dev`.
