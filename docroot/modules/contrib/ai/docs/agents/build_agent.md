# Build an agent

So now that we have some base knowledge on how agents work, let's build our first agent using the Drupal agents framework. There will most likely be more contributed agent builders - one already exists in the [Modeler API](https://www.drupal.org/project/modeler_api). But this will assume the base form, all the solutions on top of that will just make it simpler.

We will build the simplest agent in this case, it will have the possibility to answer questions about the Media types that are installed on the system and what kind of fields they have.

## Build the Media agent

1. The first thing we need to install is the Media module, the AI, AI Agents, AI API Explorer and AI Agents Explorer module and a provider module that supports tools calling.
2. Then you need to setup that provider you are using.
3. Visit /admin/config/ai/agents, you should see some agents with type config as part of the list.
4. Click on `Add AI Agent`
5. You need to give your agent a Label and a Description. While people are pretty lax with descriptions normally in Drupal, in this case it's of uttermost importance. If you later will attach your Media Type agent to your orchestration agent, this is the main information it takes its decisions on. So be verbose.
6. Max loops is there to prevent recursion or the agent getting stuck in a loop where it can't solve a problem. In our case the agent should have answered any question within 3 loops, so we can keep the default value.
7. Agent instructions is where you write down how the agent should handle different scenarios - try to be verbose and instructive here as well. Start by writing something simple like - `You are a looping agent for Drupal 11, that can answer questions about media types and what kind of fields these media types has.`
8. Default Information Tools you can leave empty for now - it will be covered in advanced usage.
9. Then you need to find the tools you should use - we need two tools in this case:
10. `List Bundles` - this is to be able to retrieve the list of the bundles you have, in this case media types.
11. `Get Entity Field Information` - this is to get some base information about all fields on a media type.
12. For both of these tools, try to press `Test this tool` to get some feeling of what kind of results are returned there.
13. Now comes a really important part - if you scroll down there should be `Detailed tool usage`. Open up this for both tools and open `Property Restrictions`.
14. Under the restrictions on both, select the dropdown for the `entity_type` property and set that to `Force Value` and in the new field write `media`. What this does is to make sure that the media entity type is the only entity type that you can run this tool against.
15. Click `Save`
16. We have now built an agent - you will be back at the list, click `Explore` on the newly created agent.
17. In prompt try running `What media types exist on this system?` or `What fields does the audio field have that starts with a?`. You will see how the agent takes decisions on what tools to load and gives an answer.
18. Add or remove a media type or add fields to a media type and ask again and see how the answers are aware of the changes.
19. Start playing with the Agent instructions on #7, to get an idea of how this actually changes how the agent reasons.
20. If you are running Drupal CMS, you can now go to the assistant and add this agent, and you should be able to ask the chatbot about media types.

## A word of warning
While building agents is super simple and getting simple agents up and running can be done in under a minute, building agents that are highly probabilistic to be able to solve any issues it's designed for in a secure manner is a lot harder. Think of all the combinations you actually can ask the agent and think about how it is not always obvious to us humans to answer that - try asking the agent above `Can I create images?` and see what it answers. What would you answer yourself in context?


