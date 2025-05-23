<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extra\TwigExtraBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Twig\Extra\TwigExtraBundle\Extensions;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigExtraExtension extends Extension
{
    /** @return void */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if ($container->getParameter('kernel.debug')) {
            $loader->load('suggestor.php');
        }

        foreach (array_keys(Extensions::getClasses()) as $extension) {
            if ($this->isConfigEnabled($container, $config[$extension])) {
                $loader->load($extension.'.php');
            }
        }
    }
}
