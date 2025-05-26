<?php

namespace MenAtWork\SyncCtoPro;

use MenAtWork\SyncCtoPro\DependencyInjection\SyncCtoProExtension;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class SyncCtoProBundle
 *
 * @package MenAtWork\SyncCtoPro
 */
class SyncCtoProBundle extends Bundle
{
    const SCOPE_BACKEND  = 'backend';
    const SCOPE_FRONTEND = 'frontend';

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
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
