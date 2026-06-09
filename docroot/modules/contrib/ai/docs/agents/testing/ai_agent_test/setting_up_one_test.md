# Setting up one test

Tests are located under `/admin/content/ai-agents-test` and can be reached under `content` in the admin menu.

In there you reach the form by clicking the `Add AI Agents Test` button.

## Test form

### Title
The title is used to identify the test and should be descriptive of what the test is checking and will be shown in the result list. So you should be able to understand what the test is about just by reading the title.

### Description
The description is used to give more context about the test and what it is checking and why it is important. This will be shown in the result list as well, so you can get a better understanding of the test without having to open it.

### Triggering Instructions
This is for meta data purposes only, to quickly see what the last triggering instructions were for the test. This is not used in the test itself, but can be useful to see what the last instructions were that triggered the test.

### Agent
This is the agent that the test will be run against. You can select any agent that is available in the system.

### History
The history checkbox, determines if the test should be run with a conversation history or not. The rule is that agents that are called by orchestration agents, usually do not need history, while agents or assistants that are called by end-users, usually do need history. This is because the orchestration agents usually have all the context they need to make a decision, while end-user agents usually need to remember the conversation to be able to answer correctly.

#### Instructions
If you do not check the history checkbox, you can add one single instruction that will be used to set the context for the test.

#### Chat History Role
If you do check the history checkbox, you can add as many messages as you want to the chat history. The role is used to determine if the message is from the user or the agent. This is important for the agent to understand who is speaking and what context it has - you can check AI logs to see how much context is given at any time from for instance a Chatbot, to replicate this.

#### Chat History Text
This is the actual text of the message that will be added to the chat history. You can add as many messages as you want, and they will be added in the order you add them. Do replicate the assistant's/agent's text correctly, so that it has the same context as it would have in a real conversation.

### Reset Config
If you for instance were to call an agent that adds a Content Type, and you want to run this test again, it would fail normally, since it exists the second time, unless you manually remove it. If you check the reset config checkbox, the test will automatically reset the configuration after running the test, so that it can be run again without any manual intervention.

Please note that this will only reset configuration, but not content. Also note that this is slow, so you should only set it if you really need it.

### Rules

Add as many rules as you want to the test. These are the criteria of if a test is successful or not. The rules are used to determine if the test passed or failed, and can be based on the output of the agent, the tools used, or any other relevant information.

#### Tool

First you have to select the tool that you want to do a check against. This is the tool that the agent should be USING or as importantly NOT USING in this test.

#### Label

This is the label for the specific test. Whenever you run a test and it fails, this label will be shown in the result list, so you can quickly see what test failed and why. It should be descriptive of what the test is checking.

#### Tool Rule

This determines if the tool should run or not. If you select "Should not be run" the rule is ready to be used. If you select "Should be run", you have to add a rule for parameters as well.

##### Tool Order

If you add a tool rule, you can also say when the tool should be run. The following options are available:

* Any Time - The tool can be run at any time during the test.
* One of the Loops - The tool should be run in selected loop(s) only. You give a comma separated list of loop numbers, where the first loop is 1.
* Sometime after the tool - The tool should be run after the tool you selected in the rule. This is useful if you want to check that a tool is run after another tool, for instance if you want to check that a tool is run after a content creation tool.
* Sometime before the tool - The tool should be run before the tool you selected in the rule. This is useful if you want to check that a tool is run before another tool, for instance if you want to check that a tool is run before a content creation tool.
* Directly after the tool - The tool should be run directly after the tool you selected in the rule. This is useful if you want to check that a tool is run directly after another tool, for instance if you want to check that a tool is run directly after a content creation tool.
* Directly before the tool - The tool should be run directly before the tool you selected in the rule. This is useful if you want to check that a tool is run directly before another tool, for instance if you want to check that a tool is run directly before a content creation tool.

##### Tool Rule Parameters
If you selected "Should be run" in the previous step, you can add parameters to the rule with tests.

###### Parameters Rule

The following rules are available for a parameter:

* No rules - This will not check the parameter at all.
* Is set - This will check if the parameter is set or not.
* Is not set - This will check if the parameter is not set or set to null.
* Is exactly - This will check if the parameter is exactly equal to the value you set.
* Is exactly not - This will check if the parameter is not equal to the value you set
* Is one of - This will check if the parameter is one of the values you set. You can add as many values as you want, separated by commas.
* Is not one of - This will check if the parameter is not one of the values you set. You can add as many values as you want, separated by commas.
* Is greater than - This will check if the parameter is greater than the value you set.
* Is less than - This will check if the parameter is less than the value you set.
* Is verified by an LLM - This will require you to fill in a prompt that will be used to verify the parameter. The prompt should be descriptive of what the parameter is checking and what the expected value is and when it should fail or not.

###### ParametersValue
This is the value that the rule will check against. Note that while an integer is saved as a string in the database, it will be converted to an integer when the rule is checked, so you can use correct types.

### Agent Response LLM Test
Here you can set a prompt that will be used to verify the agent's final response. This is useful if you want to check if the agent's response is correct or not, based on a specific prompt. For instance RAG test is hard to test if the right tools are used, but you can check that the agent does find what you are looking for in the response.
