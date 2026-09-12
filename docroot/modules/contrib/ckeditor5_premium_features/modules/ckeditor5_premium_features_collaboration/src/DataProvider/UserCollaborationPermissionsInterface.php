<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\DataProvider;

/**
 * Interface describing a collaboration permissions for the ckeditor5.
 */
interface UserCollaborationPermissionsInterface {

  /**
   * Creates the data provider instance from the given entities.
   *
   * @param array|\Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface[] $entities
   *   The entities related to the user.
   */
  public function getFromEntities(array $entities): array;

}
