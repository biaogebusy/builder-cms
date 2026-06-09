# Metatag Automator

The Metatag Automator allows you to generate metatags from text content using an LLM.

**The [Metatag](https://www.drupal.org/project/metatag) module must be installed and enabled for this automator to work and show up.**

## Overview

### How It Works

1.  When you **Create** an entity, the Automator checks if the metatags field is empty.
2.  If empty, it sends the text (context) to the AI provider.
3.  The AI generates metatags from the text and returns them as structured JSON.
4.  The Automator parses the JSON and populates the metatags field with the extracted data.

The automator intelligently adapts to your metatags field configuration — it only requests tags that are enabled on the field.

### "Edit when changed"

If you check **Edit when changed**, the Automator will re-extract the metatags when the content of the *Base Field* changes. **Warning**: This will overwrite any existing data in the field.

## Prerequisites

1.  **Metatag Module**: You **must** install and enable the [Metatag](https://www.drupal.org/project/metatag) module. Install it via Composer:
    ```bash
    composer require "drupal/metatag ^2.0"
    ```
    Then enable it at `admin/modules` or via Drush:
    ```bash
    drush en metatag
    ```
2.  **Metatag Field**: You must have a Metatag field on your Content Type (e.g., `field_metatag`).
3.  **AI Provider**: You must have an AI Provider with **Chat** capability (e.g., OpenAI, Anthropic).

## Configuration

To configure the Metatag Automator:

1.  Navigate to the **Manage Fields** tab of your entity type (e.g., `Structure > Content types > Article > Manage fields`).
2.  Click **Edit** on the Metatag field you want to automate.
3.  Scroll down to the **AI Automator Settings** section.
4.  Check **Enable AI Automator**.
5.  In the **Choose AI Automator Type** dropdown, select **LLM: Metatag**.

### Base Mode Configuration

**Base Mode** uses one existing text field as the input for metatags generation.

1.  **Automator Input Mode**: Select **Base Mode**.
2.  **Automator Base Field**: Choose the text field that contains data for metatag generation (e.g., "Body" or "Title").
3.  **Automator Prompt**: Enter instructions for the AI on how to generate metatags.
    *   **Standard Usage**: Use `{{ context }}` to pass the text from the base field.

**Example Prompt:**
```text
Based on the context create the different metatag fields according to the instructions for each.

Context:
{{ context }}
```

### Advanced Mode (Token) Configuration

**Advanced Mode** allows you to construct the extraction prompt using any Drupal tokens.

1.  **Automator Input Mode**: Select **Advanced Mode (Token)**.
2.  **Automator Prompt (Token)**: Enter your prompt using tokens.
3.  **Setup {Category}**: For each metatag field you want to extract, add a category with instructions on how to extract it from the context and an example. Leave the ones you do not want to extract empty.

**Example Prompt:**
```text
Based on the context create the different metatag fields according to the instructions for each. Context: [node:title]. [node:body]
```

## Field Widget Action support

In addition to the automator (which runs on entity save), you can add an action button directly on the content edit form. This allows editors to generate metatags on demand while editing content.

**Prerequisite**: You must first configure the automator on the field as described above.

To set this up:

1.  Configure the automator on the metatag field as described in the [Configuration](#configuration) section above.
2.  If you want the automator to **only** run when the user clicks the action button (and not automatically on entity save), set the **Automator Worker** to **Field Widget** in the advanced settings.
3.  Navigate to the **Manage Form Display** tab of your entity type.
4.  Click the settings gear icon on the Metatag widget for your metatag field.
5.  Under **Field Widget Actions**, add the metatag action.
6.  Select the automator to use, enable it, and save.

When editing content, the action button will appear next to the metatag field. Clicking it generates the metatags from the configured source and populates the components in the widget.
