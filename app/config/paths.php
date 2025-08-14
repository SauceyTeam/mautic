<?php


$root        = $root ?? realpath(__DIR__.'/..');
$projectRoot = $projectRoot ?? Mautic\CoreBundle\Loader\ParameterLoader::getProjectDirByRoot($root);

$host = null;
$tenant = null;

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    // Get tenant from environment variable set in console-application.php
    $tenant = $_ENV['TENANT'] ?? $_SERVER['TENANT'] ?? getenv('TENANT');
} else {
    // Web request - extract from HTTP_HOST
    $host = $_SERVER["HTTP_HOST"];
    if (strpos($host, ':') !== false) {
        $host = substr($host, 0, strpos($host, ':'));
    }
    $tenant = preg_match('/^([a-zA-Z0-9]+)\./', $host, $matches) ? $matches[1] : null;
}

$file = 'local.php';

if($tenant) {
    if (file_exists($projectRoot.'/config/local-'.$tenant.'.php')) {
        $file = 'local-'.$tenant.'.php';
    } else {
        // Get main DB credentials from env vars
        $mainDbHost = getenv('MAUTIC_DB_HOST');
        $mainDbPort = getenv('MAUTIC_DB_PORT') ?: 3306;
        $mainDbUser = getenv('MAUTIC_DB_USER');
        $mainDbPassword = getenv('MAUTIC_DB_PASSWORD');

        $dsn = "mysql:host=$mainDbHost;port=$mainDbPort;dbname=mautic_main;charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $mainDbUser, $mainDbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $pdo->prepare('SELECT * FROM tenants WHERE url = ? OR tenant_id = ? LIMIT 1');
            $stmt->execute([$host,$tenant]);
            $tenantRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenantRow) {                
                // Use the shared config generator helper
                require_once __DIR__.'/../bundles/CoreBundle/Helper/ConfigGeneratorHelper.php';
                $result = \Mautic\CoreBundle\Helper\ConfigGeneratorHelper::generateTenantConfig($tenant, $tenantRow['url'], $tenantRow, $projectRoot);
                
                if ($result['success']) {
                    $file = 'local-'.$tenant.'.php';
                } else {
                    error_log('Could not generate config: ' . $result['error']);
                }

            } else {
                error_log('No tenant found for host: ' . $host);
            }
        } catch (Exception $e) {
            error_log('Error generating tenant config: ' . $e->getMessage());
        }
    }
}


$paths = [
    // customizable
    'themes'       => 'themes',
    'assets'       => 'app/assets',
    'media'        => 'media',
    'asset_prefix' => '',
    'plugins'      => 'plugins',
    'translations' => 'translations',
    'local_config' => '%kernel.project_dir%/config/'.$file,
];



// allow easy overrides of the above
if (file_exists($projectRoot.'/config/paths_local.php')) {
    include $projectRoot.'/config/paths_local.php';
} elseif (file_exists($root.'/config/paths_local.php')) {
    include $root.'/config/paths_local.php';
}

// fixed
$paths = array_merge($paths, [
    // remove /app from the root
    'root'    => substr($root, 0, -4),
    'app'     => 'app',
    'bundles' => 'app/bundles',
    'vendor'  => 'vendor',
]);
