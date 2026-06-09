# AI External Moderation
## What is the AI External Moderation module?
The AI External Moderation module intercepts input sent to an LLM by other
AI Core-based modules and sends it to a configured Moderation provider before it
is run. If the LLM indicates that the input is unsafe, the operation will be
prevented from completing. **If you are using only the OpenAI LLM, you will not
need to enable this module** as all calls using this provider are automatically
moderated before being sent.

## How to configure the AI External Moderation module
For more information, please see the [AI External Moderation module documentation](https://project.pages.drupalcode.org/ai/latest/modules/ai_external_moderation/index.md).
