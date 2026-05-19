# Simple OAuth static scope

This module introduces a static scope provider. Gives the ability to defined static scopes via Plugins (YAML).

To enable the static scope provider, go to: `/admin/config/people/simple_oauth` and set the scope provider to static.

To define a static scope, add the following YAML file to the root of the associated module: `module_name.oauth2_scopes.yml`.

Static scope definition structure:
```
"scope:name":
  description: STRING (required)
  umbrella: BOOLEAN (required)
  grant_types: (required)
    GRANT_TYPE_PLUGIN_ID: (required: only known grant types)
      status: BOOLEAN (required)
      description: STRING
  parent: STRING
  granularity: STRING (required: if umbrella is FALSE, values: permission or role)
  permission: STRING (required: if umbrella is FALSE and granularity set to permission)
  role: STRING (required: if umbrella is FALSE and granularity set to role)
```

To view all defined static scopes via the UI go to: `/admin/config/people/simple_oauth/oauth2_scope/static`.
