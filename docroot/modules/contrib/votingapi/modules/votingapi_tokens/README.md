#VotinAPI tokens

This module enables tokens for all entity types that has voting_api_field.

Tokens are dynamic and you have to pass _vote_type_ as the last parameter.
Module defines four tokens:
 - Vote count :: `[votingapi_<entity_type>_token:vote_count:<vote_type>]`
 - Vote average :: `[votingapi_<entity_type>_token:vote_average:<vote_type>]`
 - Best vote :: `[votingapi_<entity_type>_token:best_vote:<vote_type>]`
 - Worst vote :: `[votingapi_<entity_type>_token:worst_vote:<vote_type>]`
