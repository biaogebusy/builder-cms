# What is an agent?

## What is a tool?

Before we introduce agents, let's start lower down the chain - the tools. Tools are different pieces of code or services that you can run on either your Drupal machine (function calling) or via the LLM's services (service tools) that can execute some kind of action.

The most obvious tool is the calculator - LLM's are not logical machines, but rather trained response, so while they can come up with mathematical equations that the top math researchers have problems with because it's language, they might still have problems with something as simple as 194*142 because they haven't been trained on that exact number.

The solution is to use a tool. A specific tool that we can develop ourselves and run on our Drupal site is called a function call. A function call by itself only requires a function name, description and structured input parameters. The LLM will be given the function calls and will take a decision if it should use them and give back which function to use and filled in input parameters.

This means that you can write to the LLM `What is 194*142?` and also give it the function call `calculator` that takes the string input `calculation` and instead of answering with a text message the LLM would answer with a structured input that would be something like `calculator(calculation: 194*142)` in pseudo code.

Now for certain tasks it's enough that we got a structured output here, but for an agent we need to execute it and be able to return the result. So we have the [ExecutableFunctionCallInterface](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/Service/FunctionCalling/ExecutableFunctionCallInterface.php?ref_type=heads) in Drupal AI.

This means that now we can have the AI taking decisions and triggering the tools.

## AI Workflows vs AI Agents?

There are two different tracks that you usually talk about when you are doing something more complex using LLMs.

One is the AI Workflows, that are mainly done via either [AI Automators](../modules/ai_automators/index.md) or [ECA](https://www.drupal.org/project/eca) in Drupal. These are where the workflow is controlled and defined by a human and the only choices the agents can take is what to do at the different workflow steps and when they happen.

**--If you are just running a tool over and over with a dynamic input, use AI Automators or ECA - you are wasting your money, reliability and time otherwise--**

## So what is an AI Agent?

The definition of an AI Agent is on the other hand quite elegant and simple per Anthropic's definition

> Agents [...] are systems where LLMs dynamically direct their own processes and tool usage, maintaining control over how they accomplish tasks.

Meaning, they are autonomous and take their own decisions. You can give them guidance, you can provide them with information they always should have available and you can direct certain steps, but an agent should always at least take one decision of how or when to use a tool to be considered an agent.

That's also why they are quite simple to set up, but quite hard to perfect. The same can be said about writing an agent framework.

In the end an agent is three things

* **A system prompt and instructions** - this is what tells the agent how to behave, how it should work, and what it should do. The system prompt is what you set as how the agent should always work, the instructions are given for a given problem.
* **Tools** - tools are not necessary for an agent, but whenever you want to load dynamic data or manipulate something that requires code, you need to assign tools to the agent. So the ability to run tools, is a requirement.
* **Memory** - short term memory is the required part of an agent, this is keeping track of past steps, so it can understand what it needs to do next and when it considers itself to be finished with a task.

These three components are the required parts of any agent framework - anything else is added for better outcomes, security, traceability, loggability etc.

### The loop

What is happening when you call an agent is the following:

1. The system asks an LLM - these are my system prompt, these are the instructions, these are the tools and this is the history - what action do you take.
2. The LLM looks at these four components and decides - should I use a tool or should I end by sending a text message.
3. If a tool is being used, it will answer with the tool and how it should be used and puts that choice in its memory.
4. The framework that is orchestrating the agents, executes the tool and puts the results in its memory.
5. The agent starts over on #1.
6. (unless) it deems itself to be finished, either by solving the issue or by not being able to solve the issue. The text is returned and the process is finished.

So it's just a looping process over and over that is happening.

### A concrete example.

We take this example:

* **System Prompt** - `You are an looping agent, that can look up thing on Wikipedia. You should use that tool until you have answered the users question, or answer that you can't find an answer`
* **Tools** - Wikipedia Search, takes input search words.
* **Instructions** - What is the difference between WordPress and Drupal?

The agent will in this case first run once and take the decision that it needs to run the Wikipedia Search twice, once with Drupal as search word and once with WordPress as search word.

This will execute and the result will be stored in the memory.

On the second loop it sees that it has this results and considers that it has enough information to answer, so it writes out a text message what the difference is.

That is an agent in its simplicity!
