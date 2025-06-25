#!/usr/bin/env php
<?php

if (empty(ini_get('date.timezone'))) {
    date_default_timezone_set('UTC');
}

// if you don't want to setup permissions the proper way, just uncomment the following PHP line
// read http://symfony.com/doc/current/book/installation.html#configuration-and-setup for more information
// umask(0000);

if (function_exists('set_time_limit')) {
    set_time_limit(0);
}

defined('IN_MAUTIC_CONSOLE') or define('IN_MAUTIC_CONSOLE', 1);

define('MAUTIC_ROOT_DIR', realpath(__DIR__.'/..'));

require_once __DIR__.'/../autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\ErrorHandler\Debug;

$input = new ArgvInput();

if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
    putenv('APP_ENV='.$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env);
}

if ($input->hasParameterOption('--no-debug', true)) {
    putenv('APP_DEBUG='.$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0');
}

// Check if a specific tenant is provided
$tenant = $input->getParameterOption(['--tenant'], null, true);

// If no tenant is specified, get all tenants and run the command for each
if (null === $tenant) {
    // Get main DB credentials from env vars
    $mainDbHost = getenv('MAUTIC_DB_HOST');
    $mainDbPort = getenv('MAUTIC_DB_PORT') ?: 3306;
    $mainDbUser = getenv('MAUTIC_DB_USER');
    $mainDbPassword = getenv('MAUTIC_DB_PASSWORD');

    if ($mainDbHost && $mainDbUser && $mainDbPassword) {
        $dsn = "mysql:host=$mainDbHost;port=$mainDbPort;dbname=mautic_main;charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $mainDbUser, $mainDbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            $stmt = $pdo->prepare('SELECT * FROM tenants WHERE active = 1');
            $stmt->execute();
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($tenants)) {
                echo "No tenant specified. Running command for all " . count($tenants) . " tenants:\n";
                
                foreach ($tenants as $tenantData) {
                    // Extract last 10 characters of merchant_id as tenant ID
                    $tenantId = substr($tenantData['merchant_id'], -10);
                    echo "\n=== Running for tenant: " . $tenantData['url'] . " (ID: " . $tenantId . ") ===\n";
                    
                    // Set tenant environment variable
                    putenv('TENANT='.$_SERVER['TENANT'] = $_ENV['TENANT'] = $tenantId);
                    
                    // Run the command for this tenant
                    $command = implode(' ', array_slice($argv, 1)); // Remove the script name
                    $output = [];
                    $returnCode = 0;
                    
                    exec("php " . __FILE__ . " --tenant=" . $tenantId . " " . $command, $output, $returnCode);
                    
                    // Display output
                    foreach ($output as $line) {
                        echo $line . "\n";
                    }
                    
                    if ($returnCode !== 0) {
                        echo "Command failed for tenant " . $tenantData['url'] . " (ID: " . $tenantId . ") with return code: " . $returnCode . "\n";
                    }
                }
                
                echo "\n=== Completed running command for all tenants ===\n";
                exit(0);
            } else {
                echo "No active tenants found in database.\n";
                exit(1);
            }
        } catch (Exception $e) {
            echo "Error connecting to main database: " . $e->getMessage() . "\n";
            echo "Please ensure MAUTIC_DB_HOST, MAUTIC_DB_USER, and MAUTIC_DB_PASSWORD environment variables are set.\n";
            exit(1);
        }
    } else {
        echo "No tenant specified and main database credentials not available.\n";
        echo "Please either specify a tenant with --tenant or set MAUTIC_DB_HOST, MAUTIC_DB_USER, and MAUTIC_DB_PASSWORD environment variables.\n";
        exit(1);
    }
} else {
    // Specific tenant provided, set environment variable
    putenv('TENANT='.$_SERVER['TENANT'] = $_ENV['TENANT'] = $tenant);
}

require dirname(__DIR__).'/app/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    if (class_exists(Debug::class)) {
        Debug::enable();
    }
}

$kernel      = new AppKernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$application = new Application($kernel);
$application->setName('Mautic');
$application->setVersion($kernel->getVersion().' - app/'.$kernel->getEnvironment().($kernel->isDebug() ? '/debug' : ''));

// Add global tenant option to all commands
$application->getDefinition()->addOption(
    new InputOption('--tenant', null, InputOption::VALUE_REQUIRED, 'Specify the tenant to use for this command')
);

return $application;
