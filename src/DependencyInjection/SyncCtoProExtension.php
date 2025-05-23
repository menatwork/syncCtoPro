<?php

namespace MenAtWork\SyncCtoPro\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Class SyncCtoProExtension
 *
 * @package MenAtWork\SyncCtoPro\DependencyInjection
 */
class SyncCtoProExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'syncctopro-bundle';
    }

    /**
     * Loads a specific configuration.
     *
     * @throws InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // TODO: Implement load() method.
    }
}
