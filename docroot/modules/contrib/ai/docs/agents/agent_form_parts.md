# Agent Form Parts

While how you add these parts might change in the future, how they actually are used will not likely change that much. These parts are the parts that build the agent form and will showcase how they work together when running an agent.

## Title

The title is the actual title of the agent. It is used to identify the agent and a version of it will also be used as the data name of the agent. It is the thing you see in the agent list. This will also be used as the label of the agent when you add it to a chatbot, or an orchestration tool like Minikanban agents. The field is required. Note that the title is not used in the decision making of the agent itself.

## Description

While people do not fill out the description field in Drupal very often, it is of uttermost importance in the case of agents. The description is used when other agents use this agent as a tool. The decision making of the agent that is using this agent as a tool is based on the description. So if you want to use this agent in an orchestration, make sure that the description is clear, concise and verbose about what the agent's capabilities are and also what it can't solve. Because of its importance, the description field is required, in contrast to many other entity types in Drupal.

Note that the description is not used in the decision making of the agent itself, but rather by other agents that might use this agent as a tool.

## Swarm Orchestration Agent

This is a pure category checkbox that any orchestration tool can use when listing the agents. It's to tell orchestration tools that this agent is an agent that manages other agents predominantly as the main parent. This does not affect the agent itself while running and is just a categorization.

## Project Manager Agent

This is a pure category checkbox to notify the user that if many agents work together to solve a complex task like Views or Webform, this agent is the one that manages the other agents, but in comparison to Orchestration Agents this agent still only has one concern. This does not affect the agent itself while running and is just a categorization.

## Max Loops

The max loops is the maximum number of loops that the agent can run before it stops. This is to prevent agents from getting stuck in a loop where they can't solve a problem. If the agent reaches the maximum number of loops, it will stop and return an error message. The default value is 3, but you can change it to any value you want.

## Agent Instructions

The agent instructions is the main part of the agent form. This is where you write down how the agent should handle different scenarios. The instructions are used by the agent to make decisions on what tools to use and how to answer questions. The prompt engineering is crucial here, so read up on best practices for prompt engineering.

Be verbose with information, but precise with instructions. Give examples of what the agent should do and what it should not do. The agent instructions are used in every loop, so make sure that they are clear and concise. Make it clear that the agent is a looping agent and what it is capable of doing. Starting with the following prompt when doing something internal is a good start:

```
You are a looping agent that specializes in Drupal 11
```

You may use tokens here.

## Default Information Tools

Default information tools automatically load into the agent's memory on each loop, providing essential context without requiring agent decisions. These tools gather dynamic information about the capabilities that you want to custom-build into the agent. For example, a Field Agent might automatically receive lists of field types, form widgets, and display widgets specific to the current site. Remember that, because the specific nature of this data is likely to vary between different Drupal installations, it must be gathered dynamically.

To set this up, define the raw data as a YAML array with the following structure:

```yaml
[any_unique_key]:
  label: 'The label of the dataset. Required.'
  description: 'A description of the dataset - this can be used to describe tools that are generic, to be more exact. Optional.'
  tool: 'The id of the tool to used'
  parameters:
    [parameter_name]: 'The value of the parameter. This can be a token. Required parameters are required to fill out here.'
  available_on_loop: 'An array of numbers that on which loops this tool should be available. Optional and should generally not be used without knowledge.`
```

Say we need an AI agent that's an expert on the "article" node type and always needs to know what fields are available - we could configure the following:

```yaml
article_node_fields:
  label: 'Article node fields'
  description: 'The fields available on the Article node type.'
  tool: 'ai_agent:get_entity_field_information'
  parameters:
    entity_type: 'node'
    bundle: 'article'
```

Now the agent would know on any loop what fields are available on the Article node type and can use that information to answer questions about it. Since loops are expensive and should be avoided, this approach prevents having to run the same tool repeatedly.

## Tools

The tools are the actual tools that the agent can use to perform actions on the Drupal site. These are the interactions that the agent can use to actually perform actions on the Drupal site. The tools are used by the agent to make decisions on what actions to take and how to answer questions.

When you add a tool, you need to make sure that it is relevant to the agent's capabilities. For example, if the agent is a Field Agent, it might have tools that list all field types, form widgets, and display widgets available on the site. The tools are used in the decision-making process of the agent and should be chosen carefully.

Try to use as narrow tools as possible, so that the agent can make decisions based on the specific capabilities of the tool. This will help the agent to be more accurate in its answers and actions.

You will also see the other agents here if you want to use them as tools. This is a way to use other agents as tools in your agent's decision-making process.

If you install the `AI API Explorer` module, you can also use the `Test this tool` button to see how the tool works and what parameters it takes.

## Detailed Tool Usage

The detailed tool usage is a way to define how the tool should be used in the agent. This is where you can set restrictions on the parameters of the tool and how they should be used. You can also set default values for the parameters and make them required or optional.

Each of the detailed tool usage has per tool:

### Return Directly

By default the agent will use the result of the tools to generate a natural language text output or summary. This costs time and tokens. But if you know that the tool's result is a perfectly good answer, enabling this option will save processing time and reduce token costs by bypassing the agent's text generation step.

### Require Usage
By default the agent can decide whether to use a tool or not. This option forces the agent to use the tool at least once before completing its task. This is useful if you know that the tool is essential for solving the task. The system will validate that the tool was called before accepting a final text response. So make sure to set the number of loops accordingly.

### Override Tool Description
For production deployments, custom descriptions help you explain how the agent understands and uses the tool, leading to more reliable and predictable behavior. This replaces the default tool description with your custom instructions.

### Use Artifact Storage
By default the agent will store the result in the history of the agent. This is useful if the agent has to reason in a later loop about what it has done in a previous loop.

There are however use cases where the agent's reasoning is not dependent on the output of the tool, but rather just needs to perform an action and move that output to the input of another tool. In these cases, you can enable this option and the output of the tool will be stored in artifact storage instead of the history. Using artifact storage can significantly reduce input tokens. If you enable artifact storage, just make sure that you specify in the Agent Instructions (system prompt) how the agent should use the artifact token. For example:

```
You are a looping agent that specializes in Drupal 11. You have access to the following tools
- Tool A: Does something and stores the result in the token {{artifact:ai_agents:tool_a:1}}. You should use this token when using Tool B.
- Tool B: Does something with the result of Tool A. For the input parameter text, you should use the token {{artifact:ai_agents:tool_a:1}}.
```

### Property Restrictions/Restrictions for property {name}

This is where you can set restrictions on the parameters of the tool. You can set the following restrictions: `Allow all`, `Only allow certain values`, `Force value`.

By default the restrictions are set to `Allow all`, which means that the agent can use any value for the parameter. If you want to restrict the values that can be used, you can set it to `Only allow certain values` and then add the values that are allowed comma separated.

If you force the value, you can set it to `Force value` and then add the value that should be used. This is useful if you know that the tool should only be used with a specific value, like the entity type for a field tool. You can also then choose the checkbox `Hide property`, which will hide the property from the agent's decision-making process. This is useful if you want to make sure that the agent does not use the property in its decision-making process or for its answer.
