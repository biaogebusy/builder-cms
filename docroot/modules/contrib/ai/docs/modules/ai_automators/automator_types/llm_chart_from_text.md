# LLM: Chart From Text

## Field it applies to

- **Field type:** `chart_config`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmChartFromText.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmChartFromText.php?ref_type=heads)

## Description

**LLM: Chart From Text** extracts structured chart data from unstructured text using AI.
It generates CSV output, parses it into chart configurations, and stores it in `chart_config` fields for dynamic data visualization.

## Requirements

- Field must be of type `chart_config`.
- Entity must contain context data rich enough for chart extraction.

## Configuration options

- **Prompt customization:** Allows tailoring prompts for data extraction needs.
- **Color settings:** Predefined chart colors applied to data series.

## Behavior

- Generates CSV from text where the first row is headers and subsequent rows are data.
- Parses CSV into chart data series and applies color coding.
- Stores the structured chart configuration on the entity.

## Example use cases

- Extract hotel names, capacities, and sizes to generate occupancy charts.
- Generate product comparison charts from product descriptions.
- Build performance trend charts from project status updates.
- Visualize budget allocations based on financial summaries.
- Create population charts from demographic reports.
- Summarize survey results into bar charts.
- Generate sales charts from meeting notes.
- Create charts of team assignments from planning documents.
- Visualize attendance data from event reports.
- Build inventory charts from warehouse summaries.

## Notes

- The AI-generated CSV is parsed internally; raw output is not exposed to end users.
- The tool expects a well-formed CSV with keys and data on separate lines.
- Validation checks that the CSV is non-empty but does not perform deep data validation — extend as needed.

## Field Widget Action Integration

To use this automator with a Field Widget Action button in the content edit form, see the complete setup guide:

- [Chart Field + `chart_config_default` widget example](../examples/chart-field.md)

---

*This documentation was AI-generated.*
