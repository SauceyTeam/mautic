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
            
            // Validate and sanitize input values
            $validatedDbHost = self::validateDbHost($mainDbHost);
            $validatedDbPort = self::validateDbPort($mainDbPort);
            $validatedDbName = self::validateDbName($tenantRow['db_name']);
            $validatedDbUser = self::validateDbUser($tenantRow['username']);
            $validatedDbPassword = self::validateDbPassword($tenantRow['password']);
            $validatedHost = self::validateHost($host);
            $validatedFromName = self::validateString($tenantRow['from_name']);
            $validatedFromEmail = self::validateEmail($tenantRow['from_email']);
            $validatedReplyToEmail = self::validateEmail($tenantRow['reply_to_email']);
            $validatedMailerDsn = self::validateMailerDsn($tenantRow['mailer_dsn']);
            
            // Update specific parameters with validated values
            $parameters['db_host'] = $validatedDbHost;
            $parameters['db_port'] = $validatedDbPort;
            $parameters['db_name'] = $validatedDbName;
            $parameters['db_user'] = $validatedDbUser;
            $parameters['db_password'] = $validatedDbPassword;
            $parameters['site_url'] = 'http://' . $validatedHost;
            $parameters['mailer_from_name'] = $validatedFromName;
            $parameters['mailer_from_email'] = $validatedFromEmail;
            $parameters['mailer_reply_to_email'] = $validatedReplyToEmail;
            $parameters['mailer_dsn'] = $validatedMailerDsn;
            
            // Generate the new config content
            $configContent = "<?php\n\$parameters = array(\n";
            foreach ($parameters as $key => $value) {
                $configContent .= self::formatParameter($key, $value, 2);
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
    
    /**
     * Recursively format a parameter for PHP array output
     *
     * @param string $key The parameter key
     * @param mixed $value The parameter value
     * @param int $indentLevel The indentation level
     * @return string Formatted PHP code
     */
    private static function formatParameter($key, $value, $indentLevel = 0)
    {
        $indent = str_repeat('        ', $indentLevel);
        $nextIndent = str_repeat('        ', $indentLevel + 1);
        
        if ($value === null) {
            return $indent . "'$key' => null,\n";
        } elseif (is_int($value)) {
            return $indent . "'$key' => $value,\n";
        } elseif (is_bool($value)) {
            return $indent . "'$key' => " . ($value ? 'true' : 'false') . ",\n";
        } elseif (is_array($value)) {
            $output = $indent . "'$key' => array(\n";
            
            foreach ($value as $arrayKey => $arrayValue) {
                if (is_array($arrayValue)) {
                    // Recursive call for nested arrays
                    $output .= self::formatParameter($arrayKey, $arrayValue, $indentLevel + 1);
                } else {
                    $output .= $nextIndent . "'$arrayKey' => " . self::formatValue($arrayValue) . ",\n";
                }
            }
            
            $output .= $indent . "),\n";
            return $output;
        } else {
            // String or other types
            return $indent . "'$key' => " . self::formatValue($value) . ",\n";
        }
    }
    
    /**
     * Format a single value for PHP output
     *
     * @param mixed $value The value to format
     * @return string Formatted value
     */
    private static function formatValue($value)
    {
        if ($value === null) {
            return 'null';
        } elseif (is_int($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            // Properly escape string values
            $escapedValue = str_replace("'", "\\'", $value);
            return "'$escapedValue'";
        } else {
            // Fallback for other types
            $escapedValue = str_replace("'", "\\'", (string)$value);
            return "'$escapedValue'";
        }
    }
    
    /**
     * Validate database host
     */
    private static function validateDbHost($host)
    {
        if (!is_string($host) || strlen($host) > 255) {
            throw new \InvalidArgumentException('Invalid database host');
        }
        
        // Only allow alphanumeric, dots, hyphens, and colons (for IPv6)
        if (!preg_match('/^[a-zA-Z0-9.\-:]+$/', $host)) {
            throw new \InvalidArgumentException('Invalid database host format');
        }
        
        return $host;
    }
    
    /**
     * Validate database port
     */
    private static function validateDbPort($port)
    {
        $port = (int)$port;
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Invalid database port');
        }
        return $port;
    }
    
    /**
     * Validate database name
     */
    private static function validateDbName($dbName)
    {
        if (!is_string($dbName) || strlen($dbName) > 64) {
            throw new \InvalidArgumentException('Invalid database name');
        }
        
        // Only allow alphanumeric and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException('Invalid database name format');
        }
        
        return $dbName;
    }
    
    /**
     * Validate database user
     */
    private static function validateDbUser($user)
    {
        if (!is_string($user) || strlen($user) > 32) {
            throw new \InvalidArgumentException('Invalid database user');
        }
        
        // Only allow alphanumeric and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $user)) {
            throw new \InvalidArgumentException('Invalid database user format');
        }
        
        return $user;
    }
    
    /**
     * Validate database password
     */
    private static function validateDbPassword($password)
    {
        if (!is_string($password) || strlen($password) > 255) {
            throw new \InvalidArgumentException('Invalid database password');
        }
        
        // Allow any printable characters for passwords
        if (!preg_match('/^[\x20-\x7E]+$/', $password)) {
            throw new \InvalidArgumentException('Invalid database password format');
        }
        
        return $password;
    }
    
    /**
     * Validate host
     */
    private static function validateHost($host)
    {
        if (!is_string($host) || strlen($host) > 255) {
            throw new \InvalidArgumentException('Invalid host');
        }
        
        // Only allow alphanumeric, dots, and hyphens
        if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
            throw new \InvalidArgumentException('Invalid host format');
        }
        
        return $host;
    }
    
    /**
     * Validate string value
     */
    private static function validateString($value)
    {
        if (!is_string($value) || strlen($value) > 255) {
            throw new \InvalidArgumentException('Invalid string value');
        }
        
        // Allow alphanumeric, spaces, and common punctuation
        if (!preg_match('/^[a-zA-Z0-9\s\-_.,!?@#$%&*()+=:;"\'<>\/\\|]+$/', $value)) {
            throw new \InvalidArgumentException('Invalid string format');
        }
        
        return $value;
    }
    
    /**
     * Validate email
     */
    private static function validateEmail($email)
    {
        if (!is_string($email) || strlen($email) > 255) {
            throw new \InvalidArgumentException('Invalid email');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        
        return $email;
    }
    
    /**
     * Validate mailer DSN
     */
    private static function validateMailerDsn($dsn)
    {
        if (!is_string($dsn) || strlen($dsn) > 500) {
            throw new \InvalidArgumentException('Invalid mailer DSN');
        }
        
        // Basic DSN format validation
        if (!preg_match('/^[a-zA-Z]+:\/\/[^<>"\']+$/', $dsn)) {
            throw new \InvalidArgumentException('Invalid mailer DSN format');
        }
        
        return $dsn;
    }
} 