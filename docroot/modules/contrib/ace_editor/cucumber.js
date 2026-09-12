// Default cucumber-js config for the Ace Editor varbase-e2e suite.
//
// Runs the Drupal Standard profile feature set (Olivero front end, Claro
// admin theme) under tests/features/**.
//
//   npx cucumber-js --config cucumber.js
//
// Reusable step definitions ship with varbase-e2e; the module-specific steps
// live in tests/step-definitions/ace_editor.steps.js. Artefacts land directly
// under tests/reports, tests/screenshots and tests/videos.
//
// The suite drives a Drupal Standard site through the browser only and
// exercises the three ways the module surfaces the Ace code editor:
//
//   1. As a CKEditor-style text *editor* on node/block edit forms
//      (Drupal.editors.ace_editor, see js/editor.js).
//   2. As a read-only field *formatter* "Ace Format" on a rendered entity
//      (Drupal.behaviors.ace_formatter, see js/formatter.js).
//   3. As a text *filter* turning <ace> … </ace> snippets into highlighted,
//      read-only editors (Drupal.behaviors.ace_filter, see js/filter.js).

const worldParameters = {
  launchUrl: process.env.LAUNCH_URL || 'http://localhost',
  // Test users. The Webmaster row is the `drush site:install` super-admin
  // created with `--account-name=webmaster --account-pass=…`. Every other row
  // is provisioned by `Given I add testing users` through /admin/people/create.
  users: {
    'Webmaster': {
      username: 'webmaster',
      email: 'webmaster@example.test',
      password: 'dD.123123ddd',
      isAdmin: true,
    },
    'Content editor': {
      username: 'content_editor_user',
      email: 'content_editor_user@example.test',
      password: 'dD.123123ddd',
      roles: ['content_editor'],
    },
    'Authenticated user': {
      username: 'authenticated_user',
      email: 'authenticated_user@example.test',
      password: 'dD.123123ddd',
      roles: [],
    },
  },
  minWaitTime: {
    page: 3000,
    before_scenario: 0,
    after_scenario: 0,
    before_step: 0,
    after_step: 0,
  },
  selectors: {
    css: {},
    xpath: {},
    filesPath: './tests/selectors/',
    files: [
      'cms-drupal-core-claro.json',
      'ace_editor.json',
    ],
    offset: 60,
    breakpoints: {
      xs:  { width: 375,  height: 667  },
      sm:  { width: 576,  height: 800  },
      md:  { width: 768,  height: 1024 },
      lg:  { width: 992,  height: 768  },
      xl:  { width: 1200, height: 900, default: true },
      xxl: { width: 1400, height: 900 },
    },
  },
  screenshot: {
    dir: './tests/screenshots',
    purge: false,
    onFailed: true,
    onEveryStep: false,
    alwaysFullscreen: false,
    failedPrefix: 'failed_',
    filenamePattern: '{datetime}.{feature_file}.feature_{step_line}.{ext}',
    filenamePatternFailed: '{failed_prefix}{datetime}.{feature_file}.feature_{step_line}.{ext}',
    infoTypes: '',
  },
  video: {
    mode: 'on-failure',
    dir: './tests/videos',
    size: { width: 1280, height: 720 },
    filenamePattern: '{datetime}.{feature_file}.{scenario}.{status}.{ext}',
  },
  javascript: {
    mode: 'warn',
    levels: ['error'],
    ignore: '',
    beforeScenario: false,
    afterScenario: true,
  },
};

module.exports = {
  default: {
    timeout: 60000,
    requireModule: ['tsx/cjs'],
    require: [
      'node_modules/@vardot/varbase-e2e/tests/step-definitions/**/*.js',
      'tests/step-definitions/**/*.js',
    ],
    paths: ['tests/features/**/*.feature'],
    format: [
      '@cucumber/pretty-formatter',
      'json:tests/reports/cucumber_report.json',
      'junit:tests/reports/junit.xml',
    ],
    worldParameters,
  },
};
