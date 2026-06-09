# Organizing AI Modules & Features

The Drupal AI ecosystem uses a three-tier hierarchy to organize modules, submodules, and features. This structure helps users understand what each component does and quickly locate what they need.

## Top-Level Three-Tier Hierarchy

**Infrastructure** → **Tools & Automation** → **Features** 

Each tier builds on the previous: Infrastructure enables Tools to execute, and Tools power the Features users interact with.

### Subcategories 

Infrastructure and Features use subcategories to group related components. Tools & Automation is currently a single list but may add subcategories as the ecosystem grows.

- **Infrastructure**
    - AI Infrastructure
- **Tools & Automation**
- **Features**
    - Content Creation & Support
    - People, Accounts & Users
    - Safety & Compliance
    - Search & Discovery
    - Site Building & Design


## Infrastructure

Backend configuration and foundational services that other AI components depend on.

Components at this level must be configured before AI features can function. Section names should include "Infrastructure" for clarity.

**Examples:**

- **AI Infrastructure**
    - AI Provider Connections (API keys, authentication)
    - Default Model Configuration
    - Embedding Services
    - Prompt Template Library
    - Vector Database Connections

## Tools & Automation

Operational tools that execute tasks and automate workflows in the background.

These are the "doers" - not pure configuration, not end-user interfaces, but the working layer in between.

**Examples:**

- **Tools & Automation**
    - AI Agents (execute Drupal operations)  
    - AI Assistants (conversational logic and configuration)  
    - AI Automation Workflows (chained operations)  
    - AI Moderation Guardrails (automatic safety filtering)

**Key distinction:** Configuration for these tools lives here; the user interfaces appear in Features.

## Features

End-user facing capabilities and site enhancements. If users directly interact with it, it belongs here.

Feature names describe their function - avoid "Infrastructure" or "Tools" in these labels.

**Feature categories:**

- **Content Creation & Support** - Generation, review, SEO tools, translation, personalization  
- **People, Accounts & Users** - Chat widgets, user profiling, account assistance  
- **Safety & Compliance** - Review dashboards, observability, spam detection, privacy controls  
- **Search & Discovery** - Search interfaces, recommendations, filters  
- **Site Building & Design** - Page builders, theme assistants, layout tools

## Ordering Rules

1. **Top-level sections:** Infrastructure → Tools & Automation → Features  
2. **Within sections:** Within each tier and subcategory, items are listed alphabetically

## Quick Decision Framework

When deciding where a component belongs:

| Question | Answer | Place it in... |
| :---- | :---- | :---- |
| Is it necessary to set up before AI can work? | Yes | Infrastructure |
| Does it operate automatically in the background? | Yes | Tools & Automation |
| Do users interact with it directly? | Yes | Features |

## When It's Not Clear

Some components span multiple tiers. When this happens, apply the **80/20 rule**: categorize based on where the majority of usage occurs.

**Example:** The Context Control Center has an interface where users upload context documents, but 80% of its activity happens in the background. Agents automatically attach context information during agent operations. The occasional configuration updates don't make it a Feature, so we categorize it in Tools & Automation.

**When in doubt:** Ask "Where does most of the work happen?" and place the component there.

## Adding Contrib Module Settings to the Admin Menu

The AI module provides a grouped admin configuration page at **Administration → Configuration → AI** that mirrors the three-tier hierarchy above. Contrib modules should register their settings pages under the appropriate category so they appear in the correct grouping.

### Available Parent Menu Categories

Each category corresponds to a parent menu link that contrib modules can reference:

| Category | Parent Value | Description |
| :---- | :---- | :---- |
| AI Infrastructure | `ai.admin_config_infrastructure` | AI providers, models, and core infrastructure |
| Tools & Automation | `ai.admin_config_tools` | AI tools, agents, and automation workflows |
| Content Creation & Support | `ai.admin_config_content` | Prompts, content generation, and support features |
| People, Accounts & Users | `ai.admin_config_people` | User accounts and personalization |
| Safety & Compliance | `ai.admin_config_safety` | Moderation, guardrails, and compliance settings |
| Search & Discovery | `ai.admin_config_search` | AI-powered search and content discovery |
| Site Building & Design | `ai.admin_config_site_building` | Site building, theming, and design |

### How to Add a Menu Link

In your module's `*.links.menu.yml` file, add an entry with `parent` set to the appropriate category. For example, the AI Translate module places its settings under **Content Creation & Support**:

```yaml
# ai_translate.links.menu.yml
ai_translate.settings:
  title: 'AI Translate'
  description: 'Translate content between languages using AI-powered translation services.'
  parent: ai.admin_config_content
  route_name: ai_translate.settings_form
```

Use the [Quick Decision Framework](#quick-decision-framework) above to determine which category your module belongs in, then set the `parent` value accordingly.