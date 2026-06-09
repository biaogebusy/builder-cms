# Using Drush to Run Tests

## Running Tests with Drush
Currently you can run single tests using Drush. Test groups are coming up.

The command is `drush agents:test-agents` with the following parameters:

-- `test_id`: The ID of the test you want to run. You can find this in the test listing page.
-- `uid`: The user ID of the user who is running the test, since Drush does not have a user context.
-- `provider`: The provider you want to use for the test. This is optional and will default to the default provider
-- `model`: The model you want to use for the test. This is also optional and will default to the default model.
-- `detailed`: Set this to `true` if you want to see detailed results of the test run. This will include the input, output, tools used, and any additional information relevant to that test run.

## Import a Test Group

The command is `drush agents:test-agents-group-import` with the following parameters:

You have to give the file url as the first parameter of the YAML file you want to import.
