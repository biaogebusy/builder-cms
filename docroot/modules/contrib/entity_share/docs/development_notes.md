Development Notes
-----------------

To regenerate the configuration used in automated tests.

```shell script
drush config:devel-export entity_share_test
# Depending of the development environment used and depending of the place of your cloned repository.
rsync -avzP --delete /project/app/modules/custom/entity_share/tests/modules/entity_share_test/config /project/contrib/entity_share/tests/modules/entity_share_test/
chmod 644 -R /project/contrib/entity_share/tests/modules/entity_share_test/config/install/*
```
