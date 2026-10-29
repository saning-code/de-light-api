<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Extends the default PostgresConnector to support Neon's endpoint SNI requirement.
 * Neon requires the endpoint ID in the DSN `options` parameter when using older
 * libpq versions that don't support SNI via the hostname alone.
 *
 * Config key: `dsn_options` — automatically derived from DB_HOST if not explicitly set.
 */
class NeonPostgresConnector extends PostgresConnector
{
    protected function addSslOptions($dsn, array $config)
    {
        foreach (['sslmode', 'sslcert', 'sslkey', 'sslrootcert'] as $option) {
            if (isset($config[$option])) {
                $dsn .= ";{$option}={$config[$option]}";
            }
        }

        if (!empty($config['dsn_options'])) {
            $dsn .= ";options={$config['dsn_options']}";
        }

        return $dsn;
    }
}
