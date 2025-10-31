<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Installer script for com_nxpeasycart.
 */
class Com_NxpeasycartInstallerScript
{
    /**
     * Run on install.
     */
    public function install($parent): void
    {
        $this->installSchema();
    }

    /**
     * Run on update.
     */
    public function update($parent): void
    {
        $this->installSchema();
    }

    /**
     * Run on discover install.
     */
    public function discover_install($parent): void
    {
        $this->installSchema();
    }

    /**
     * Apply the base schema if tables are missing.
     */
    private function installSchema(): void
    {
        $path = __DIR__ . '/sql/install.mysql.utf8.sql';

        if (!is_file($path)) {
            return;
        }

        $container = Factory::getContainer();
        $db = $container->get(DatabaseInterface::class);

        $queries = Installer::splitSql(file_get_contents($path));

        foreach ($queries as $query) {
            $query = trim($query);

            if ($query === '') {
                continue;
            }

            try {
                $db->setQuery($query)->execute();
            } catch (\Throwable $exception) {
                $message = $exception->getMessage();

                // Ignore "already exists" warnings for idempotency.
                if (stripos($message, 'already exists') !== false) {
                    continue;
                }

                Log::add('com_nxpeasycart install SQL error: ' . $message, Log::ERROR, 'com_nxpeasycart');

                throw $exception;
            }
        }
    }
}
