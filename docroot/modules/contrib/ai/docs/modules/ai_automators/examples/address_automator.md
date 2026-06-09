# Address Automator

The Address Automator allows you to automatically extract and populate structured address data from text content using an LLM. This is useful for parsing addresses from unstructured text (e.g., a body field or imported content) into properly formatted address fields.

**The [Address](https://www.drupal.org/project/address) module must be installed and enabled for this automator to work and show up.**

## Overview

### How It Works

1.  When you **Create** an entity, the Automator checks if the address field is empty.
2.  If empty, it sends the text (context) to the AI provider.
3.  The AI extracts address components from the text and returns them as structured JSON.
4.  The Automator parses the JSON and populates the address field with the extracted data (country code, locality, postal code, address lines, etc.).

The automator intelligently adapts to your address field configuration — it only requests address components that are enabled on the field and marks required components accordingly.

### "Edit when changed"

If you check **Edit when changed**, the Automator will re-extract the address when the content of the *Base Field* changes. **Warning**: This will overwrite any existing address data in the field.

## Prerequisites

1.  **Address Module**: You **must** install and enable the [Address](https://www.drupal.org/project/address) module. Install it via Composer:
    ```bash
    composer require "drupal/address ^2.0"
    ```
    Then enable it at `admin/modules` or via Drush:
    ```bash
    drush en address
    ```
2.  **Address Field**: You must have an Address field on your Content Type (e.g., `field_address`).
3.  **AI Provider**: You must have an AI Provider with **Chat** capability (e.g., OpenAI, Anthropic).

## Configuration

To configure the Address Automator:

1.  Navigate to the **Manage Fields** tab of your entity type (e.g., `Structure > Content types > Article > Manage fields`).
2.  Click **Edit** on the Address field you want to automate.
3.  Scroll down to the **AI Automator Settings** section.
4.  Check **Enable AI Automator**.
5.  In the **Choose AI Automator Type** dropdown, select **LLM: Address**.

### Base Mode Configuration

**Base Mode** uses one existing text field as the input for address extraction.

1.  **Automator Input Mode**: Select **Base Mode**.
2.  **Automator Base Field**: Choose the text field that contains address information (e.g., "Body" or "Title").
3.  **Automator Prompt**: Enter instructions for the AI on how to extract the address.
    *   **Standard Usage**: Use `{{ context }}` to pass the text from the base field.

**Example Prompt:**
```text
Based on the context text return all addresses listed.

Context:
{{ context }}
```

### Advanced Mode (Token) Configuration

**Advanced Mode** allows you to construct the extraction prompt using any Drupal tokens.

1.  **Automator Input Mode**: Select **Advanced Mode (Token)**.
2.  **Automator Prompt (Token)**: Enter your prompt using tokens.

**Example Prompt:**
```text
Extract the address from the following: [node:title]. [node:body]
```

### Address Field Components

The automator automatically detects which address components are enabled and required on your field. The full set of possible components includes:

| Component            | JSON Property          |
|----------------------|------------------------|
| Country Code         | `country_code`         |
| Address Line 1       | `address_line1`        |
| Address Line 2       | `address_line2`        |
| Locality (City)      | `locality`             |
| Administrative Area  | `administrative_area`  |
| Postal Code          | `postal_code`          |
| Sorting Code         | `sorting_code`         |
| Dependent Locality   | `dependent_locality`   |
| Given Name           | `given_name`           |
| Additional Name      | `additional_name`      |
| Family Name          | `family_name`          |
| Organization         | `organization`         |

Components that are set to **Hidden** in the address field settings will be excluded from the AI prompt. Components marked as **Required** will be flagged as such to the AI.

### Test the functionality

1.  Go to **Content** > **Add content** > **Article** (or the entity type you configured).
2.  In the **Body** field (or your chosen *Base Field*), paste text that contains an address. Use the example below:
    > Our office is located at 1600 Amphitheatre Parkway, Mountain View, CA 94043, United States.
3.  Scroll to the **Address** field.
4.  *Option A (Manual Generation):* If you see the **AI Automator** button (magic wand/robot icon), click it.
    *   Wait for the Ajax loader to finish.
    *   Check the widget. The address fields should be populated with the extracted data.
5.  *Option B (Automatic Generation):* If no button is present, simply click **Save**.
    *   The page will reload (or redirect).
    *   Check the saved content. The address should be filled in with: Country = US, Address Line 1 = 1600 Amphitheatre Parkway, City = Mountain View, State = CA, Postal Code = 94043.

## Field Widget Action support

In addition to the automator (which runs on entity save), you can add an action button directly on the content edit form. This allows editors to extract addresses on demand while editing content.

**Prerequisite**: You must first configure the automator on the field as described above.

To set this up:

1.  Configure the automator on the address field as described in the [Configuration](#configuration) section above.
2.  If you want the automator to **only** run when the user clicks the action button (and not automatically on entity save), set the **Automator Worker** to **Field Widget** in the advanced settings.
3.  Navigate to the **Manage Form Display** tab of your entity type.
4.  Click the settings gear icon on the Address widget for your address field.
5.  Under **Field Widget Actions**, add the address action.
6.  Select the automator to use, enable it, and save.

When editing content, the action button will appear next to the address field. Clicking it extracts the address from the configured source and populates the address components in the widget.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **LLM: Address** does not appear in the automator dropdown | Make sure the [Address](https://www.drupal.org/project/address) module is installed and enabled. The automator requires both the module and the `commerceguys/addressing` library. |
| Address field is not populated after save | Verify your prompt includes `{{ context }}` (Base Mode) or valid tokens (Advanced Mode) and that the source text actually contains an address. |
| Country code is missing/incorrect | The AI must return a valid ISO 3166-1 alpha-2 country code (e.g., `US`, `DE`, `GB`). Check the AI response in logs if available. |
| Required fields are empty | Ensure the source text contains enough information for the AI to extract all required components. Consider adjusting your prompt to be more specific. |
