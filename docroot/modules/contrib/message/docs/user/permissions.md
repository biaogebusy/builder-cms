# Permissions

Message defines the following permissions:

| Permission | What it allows |
|------------|----------------|
| **Administer message templates** | Create, edit, and delete message templates; manage related Field UI screens when available |
| **Administer messages** | Administer message entities through the backend |
| **Overview messages** | View the messages overview (for example `/admin/content/message`) |

Assign these under **People → Permissions**.

Typical setups:

- Give site builders **Administer message templates**
- Give content administrators **Overview messages** and, if needed,
  **Administer messages**
- Grant anonymous or authenticated roles only the access required by your custom
  Views or entity access rules—Message does not grant public listing access by
  default beyond what you configure
