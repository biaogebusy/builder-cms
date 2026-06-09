# View old results, export, and import tests

## View old results
You can go to the test results page, where you can filter or search for specific tests or test groups. This page will show you all the tests that have been run, along with their results.

One thing that is coming up, but is good to know, is that the full configuration of the agent is saved with every test result, meaning that if you see negative changes over time in the results, you can go back to the configuration of the agent at that time and see what has changed. This is useful for debugging and understanding how changes in the agent's configuration affect its performance.

## Export Tests/Test Groups
You can export single tests or test groups to a YAML file. This is useful if you want to share your tests with others or if you want to keep a backup of your tests. The exported file will contain all the information about the test, including the configuration, rules, and results.

Note that anyone importing the test will need the same tools and agents installed as the test uses, otherwise it will not be able to run the test.

## Import Tests/Test Groups
You can import tests or test groups from a YAML file. This is useful if you want to reuse tests that someone else has created or if you want to restore tests from a backup. The imported tests will be added to your list of tests, and you can run them just like any other test.
