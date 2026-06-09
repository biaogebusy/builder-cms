# Chart Field + `chart_config_default` widget

This example shows how to add a "Generate Chart" button for a Chart field using the `LLM: Chart From Text` automator and the `Automator Chart` Field Widget Action.

## Prerequisites

- The following modules should be enabled:
  - `ai`
  - `ai_automators`
  - `field_widget_actions`
  - `charts` (provides the `chart_config` field type and `chart_config_default` widget)
  - `token`
  - `field_ui`
- At least one AI Provider must be configured and working under `Configuration → AI → Providers`.

## Step 1: Create a Chart field using `chart_config_default`

1. Go to `/admin/structure/types` and edit your content type (for example, *Article*).
2. Under **Manage fields**, add a new field:
   - **Label**: `Chart`
   - **Field type**: `Chart Config`
3. Save the field storage, then on the field settings screen keep the defaults or adjust as needed.
4. Under **Manage form display** for the same content type, make sure the widget for this field is set to `Chart`.

## Step 2: Enable and configure the AI Automator in field settings

The automator is configured directly in the field settings form, not as a separate entity. The Chart-specific automator is documented in [LLM: Chart From Text](../automator_types/llm_chart_from_text.md).

1. Go to `/admin/structure/types/manage/[your-type]/fields`.
2. Click **Edit** next to your Chart field.
3. Scroll down to find the **Enable AI Automator** section.
4. Check **Enable AI Automator** checkbox.
5. Under **Choose AI Automator Type**, select `LLM: Chart From Text`.
   - This dropdown will show all automator types compatible with the `chart_config` field type.
6. The **AI Automator Settings** section will expand. Configure the following:

   **Automator Input Mode:**
   - Select `Base Mode` (or `Advanced Mode (Token)` if you need to use multiple fields as input).
   - For most use cases, `Base Mode` is sufficient and allows you to select one base field as context.

   **Automator Base Field:**
   - Choose the field that should provide the context for generating chart data, typically a long text field such as `body`.
   - **This is how you point the LLM to the correct content**: The selected base field's value will be injected into the `{{ context }}` token used in the prompt.

   **Automator Prompt:**
   - You can use the default behavior described in the `LLM: Chart From Text` documentation, or customize the prompt.
   - Example custom prompt:

     ```text
     From the context text below, extract structured data suitable for a chart.
     Identify the key metrics, categories, and values that can be visualized.
     
     The data should be organized with:
     - Column headers in the first row (e.g., "Item Name", "Value", "Category")
     - Data rows following, with semicolon (;) as delimiter (comma (,) is also supported)
     - Always quote text values that contain commas, semicolons, or special characters
     - Values should be numeric where applicable (no quotes needed for numbers)
     
     Example format:
     "Hotel Name"; "Max Capacity (people)"; "Hotel Size (sqm)"
     "Hotel Radisson, Berlin"; 300; 1280
     "The Vichy, Jamestown"; 840; 3880
     
     Context:
     {{ context }}
     ```

   - The `{{ context }}` token will be automatically replaced with the value of the base field you selected (for example, the node body).
   - The automator expects CSV output where the first row contains headers and subsequent rows contain data values. The system supports both semicolon (;) and comma (,) delimiters, with semicolon being preferred. The delimiter is automatically detected based on the content.

   **Advanced Settings** (expand to see):
   - **Automator Weight**: Set a weight value (default: 100) to control the processing order if you have multiple fields dependent on each other. The higher the value, the later it is processed.
   - **Automator Worker**: Select how the automator processes and saves its generated values:
     - **Direct** (recommended for Field Widget Actions): Processes and saves the value directly. This is the easiest option, but requires longer timeouts since it can take time.
     - **Batch**: Uses JavaScript batch queue (not recommended), will not work on programmatical saving.
     - **Queue/Cron**: Saves as a queue worker and runs on cron.
   - **AI Provider**: Select the provider you want to use (e.g., `OpenAI`).
   - **Provider Configuration** (expand to see):
     - **Model**: Select the specific AI model from your chosen provider.

7. Click **Save settings** to save the field configuration.

## Step 3: Attach the `Automator Chart` Field Widget Action

Now that the automator is configured on the field, you need to add the Field Widget Action button to make it accessible in the content edit form.

1. Go to `/admin/structure/types/manage/[your-type]/form-display`.
2. Click the gear icon (⚙️) next to your Chart field.
3. In the **Field Widget Actions** section:
   - In **Add New Action** dropdown, choose `Automator Chart`.
   - Click **Add action**.
   - In the new action configuration that appears:
     - Check **Enable Automators**.
     - Under **Automator to use for suggestions**, select the automator that was automatically created when you saved the field settings.
       - The automator will be named something like "[Field Label] Default" (e.g., "Chart Default").
       - It should be the only option available since it's linked to this specific field.
     - Set **Button label** to something like `Generate Chart`.
   - Click **Update**.
4. Click **Save** at the bottom of the form display page.

The `Automator Chart` plugin lives in `ai_automators/src/Plugin/FieldWidgetAction/Chart.php` and is only available for `chart_config_default` widgets and `chart_config` fields.

## Step 4: Using the "Generate Chart" button

1. Create or edit an entity using the configured content type (for example, `/node/add/article`).
2. Fill in the base context field you selected for the automator (for example, the `Body` field).
   - Ensure the content contains structured or semi-structured data that can be extracted into chart format (e.g., lists of items with values, comparisons, statistics).
3. Scroll to the Chart field:
   - You should see a **Generate Chart** button associated with the field.
   - The button is provided by the `Automator Chart` Field Widget Action.
4. Click **Generate Chart**:
   - The request is sent via AJAX; the page should not fully reload.
   - The automator runs `LLM: Chart From Text` with the current node context (primarily the base field you configured).
   - The LLM extracts structured data and returns it as CSV format (semicolon or comma delimited).
   - The Chart field's data table is populated with the extracted chart data.
5. Review the generated chart data.

## How the Chart Data is Structured

The `LLM: Chart From Text` automator:

- Generates CSV output where the first row contains column headers (keys) and subsequent rows contain data values
- Supports both semicolon (;) and comma (,) delimiters, with semicolon being preferred. The delimiter is automatically detected.
- Parses the CSV into a chart data structure where:
  - Each column becomes a data series
  - Each row (after the header) becomes a data point
  - Colors are automatically applied to data series
- Stores the data in the `series.data_collector_table` structure of the chart configuration

Example CSV format expected:
```csv
"Hotel Name"; "Max Capacity (people)"; "Hotel Size (sqm)"
"Hotel Radisson, Berlin"; 300; 1280
"The Vichy, Jamestown"; 840; 3880
```

## Related documentation

- [LLM: Chart From Text automator type](../automator_types/llm_chart_from_text.md)
