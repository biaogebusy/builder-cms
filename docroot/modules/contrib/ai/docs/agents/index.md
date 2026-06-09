# Agents - Getting Started

This documentation will both be targeted against site builders that want to build agents and tools without having to or having the knowledge how to code and against developers that want to develop custom tools and create custom triggers of the agent run process. This documentation is WIP.

## How to run your first agent to get a base understanding of what an agent is

1. Install the AI module, AI Agents module and some AI provider that has a model that supports Chat with Tools.
2. Install the AI Agent Explorer - this will be our custom trigger.
3. Install the AI API Explorer - this will be used to showcase how to trigger a tool.
3. Visit /admin/config/ai/agents, you should see some agents with type config as part of the list.
4. Find the Field Agent and click Edit on it.
5. You will come to a form or a modeller depending on your setup.
6. Scroll down to the Agent Instructions and read them. These are the instructions that are given to the agent on any process that it runs.
7. After that scroll down to the Tools. Check the tools that are marked and see what they are capable of. These are the interactions that the agent can use to actually perform actions on the Drupal site. Look at it and contemplate if you could answer the question `What field type and how many elements do you have to fill out on the title on the Basic Page?` with any of them.
8. Find the tool `Get Entity Field Information` and click on `Test this tool`. A new tab should open.
9. This will showcase this tool and what parameters it takes. Try to fill in `node` under `entity_type`, `page` under `bundle` and `title` under `field_name` and click on `Run Function`
10. On the right side you should see the output of that function. Would you be able to answer the question above? Most likely yes.
11. Close the tab and click back so you come to the agent list again.
12. Click `Explore` on the Field Agent.
13. This form is a way to trigger an agent, see the tools it picks and uses and the textual output of when it's finished.
14. In the `Prompt` field write `What field type and how many elements do you have to fill out on the title on the Basic Page?`. Now it's the agent's time to try to figure out what you did just figure out.
15. Click `Run Agent`
16. On the right side you should see things happening.
17. First it should run `ai_agents_get_entity_field_information` which is the data name of the `Get Entity Field Information` tool. You will also see that it has been able to figure out how to put in the parameters correctly. You will most likely see that it set `entity_type` to `node` and `bundle` to `page`. It could also write `title` in the `field_type` parameter. This is the first loop, where the agent gathers information
18. The second row, which is the textual output is the second loop where the agent writes out a textual output, because it thinks it's finished. This will be the same text that is in the `Final Answer` part, just not formatted as HTML.
19. Hopefully it should have written an answer that answers your question.

## So what did just happen?

If the LLM is smart enough, it took the exact same decisions you would take. That is the essence of the agent - decision making.

## Trained knowledge vs. given knowledge

One thing that was not explained in the example above was that the agent also has some knowledge about your system when it starts. While the LLM might be trained on the knowledge about Drupal, you should never assume this - in theory you could add a Vocabulary that is called Basic Page and with title you mean Label.

So if you go back to the form you will see that the agent has some data that is dynamically loaded into its memory for every loop without it having to take a decision about it - you can see this in the `Default Information Tools`. With the Field Agent it has the information about what field types, form widgets and display widgets exist on the website and a list of what fields exist on which entities.

This also uses tools to load this information, since it might differ from website to website, but it doesn't have to take a decision about it.

You can think about it like giving the knowledge you have about your own website already.

