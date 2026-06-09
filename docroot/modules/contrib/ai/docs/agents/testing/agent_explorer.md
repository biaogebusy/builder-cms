# Agent Explorer

The Agent Explorer is a tool designed to run an agent over and over and see what paths it takes in its decision-making process. This is particularly useful for testing and debugging agents, as it allows developers to visualize the agent's behavior in various scenarios.

## Getting Started
To use the Agent Explorer, follow these steps:

1. **Install** - Go to extend and install the Agent Explorer module.
2. **List** - Navigate to the agent list page to see all available agents.
3. **Test** - The agent should now have a `Explore button`, where you can run the agent in the explorer.

## How to run a test

1. Choose an agent from the list.
2. Write a prompt and add files that you might want to use in the test.
3. Choose the model you want to use for the test.
4. Click the `Run Agent` button to start the test.

## Reading the results
On the right side of the screen, you will see the results of the agent's execution. The results are displayed in a table format for each loop iteration. Each row represents a step in the agent's decision-making process, showing the input, output, tools used, and any additional information relevant to that step.

You can click on each row to open it in a new tab and see more details about the agent's actions, including the raw provider data and the tokenized system prompt.
