<?php

/**
 * @file
 * Hooks provided by the State Machine module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter workflows.
 *
 * @param array $workflows
 *   Workflow definitions, keyed by plugin ID.
 */
function hook_workflows_alter(array &$workflows) {
  $workflows['default']['label'] = 'Altered label';
}

/**
 * Alter workflow groups.
 *
 * @param array $workflow_groups
 *   Workflow group definitions, keyed by plugin ID.
 */
function hook_workflow_groups_alter(array &$workflow_groups) {
  $workflow_groups['default']['label'] = 'Altered label';
}

/**
 * @} End of "addtogroup hooks".
 */
