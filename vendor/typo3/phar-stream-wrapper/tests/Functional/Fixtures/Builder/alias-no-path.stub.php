<?php
/**
 * basically similar to https://github.com/aws/aws-sdk-php/releases
 */

\Phar::mapPhar('alias.no.path.phar');
// using internal alias name in order to require file
require('phar://alias.no.path.phar/Classes/Domain/Model/DemoModel.php');
// <c3d4371ab0014b4e777cd450347bd20182a1dae3>
__HALT_COMPILER();