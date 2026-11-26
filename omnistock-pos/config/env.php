<?php
declare(strict_types=1);

/**
 * Load .env file configuration
 * Parse and load environment variables from .env file
 */
function load_env_file(string $path = __DIR__ . '/../.env'): void
{
    if (!file_exists($path)) {
        return;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return;
    }

    $lines = explode("\n", $contents);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') === false) {
            continue;
        }
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Skip if key is empty
        if (empty($key)) {
            continue;
        }
        
        // Remove quotes if present
        if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        } elseif (($value[0] ?? '') === "'" && substr($value, -1) === "'") {
            $value = substr($value, 1, -1);
        }
        
        // Set environment variable only if not already set
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

// Auto-load .env file
load_env_file();

function omni_is_production(): bool
{
    $appEnvironment = strtolower(trim((string) (getenv('APP_ENV') ?: 'production')));

    return in_array($appEnvironment, ['prod', 'production'], true);
}

function omni_public_demo_enabled(): bool
{
    return !omni_is_production() && getenv('OMNISTOCK_PUBLIC_DEMO_ENABLED') === '1';
}
