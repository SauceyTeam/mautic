<?php

namespace Mautic\CoreBundle\Helper;

/**
 * Helper class for generating tenant configuration files
 */
class ConfigGeneratorHelper
{
    /**
     * Generate or update a tenant configuration file
     *
     * @param string $tenant The tenant identifier
     * @param string $host The host name
     * @param array $tenantRow Database row containing tenant information
     * @param string $projectRoot The project root directory
     * @return array Array with 'success' boolean and optional 'error' message
     */
    public static function generateTenantConfig($tenant, $host, $tenantRow, $projectRoot)
    {
        try {
            // Get main DB credentials from env vars
            $mainDbHost = getenv('MAUTIC_DB_HOST');
            $mainDbPort = getenv('MAUTIC_DB_PORT') ?: 3306;
            
            $configPath = '/var/www/html/config/local-' . $tenant . '.php';
            
            // Check if tenant config file exists
            if (file_exists($configPath)) {
                // Load existing config file
                ob_start();
                include $configPath;
                ob_end_clean();
                
                if (!isset($parameters) || !is_array($parameters)) {
                    return [
                        'success' => false,
                        'error' => 'Could not load existing config parameters array'
                    ];
                }
            } else {
                // Load the config template as a PHP file
                $templatePath = '/var/www/html/app/config/config_template.php';
                
                // Use output buffering to capture any output and prevent it from being sent
                ob_start();
                include $templatePath;
                ob_end_clean();
                
                // Now $parameters should be available as a real PHP array
                if (!isset($parameters) || !is_array($parameters)) {
                    return [
                        'success' => false,
                        'error' => 'Could not load config template parameters array'
                    ];
                }
                
                // Generate a new secret key only for new configs
                $parameters['secret_key'] = bin2hex(random_bytes(32));
            }
            
            // Update specific parameters
            $parameters['db_host'] = $mainDbHost;
            $parameters['db_port'] = $mainDbPort;
            $parameters['db_name'] = $tenantRow['db_name'];
            $parameters['db_user'] = $tenantRow['username'];
            $parameters['db_password'] = $tenantRow['password'];
            $parameters['site_url'] = 'http://' . $host;
            $parameters['mailer_from_name'] = $tenantRow['from_name'];
            $parameters['mailer_from_email'] = $tenantRow['from_email'];
            $parameters['mailer_reply_to_email'] = $tenantRow['reply_to_email'];
            $parameters['mailer_dsn'] = $tenantRow['mailer_dsn'];
            
            // Generate the new config content
            $configContent = "<?php\n\$parameters = array(\n";
            foreach ($parameters as $key => $value) {
                if ($value === null) {
                    $configContent .= "        '$key' => null,\n";
                } elseif (is_int($value)) {
                    $configContent .= "        '$key' => $value,\n";
                } elseif (is_array($value)) {
                    $configContent .= "        '$key' => array(\n\n        ),\n";
                } else {
                    $configContent .= "        '$key' => '$value',\n";
                }
            }
            $configContent .= ");";
            
            file_put_contents($configPath, $configContent);
            
            return [
                'success' => true,
                'config_path' => $configPath
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error generating config: ' . $e->getMessage()
            ];
        }
    }
} 