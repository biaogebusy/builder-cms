---
name: create-functional-js-test
description: Creates Drupal FunctionalJavascript tests for the AI module. Validates the ddev environment, Selenium Chrome, and Playwright CLI setup. Manually explores the UI as admin to understand markup, then generates a test class extending BaseClassFunctionalJavascriptTests with screenshots, video recording, and optional AI provider request spoofing.
---

# Create Drupal FunctionalJavascript Test (AI Module)

This skill creates FunctionalJavascript tests for the Drupal AI module. It validates the local environment, manually explores the UI to understand markup, and then generates a proper test class.

## Step 0: Gather Requirements

Ask the user:
1. **What module** is this test for? (e.g., `ai_automators`, `ai_api_explorer`, `ai_content_suggestions`, or the core `ai` module itself)
2. **What page/feature** should the test cover? (e.g., a form, a page, a widget action)
3. **Does the test need AI provider spoofing?** (i.e., does the feature call an AI provider that needs a mock response?)
4. **What assertions** should the test make? (e.g., "a field should contain X", "page should show Y text")

## Step 1: Validate Environment

### 1a. Check ddev is available and running

```bash
which ddev && ddev describe
```

If `ddev` is not found or the project is not running, stop and tell the user:
> This skill requires a running ddev environment. Please run `ddev start` first.

### 1b. Check Selenium Standalone Chrome

```bash
ddev describe | grep -i selenium
```

Also check if the selenium service container is running:
```bash
docker ps --filter "name=ddev.*selenium" --format "{{.Names}} {{.Status}}"
```

If Selenium is not configured, advise the user:
> Selenium Chrome is not configured in this ddev project. To add it:
>
> 1. Create or edit `.ddev/docker-compose.selenium.yaml` with:
> ```yaml
> services:
>   selenium-chrome:
>     container_name: ddev-${DDEV_SITENAME}-chrome
>     image: selenium/standalone-chrome:latest
>     environment:
>       - SE_NODE_MAX_SESSIONS=1
>       - SE_SESSION_REQUEST_TIMEOUT=60
>       - VIRTUAL_HOST=$DDEV_HOSTNAME
>       - HTTP_EXPOSE=4444:4444,7900:7900
>     external_links:
>       - ddev-router:${DDEV_HOSTNAME}
>     volumes:
>       - /dev/shm:/dev/shm
> ```
> 2. Run `ddev restart`
>
> Alternatively, use `ddev get ddev/ddev-selenium-standalone-chrome` for the official addon.

### 1c. Check ffmpeg in ddev web container

ffmpeg is required for video recording. Check if it's available inside the web container:

```bash
ddev exec which ffmpeg
```

If ffmpeg is not found, advise the user to add it to the ddev web container config:

> ffmpeg is not installed in the ddev web container. It's required for video recording in tests.
>
> Add ffmpeg to your `.ddev/config.yaml` under `webimage_extra_packages`:
> ```yaml
> webimage_extra_packages:
>   - ffmpeg
> ```
>
> Then restart ddev:
> ```bash
> ddev restart
> ```
>
> This will install ffmpeg in the web container on next start.

### 1d. Check Playwright CLI

```bash
which playwright 2>/dev/null || npx playwright --version 2>/dev/null
```

If Playwright is not installed, advise:
> Playwright CLI is not installed globally. To install it:
> ```bash
> npm install -g playwright
> # Or use npx:
> npx playwright install chromium
> ```
> Playwright is useful for manually exploring pages and generating selectors.

## Step 2: Admin Access Warning

**IMPORTANT: Before proceeding, warn the user and get explicit confirmation:**

> **WARNING:** To explore the UI and understand the markup, I will need to:
> - Create a one-time login link for the Drupal admin user (user 1)
> - Navigate the site as admin to inspect page structure
> - This gives full admin access to the site
>
> **Do you confirm this is acceptable?**

Wait for explicit user confirmation before proceeding.

## Step 3: Manual UI Exploration

### 3a. Verify drush is available

```bash
ddev drush status
```

### 3b. Create one-time login link

```bash
ddev drush uli --uid=1
```

### 3c. Explore the target page

Use the one-time login link to understand the page structure. Navigate to the target URL using Playwright or a browser:

```bash
# Example: get the page HTML to understand the form structure
ddev drush ev "echo \Drupal::request()->getSchemeAndHttpHost();"
```

Then use WebFetch or Playwright to visit the page and extract:
- Form field names and IDs (e.g., `title[0][value]`, `edit-submit`)
- CSS selectors for buttons, links, and interactive elements
- AJAX-powered elements and their triggers
- The sequence of user interactions needed

**Document everything you find:**
- The URL path to test
- All form fields and their HTML name attributes
- All buttons/links and their selectors
- Expected AJAX behaviors
- Expected outcomes after each interaction

## Step 4: Create the Test

### 4a. Test file location

Tests go in the module's test directory following this pattern:
```
{module_path}/tests/src/FunctionalJavascriptTests/{OptionalSubdir}/{TestName}Test.php
```

For submodules of AI:
```
modules/ai/modules/{submodule}/tests/src/FunctionalJavascriptTests/...
```

For the AI module itself:
```
modules/ai/tests/src/FunctionalJavascriptTests/...
```

### 4b. Test class structure

Every test MUST:

1. **Extend `BaseClassFunctionalJavascriptTests`**:
   ```php
   use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
   ```

2. **Enable required modules** including `ai` and `ai_test`:
   ```php
   protected static $modules = [
     'ai',
     'ai_test',
     // ... other required modules
   ];
   ```

3. **Set the screenshot module name**:
   ```php
   protected $screenshotModuleName = '{module_name}';
   ```

4. **Enable video recording**:
   ```php
   protected $videoRecording = TRUE;
   ```

5. **Take screenshots at each step** using `$this->takeScreenshot('descriptive_name')`:
   - Before any interaction (initial state)
   - After filling forms
   - After clicking buttons/links
   - After AJAX completes
   - At final state for assertion verification

6. **Wait for AJAX properly** - never use hardcoded sleeps:
   ```php
   $this->assertSession()->assertWaitOnAjaxRequest();
   // Or wait for specific elements:
   $this->assertSession()->waitForElement('css', '.some-selector');
   $this->assertSession()->waitForElementVisible('css', '.some-selector');
   ```

### 4c. Complete test template

```php
<?php

namespace Drupal\Tests\{module}\FunctionalJavascriptTests\{OptionalSubNamespace};

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests {description of what is being tested}.
 *
 * @group {module_name}
 */
class {TestName}Test extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    // Add all required modules here.
  ];

  /**
   * {@inheritdoc}
   */
  protected $screenshotModuleName = '{module_name}';

  /**
   * {@inheritdoc}
   */
  protected $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Configure the EchoAI as default provider (if AI calls are made).
    \Drupal::service('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers', [
        'chat' => [
          'provider_id' => 'echoai',
          'model_id' => 'gpt-test',
        ],
      ])
      ->save();

    // Create any required entities, content types, fields, etc.
  }

  /**
   * Tests {description}.
   */
  public function test{MethodName}(): void {
    // Create a user with the necessary permissions.
    $admin = $this->drupalCreateUser([
      // List required permissions.
    ]);
    $this->drupalLogin($admin);

    // Navigate to the page.
    $this->drupalGet('{path}');
    $this->takeScreenshot('1_initial_page');

    // Get the page object.
    $page = $this->getSession()->getPage();

    // Interact with the page.
    $page->fillField('{field_name}', '{value}');
    $this->takeScreenshot('2_after_fill');

    // Click a button or link.
    $this->click('{css_selector}');
    $this->takeScreenshot('3_after_click');

    // Wait for AJAX.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_after_ajax');

    // Assert the expected outcome.
    $this->assertSession()->pageTextContains('{expected_text}');
  }

}
```

### 4d. Available interaction methods

From the base class and WebDriverTestBase:

**Navigation:**
- `$this->drupalGet('/path')` - Navigate to a page
- `$this->drupalLogin($user)` - Log in as a user
- `$this->drupalLogout()` - Log out

**Finding elements:**
- `$page->findField('field_name')` - Find a form field
- `$page->findButton('button_text')` - Find a button
- `$page->findLink('link_text')` - Find a link
- `$page->find('css', '.css-selector')` - Find by CSS selector
- `$page->findById('element-id')` - Find by ID

**Interacting:**
- `$page->fillField('field_name', 'value')` - Fill a text field
- `$page->selectFieldOption('field_name', 'option')` - Select dropdown option
- `$page->checkField('field_name')` - Check a checkbox
- `$page->pressButton('button_text')` - Press a button
- `$this->click('.css-selector')` - Click any element by CSS

**Waiting:**
- `$this->assertSession()->assertWaitOnAjaxRequest()` - Wait for AJAX
- `$this->assertSession()->waitForElement('css', '.selector')` - Wait for element
- `$this->assertSession()->waitForElementVisible('css', '.selector')` - Wait for visible element
- `$this->assertSession()->waitForButton('text')` - Wait for button
- `$this->assertSession()->waitForField('name')` - Wait for field
- `$this->assertSession()->waitForLink('text')` - Wait for link
- `$this->assertJsCondition('js expression')` - Wait for JS condition

**Assertions:**
- `$this->assertSession()->pageTextContains('text')` - Page has text
- `$this->assertSession()->pageTextNotContains('text')` - Page lacks text
- `$this->assertSession()->fieldValueEquals('field', 'value')` - Field has value
- `$this->assertSession()->elementExists('css', '.selector')` - Element exists
- `$this->assertSession()->statusCodeEquals(200)` - HTTP status code

**Screenshots and debugging:**
- `$this->takeScreenshot('name')` - Take screenshot (from BaseClassFunctionalJavascriptTests)
- `$this->htmlOutput($page->getHtml())` - Dump HTML for debugging

## Step 5: AI Provider Request Spoofing (if needed)

If the feature under test calls an AI provider, you need to create a mock request/response YAML file.

### 5a. YAML file location

```
{module_path}/tests/resources/ai_test/requests/{operation_type}/{UniqueTestName}.yml
```

Where `{operation_type}` is almost always `chat`.

### 5b. YAML format

The request must exactly match what the code sends. The structure is:

```yaml
request:
  messages:
    - role: user
      text: 'The exact prompt text the code will send'
      images: {}
      tools: null
      tool_id: null
  debug_data: {}
  chat_tools: null
  chat_structured_json_schema: {}
  chat_strict_schema: false
response:
  normalized:
    role: assistant
    text: 'The mock response text'
    images: {}
    tools: null
    tool_id: null
  rawOutput: []
  metadata: {}
  tokenUsage:
    input: null
    output: null
    total: null
    reasoning: null
    cached: null
wait: 0
label: 'Descriptive Label For This Test'
tags:
  - chat
  - {module_name}
operation_type: chat
```

**CRITICAL:** The `request` section must exactly match the serialized form of the input that the code under test will produce. The EchoProvider matches requests by comparing the JSON-encoded `toArray()` output. If the request doesn't match exactly, the EchoProvider will fall back to its default echo response instead of your mock.

### 5c. How to capture the exact request

The easiest way to get the exact request format:

1. Enable the ai_test module on the site: `ddev drush en ai_test`
2. Go to `/admin/config/ai/providers/ai-test`
3. Enable "Catch results"
4. Trigger the feature that makes the AI call
5. Go to `/admin/content/ai-mock-provider-result`
6. Find the captured request and use the "Export to test" operation
7. Place the exported YAML file in the correct location

Alternatively, run the test once with a debug breakpoint in `EchoProvider::getMatchingRequest()` to see the exact input format.

### 5d. Setting EchoAI as default provider in setUp()

```php
protected function setUp(): void {
  parent::setUp();
  \Drupal::service('config.factory')
    ->getEditable('ai.settings')
    ->set('default_providers', [
      'chat' => [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ],
    ])
    ->save();
}
```

## Step 6: Verify the Test

After creating the test file and any mock YAML files, verify:

1. **File locations are correct** - test class and mock YAMLs are in the right directories
2. **Namespace matches directory structure**
3. **Run the test**:
   ```bash
   ddev exec phpunit --filter='{TestClassName}' web/modules/custom/ai/{path_to_test}
   ```
   Or with the full Drupal test runner:
   ```bash
   ddev exec php core/scripts/run-tests.sh --class 'Drupal\Tests\{module}\FunctionalJavascriptTests\{TestClass}'
   ```

4. **Check screenshots** at `sites/default/files/simpletest/screenshots/{module_name}/`
5. **Check video** at `sites/default/files/simpletest/videos/{module_name}/`

## Reference: Existing Test Examples

Study these existing tests for patterns:

- `modules/ai_automators/tests/src/FunctionalJavascriptTests/Plugin/FieldWidgetAction/AutoCompleteTagsTaxonomyTest.php` - Full example with entity setup, config from YAML, form interaction, AJAX wait, field value assertion
- `modules/ai_api_explorer/tests/src/FunctionalJavascript/Plugin/AiApiExplorer/ChatExplorerTest.php` - Simpler example with video recording, EchoAI config, form fill, AJAX wait, text assertion
- Mock YAML: `modules/ai_api_explorer/tests/resources/ai_test/requests/chat/Hello_There_AI_API_Explorer_Test.yml` - Full YAML format with rawOutput, label, tags, operation_type
- Mock YAML: `modules/ai_automators/tests/resources/ai_test/requests/chat/AutoCompleteTagsTaxonomyTest.yml` - Minimal YAML format

## Important Notes

- **Never use hardcoded waits** (`sleep()`, `usleep()`) in tests. Always use `waitFor*` methods or `assertWaitOnAjaxRequest()`.
- **Always validate element existence** with `$this->assertNotEmpty($element)` before interacting with it.
- **The `$strictConfigSchema` is already set to FALSE** in the base class - no need to redeclare it.
- **The `$defaultTheme` is already set to `'claro'`** in the base class.
- **Screenshots are auto-organized** by module name and test class name into subdirectories.
- **Video recording requires the Selenium container** to have an accessible X display (`:99.0`).
- The `ai_test` module must be in `$modules` for the EchoProvider to be available.
- The `$settings['extension_discovery_scan_tests'] = TRUE;` setting must be in `settings.php` for functional tests to find test modules.
