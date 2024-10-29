<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Autoloader.
 *
 * @class 		WC_Autoloader
 *
 * @version		2.3.0
 *
 * @category	Class
 */
class Admitad_Autoloader
{
    /**
     * Path to the includes directory.
     */
    private string $include_path = '';

    /**
     * The Constructor.
     */
    public function __construct()
    {
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }

        spl_autoload_register([$this, 'autoload']);

        $this->include_path = untrailingslashit(ADMITAD_TRACKING_PLUGIN_PATH) . '/src';
    }

    /**
     * Auto-load WC classes on demand to reduce memory consumption.
     *
     * @param string $class
     */
    public function autoload($class)
    {
        if (!preg_match('@^(Admitad|Buzz)@', $class)) {
            return;
        }

        $path = preg_replace('@\\\\@', DIRECTORY_SEPARATOR, $class);
        $includePath = $this->include_path . DIRECTORY_SEPARATOR . $path . '.php';
        $this->load_file($includePath);
    }

    /**
     * Take a class name and turn it into a file name.
     *
     * @param string $class
     *
     * @return string
     */
    private function get_file_name_from_class($class)
    {
        return 'class-' . str_replace('_', '-', $class) . '.php';
    }

    /**
     * Include a class file.
     *
     * @param string $path
     *
     * @return bool successful or not
     */
    private function load_file($path)
    {
        if ($path && is_readable($path)) {
            include_once $path;

            return true;
        }

        return false;
    }
}

new Admitad_Autoloader();
