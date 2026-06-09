# LLM: Address

## Field it applies to

- **Field type:** `address`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmAddress.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmAddress.php?ref_type=heads)

## Description

**LLM: Address** extracts structured address data from unstructured text using AI.
It supports filling complex address fields while respecting required fields and hidden components defined in the field settings.

## Requirements

- Field must be of type `address`.
- The Address module must be enabled.
- CommerceGuys Addressing classes must be available.

## Configuration options

- No additional form options beyond standard automator settings.
- Respects field settings like required and hidden components.

## Behavior

- Generates AI prompts to extract address components (e.g., country code, postal code, locality).
- Outputs RFC8259-compliant JSON containing address data.
- Validates required components are present as per field configuration.
- Stores extracted address data in the entity field.

## Example use cases

- Extract a business address from the body of an article.
- Capture event venue details from event descriptions.
- Populate shipping or billing addresses from customer notes.
- Derive address details from contract or agreement text.
- Extract supplier address from uploaded invoices.
- Auto-fill location fields from meeting transcripts.
- Parse addresses from real estate listings.
- Convert address mentions in social media posts to structured data.
- Import addresses from text-based legacy records.
- Capture office locations from company profiles.

## Notes

- The plugin ensures that no fabricated data is generated—only what exists in the context is extracted.
- Field validation enforces compliance with address format requirements.
- This automator depends on the Address module and CommerceGuys Addressing library.
- No prompt output is shown to the end user; only the extracted data is stored.

---

*This documentation was AI-generated.*
