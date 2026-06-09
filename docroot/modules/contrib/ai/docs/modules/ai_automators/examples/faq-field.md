# FAQ Field + `faqfield_default` widget

This example shows how to add a "Generate FAQs" button for a FAQ field using the `LLM: FAQ Field` automator and the `Automator FAQ Field` Field Widget Action.

## Prerequisites

- The following modules should be enabled:
  - `ai`
  - `ai_automators`
  - `field_widget_actions`
  - `faqfield`
  - `token`
  - `field_ui`
- At least one AI Provider must be configured and working under `Configuration → AI → Providers`.

## Step 1: Create a FAQ field using `faqfield_default`

1. Go to `/admin/structure/types` and edit your content type (for example, *Article*).
2. Under **Manage fields**, add a new field:
   - **Label**: `FAQ`
   - **Field type**: `FAQ Field (faqfield)`
3. Save the field storage, then on the field settings screen keep the defaults or adjust as needed.
4. Under **Manage form display** for the same content type, make sure the widget for this field is set to `FAQ field default` (`faqfield_default`).

## Step 2: Enable and configure the AI Automator in field settings

The automator is configured directly in the field settings form, not as a separate entity. The FAQ-specific automator is documented in [LLM: FAQ Field automator type](../automator_types/llm_faq_field.md).

1. Go to `/admin/structure/types/manage/[your-type]/fields`.
2. Click **Edit** next to your FAQ field.
3. Scroll down to find the **Enable AI Automator** section.
4. Check **Enable AI Automator** checkbox.
5. Under **Choose AI Automator Type**, select `LLM: FAQ Field`.
   - This dropdown will show all automator types compatible with the `faqfield` field type.
6. The **AI Automator Settings** section will expand. Configure the following:

   **Automator Input Mode:**
   - Select `Base Mode` (or `Advanced Mode (Token)` if you need to use multiple fields as input).
   - For most use cases, `Base Mode` is sufficient and allows you to select one base field as context.

   **Automator Base Field:**
   - Choose the field that should provide the context for generating FAQs, typically a long text field such as `body`.
   - **This is how you point the LLM to the correct content**: The selected base field's value will be injected into the `{{ context }}` token used in the prompt.

   **Automator Prompt:**
   - You can use the default behavior described in the `LLM: FAQ Field` documentation, or customize the prompt.
   - Example custom prompt:

     ```text
     Based on the context text below, generate 5 FAQ entries.
     Each entry must have:
     - question: a clear, user-friendly question
     - answer: a concise answer in 1–3 short paragraphs

     Context:
     {{ context }}
     ```

   - The `{{ context }}` token will be automatically replaced with the value of the base field you selected (for example, the node body).

   **Advanced Settings** (expand to see):
   - **Automator Worker**: Select `Field Widget` (so it can be triggered directly from the form via the Field Widget Action button).
   - **AI Provider**: Select the provider/model you want to use.

7. Click **Save settings** to save the field configuration.

## Step 3: Attach the `Automator FAQ Field` Field Widget Action

Now that the automator is configured on the field, you need to add the Field Widget Action button to make it accessible in the content edit form.

1. Go to `/admin/structure/types/manage/[your-type]/form-display`.
2. Click the gear icon (⚙️) next to your FAQ field (for example, `field_faq`).
3. In the **Field Widget Actions** section:
   - In **Add New Action** dropdown, choose `Automator FAQ Field`.
   - Click **Add action**.
   - In the new action configuration that appears:
     - Check **Enable Automators**.
     - Under **Automator to use for suggestions**, select the automator that was automatically created when you saved the field settings.
       - The automator will be named something like "[Field Label] Default" (e.g., "FAQ Default").
       - It should be the only option available since it's linked to this specific field.
     - Set **Button label** to something like `Generate FAQs`.
   - Click **Update**.
4. Click **Save** at the bottom of the form display page.

The `Automator FAQ Field` plugin lives in `ai_automators/src/Plugin/FieldWidgetAction/FaqField.php` and is only available for `faqfield_default` widgets and `faqfield` fields.

## Step 4: Using the "Generate FAQs" button

1. Create or edit an entity using the configured content type (for example, `/node/add/article`).
2. Fill in the base context field you selected for the automator (for example, the `Body` field).
3. Scroll to the FAQ field:
   - You should see a **Generate FAQs** button associated with the field.
   - The button is provided by the `Automator FAQ Field` Field Widget Action.
4. Click **Generate FAQs**:
   - The request is sent via AJAX; the page should not fully reload.
   - The automator runs `LLM: FAQ Field` with the current node context (primarily the base field you configured).
   - The FAQ field items are populated with question/answer pairs generated by the LLM.
5. Review the generated FAQs:
   - Confirm that each entry has both a question and an answer.
   - Edit individual questions or answers as needed before saving the entity.

## Related documentation

- [LLM: FAQ Field automator type](../automator_types/llm_faq_field.md)
- [AI Automators module](../index.md)
