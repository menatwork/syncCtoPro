<?php

namespace MenAtWork\SyncCtoPro\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Class SyncCtoProExtension
 *
 * @package MenAtWork\SyncCtoPro\DependencyInjection
 */
class SyncCtoProExtension extends Extension
{
    /**
     * The config files.
     *
     * @var array
     */
    private $files = [
//        'listener.yml',
//        'services.yml',
    ];

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'syncctopro-bundle';
    }

    /**
     * Loads a specific configuration.
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // TODO: Implement load() method.
    }
}
