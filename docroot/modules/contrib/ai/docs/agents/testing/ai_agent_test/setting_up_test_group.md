# Setting up a Test Group

When you have collected enough tests, to be a logical mass, you can set up a Test Group, which is a collection of tests that can be run together. This is useful for testing an agent's performance across multiple scenarios and ensuring it meets a certain success threshold over different providers and models.

You start by going to the Test Groups page (`/admin/content/ai-agents-test/group`), and clicking on the "Add AI Agents Test Group" button.

## Test Group Form

### Label
The label is used to identify the test group and should be descriptive of what the group is testing. It will be shown in the result list, so you should be able to understand what the group is about just by reading the label.

### Description
The description is used to give more context about the test group and what it is testing. This will be shown in the result list as well, so you can get a better understanding of the group without having to open it.

### Tests
Here you can use autocomplete to select the tests that you want to include in the group. You can select as many tests as you want, and they will be run together when you run the group.

### Approval Percentage
This is the percentage of tests that should pass for the group to be considered successful. For example, if you have 10 tests and set the approval percentage to 80%, at least 8 tests must pass for the group to be considered successful. This allows you to set a threshold for how many tests should pass, which is useful since agents are rarely 100% accurate.

### Reset Configuration
If you check this box, the configuration will be reset after running the group. This is useful if you want to run the group multiple times without having to manually reset the configuration each time.
