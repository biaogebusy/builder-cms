# Understanding Orchestration

## Agents, Sub agents, Orchestration, Swarms... huh?

These terms can be quite daunting at first, but basically it's just different terms of how agents can work together as agents. Once again you can think of how humans work together to understand how agents can work autonomously or together with humans. The big difference is that agents are usually hyper focused.

We ship three agents together with the AI agents module - a Field agent that can modify and answer questions about fields, a content type agent that can modify and answer questions about content type and a taxonomy agent that can modify and answer questions about vocabularies and taxonomies.

You could use the AI agent Explorer to talk directly to these, but that can be quite cumbersome and for someone new to Drupal it might be hard to understand the concepts of which agent to talk to. It would of course be simpler if you could ask something like `I want to be able to post blog posts with a title and a form that I can style. I also want to categorize my blog posts if they are Personal, Drupal or Technology`. In this case you need to be able to segment out the tasks to different agents and give them instructions in the right order. That is orchestration or multi-agent workflows.

There are a lot of different agent orchestration patterns - Singular agents, Centralized or Hierarchical orchestration, Supervisor orchestrations, [Swarms](https://www.akira.ai/blog/multi-agent-orchestration-with-openai-swarm) or Network orchestration, [Workflow orchestration](https://medium.com/@prabhamatta/ai-agent-vs-agentic-ai-agentic-workflow-orchestration-f3cfa95f4f70). We have some modules for doing Supervisor orchestrations in [Minikanban agents](https://www.drupal.org/project/minikanban_agent), but for this part we will focus on Centralized or Hierarchical orchestrations because that is naturally built into the agent system. It's built on the same principles as [handoffs in OpenAI SDK](https://openai.github.io/openai-agents-python/handoffs/) - any agent is just another tool that any agent can utilize.

## An example - Drupal CMS Assistant agent calling subagents

![agent workflow](https://git.drupalcode.org/project/ai/-/wikis/uploads/8cacb2c386e17a3a537caf134c6205fd/agents-flow.jpg){ width="300", align=left }

### Hierarchy/Centralized

The base idea of a centralized agent orchestration is that you have one agent where everything starts. This agent usually only has other agents that it can call or a limited set or contextualizing tools to gather information before it calls an agent.

This agent will then figure out in what order the agents need to run and if it needs to gather information during one loop before calling the next one. For instance having the agent asking to create a content type and a field on that content type, might go wrong if it's done in the same loop, since the act of creating the field should only happen if the content type was successfully created - it might fail because the prompt lacked information or because it already existed.

When the agent triggers another agent, it does it like any other tool - any agent tool is consistent in its input that it can take one prompt and multiple files as parameters.

### Stateless and purpose-specific prompting

One thing that it is crucial to understand when you are designing agents is that each loop or call to the LLM is completely stateless. This means that you send the first loop to one provider, say OpenAI, and the second to another like Ollama. The state of the agent is always kept by Drupal's agents framework - not by the provider. This is how any agents framework works.

Due to it being stateless also means that you can use a purpose-specific prompt when calling the agents from another agent. So if you have the example of asking to create a content type and a field on that content type - the main agent might realize that it needs some more information from the human instructing it, so it creates a messages history with messages back and forth. Without getting into the depths of how to optimize instructional context length, it's important to know that the longer the instructional context length is, the more confused the LLM can get.

When you are using centralized agents, the agent's response always goes back to the parent agent, meaning that we make sure that the central agent that the human is instructing is the only agent that needs to know the full context of what is happening. When that agent is calling agents, it only needs to pass over the crucial information that specific agent needs.

This differs from a Swarm network, where the full context of the task and current results is passed over from agent to agent.

### Drupal CMS - a simple example

The image to the left (zoom in) goes through a whole orchestration workflow where we have the Drupal CMS Assistant agent, that is calling subagents depending on the user's instructions.

This means that the first agent will look into during the first loop of how and what should be split up and in what order and then go ahead and call on the agents in that order for each of the loop.

The different agents that actually produce changes on the Drupal site will in their turn look at the instructions the Drupal CMS Assistant agent has given to them and figure out based on its system prompt and the tools available on how to solve that issue in the least amount of loops.

Each of these calls have their own distinctive memory of what it has done so far, that differs from the orchestration agent, who is the agent that binds the results together. It's on purpose that we want to send as little information down to the subagent as possible, to make sure that it's only taking care of a specific task.
