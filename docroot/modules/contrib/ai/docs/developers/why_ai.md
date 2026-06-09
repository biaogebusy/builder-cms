# Why should I require/integrate the AI module instead of my own solution?

Here are some reasons why you should use our way to integrate your API call via the AI module instead of doing your own solution:

- The AI core module makes sure that the user only needs to input one Authentication/API key per service in a known and secure pattern.
- The AI core module enables you to hotswap one service to another. So if you realize that you want to move from OpenAI TTS to ElevenLabs for speech, it's one line of code change.
- The AI core module makes sure that you do not need to understand any specific API of any AI integrator. You just need to understand the AI modules basic interfaces.
- The AI core module makes sure that you can use the end-users default API service at any time without having to provide configuration forms.
- But at the same time it also enables you to have a flexible form where the end-user can switch services with the click of a button if you want it.
- The AI core module takes care of exposing events and exceptions for you, meaning that your module will work with any type of integrator like prompt security modules, cost analysis modules etc.
- The AI core module takes care of logging any or every prompt or API call for the user of your module if they choose to.
- The AI core module also comes with API explorers, so you and the end-user can replay a prompt/call for debugging or prompt engineering.
- The AI core is opinionated and makes sure that your implementation uses best practices for security like pre-moderation requests, so the end-users API access is not revoked. You can still opt-out from this for your specific implementation.
- Even in the edge cases where your AI service wants to do something that the abstraction layer does not support, you can use it for authentication and get the raw client or even extend the providers to do what you want.
