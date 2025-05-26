<?php

namespace MenAtWork\SyncCtoPro;

use MenAtWork\SyncCtoPro\DependencyInjection\SyncCtoProExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class SyncCtoProBundle
 *
 * @package MenAtWork\SyncCtoPro
 */
class SyncCtoProBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new SyncCtoProExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        // disable automatic command registration
    }
}
