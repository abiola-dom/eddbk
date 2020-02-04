<?php

/*
 * @wordpress-plugin
 *
 * Plugin Name: Bookings for Easy Digital Downloads
 * Plugin URI: https://eddbookings.com
 * Description: Adds a customizable booking system to Easy Digital Downloads.
 * Version: 0.4
 * Author: RebelCode
 * Author URI: https://rebelcode.com
 * Text Domain: eddbk
 * Domain Path: /languages/
 * License: GPLv3
 */

/*
 * Copyright (C) 2015-2020 RebelCode Ltd.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use Dhii\Collection\CountableMap;
use Dhii\Config\DereferencingConfigMapFactory;
use Dhii\EventManager\WordPress\WpEventManager;
use Dhii\Exception\InternalException;
use Dhii\Modular\Module\ModuleInterface;
use Dhii\Util\String\StringableInterface as Stringable;
use RebelCode\EddBookings\Core\Di\CompositeContainerFactory;
use RebelCode\EddBookings\Core\Di\ContainerFactory;
use RebelCode\EddBookings\Core\ExceptionHandler;
use RebelCode\EddBookings\Core\PluginModule;
use RebelCode\Modular\Events\EventFactory;

// Plugin info
define('EDDBK_SLUG', 'eddbk');
define('EDDBK_MIN_PHP_VERSION', '5.5.0');
define('EDDBK_MIN_WP_VERSION', '4.4');
define('EDDBK_MIN_EDD_VERSION', '2.6.0');
// Paths
define('EDDBK_FILE', __FILE__);
define('EDDBK_DIR', __DIR__);
define('EDDBK_SRC_DIR', EDDBK_DIR . '/src');
define('EDDBK_VENDOR_DIR', EDDBK_DIR . '/vendor');
define('EDDBK_MODULES_DIR', EDDBK_DIR . '/modules');
define('EDDBK_AUTOLOAD_FILE', EDDBK_VENDOR_DIR . '/autoload.php');
// I18n
define('EDDBK_TEXT_DOMAIN', 'eddbk');
// Misc
define('EDDBK_CONTACT_PAGE_URL', 'https://eddbookings.com/contact');

// Deactivate plugin on unhandled exception? Can be defined in `wp-config.php`
if (!defined('EDDBK_SAFE_EXCEPTION_HANDLING')) {
    define('EDDBK_SAFE_EXCEPTION_HANDLING', true);
}

// Check PHP version before continuing
if (version_compare(PHP_VERSION, EDDBK_MIN_PHP_VERSION) < 0) {
    $message = __('Bookings for Easy Digital Downloads requires PHP %s', 'eddbk');
    $message = sprintf($message, EDDBK_MIN_PHP_VERSION);
    $exception = new RuntimeException($message);

    eddBkHandleException($exception);
}

// Ensure modules directory exists
if (!file_exists(EDDBK_MODULES_DIR)) {
    mkdir(EDDBK_MODULES_DIR);
}

// Load autoload file if it exists
if (file_exists(EDDBK_AUTOLOAD_FILE)) {
    require EDDBK_AUTOLOAD_FILE;
}

// Run the core plugin module
runEddBkCore();

/**
 * Retrieves the plugin core module, creating it if necessary.
 *
 * @since 0.1
 *
 * @return PluginModule The core plugin module instance.
 *
 * @throws InternalException If the event manager failed to initialize.
 */
function getEddBkCore()
{
    static $instance = null;

    if ($instance === null) {
        /*
         * The list of modules.
         *
         * Each entry in this list should point to a module directory, that has a `module.php` file within it.
         */
        $modules = [
            EDDBK_MODULES_DIR . '/booking-logic',
            EDDBK_MODULES_DIR . '/eddbk-booking-logic',
            EDDBK_MODULES_DIR . '/wp-cqrs',
            EDDBK_MODULES_DIR . '/wp-bookings-cqrs',
            EDDBK_MODULES_DIR . '/eddbk-cqrs',
            EDDBK_MODULES_DIR . '/eddbk-services',
            EDDBK_MODULES_DIR . '/eddbk-session-generator',
            EDDBK_MODULES_DIR . '/eddbk-rest-api',
            EDDBK_MODULES_DIR . '/eddbk-cart',
            EDDBK_MODULES_DIR . '/eddbk-admin-emails',
            EDDBK_MODULES_DIR . '/wp-bookings-front-ui',
            EDDBK_MODULES_DIR . '/wp-bookings-ui',
            EDDBK_MODULES_DIR . '/wp-bookings-shortcode',
            EDDBK_MODULES_DIR . '/eddbk-help',
        ];

        /*
         * The factory for creating configs.
         * Used by the plugin's modular system, as well as by modules.
         */
        $configFactory = new DereferencingConfigMapFactory();
        $configFactory = apply_filters('eddbk_core_module_config_factory', $configFactory);

        /*
         * The factory for creating containers.
         * Used by the plugin's modular system, as well as by modules.
         */
        $containerFactory = new ContainerFactory();
        $containerFactory = apply_filters('eddbk_core_module_container_factory', $containerFactory);

        /*
         * The factory for creating the plugin's composite container.
         * This container will hold other containers as children - for instance the containers given by child modules.
         */
        $compContainerFactory = new CompositeContainerFactory();
        $compContainerFactory = apply_filters('eddbk_core_module_composite_container_factory', $compContainerFactory);

        /*
         * The event manager.
         * Used for managing WordPress hooks as events.
         */
        $eventManager = new WpEventManager(true);
        $eventManager = apply_filters('eddbk_core_module_event_manager', $eventManager);

        /*
         * The event factory.
         * Used in conjunction with the event manager for creating events.
         */
        $eventFactory = new EventFactory();
        $eventFactory = apply_filters('eddbk_core_module_event_factory', $eventFactory);

        /*
         * The core plugin module.
         * This is a special module that loads other modules.
         */
        $coreModule = new PluginModule(
            getEddBkInfo(),
            $configFactory,
            $containerFactory,
            $compContainerFactory,
            $eventManager,
            $eventFactory,
            $modules
        );
        $coreModule = apply_filters('eddbk_core_module', $coreModule);

        // Safety check - in case a filter did something wonky
        if (!$coreModule instanceof ModuleInterface) {
            throw new OutOfRangeException(__('Core module is not a module instance.', 'eddbk'));
        }

        $instance = $coreModule;
    }

    return $instance;
}

/**
 * Invokes the EDD Bookings core module.
 *
 * @since 0.1
 */
function runEddBkCore()
{
    getEddBkErrorHandler()->register();

    // Set up core module
    $container = getEddBkCore()->setup();

    // Run core module when all plugins have been loaded
    add_filter(
        'plugins_loaded',
        function () use ($container) {
            eddBkCheckDependencies();

            getEddBkCore()->run($container);
        },
        0
    );
}


/**
 * Retrieves the plugin info.
 *
 * @since 0.1
 *
 * @return CountableMap
 */
function getEddBkInfo()
{
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . '/wp-admin/includes/plugin.php';
    }

    $pluginData = get_plugin_data(__FILE__);

    return new CountableMap([
        'slug'               => 'eddbk',
        'name'               => $pluginData['Name'],
        'description'        => $pluginData['Description'],
        'uri'                => $pluginData['PluginURI'],
        'version'            => $pluginData['Version'],
        'author'             => $pluginData['Author'],
        'author_uri'         => $pluginData['AuthorURI'],
        'text_domain'        => $pluginData['TextDomain'],
        'config_file_path'   => __DIR__ . '/config.php',
        'services_file_path' => __DIR__ . '/services.php',
        'file_path'          => __FILE__,
        'directory'          => __DIR__,
    ]);
}

/**
 * Checks the required dependencies for EDD Bookings, deactivating with a message if not satisfied.
 *
 * @since 0.1
 */
function eddBkCheckDependencies()
{
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), EDDBK_MIN_WP_VERSION) < 0) {
        $reason = __(
            'Bookings for Easy Digital Downloads requires WordPress at version %1$s or later',
            'eddbk'
        );
        eddBkDeactivateSelf(sprintf($reason, EDDBK_MIN_WP_VERSION));

        return;
    }

    if (!defined('EDD_VERSION') || version_compare(EDD_VERSION, EDDBK_MIN_EDD_VERSION) < 0) {
        $reason = __(
            'Bookings for Easy Digital Downloads requires the Easy Digital Downloads plugin to be installed and activated at version %1$s or later',
            'eddbk'
        );
        eddBkDeactivateSelf(sprintf($reason, EDDBK_MIN_EDD_VERSION));

        return;
    }
}

/**
 * Retrieves the error handler for this plugin.
 *
 * @since 0.1
 *
 * @return ExceptionHandler The error handler.
 */
function getEddBkErrorHandler()
{
    static $instance = null;

    if ($instance === null) {
        $instance = new ExceptionHandler(EDDBK_DIR, 'eddBkHandleException');
    }

    return $instance;
}

/**
 * Handles an exception.
 *
 * @since 0.1
 *
 * @param Exception|Throwable $exception The exception.
 */
function eddBkHandleException($exception)
{
    if (EDDBK_SAFE_EXCEPTION_HANDLING) {
        eddBkDeactivateSelf();
    }

    eddBkErrorPage($exception);
}

/**
 * Deactivates this plugin.
 *
 * @since 0.1
 *
 * @param string|Stringable|null $reason A string containing the reason for deactivation. If not given, the
 *                                       plugin will be deactivated silently. Default: null
 */
function eddBkDeactivateSelf($reason = null)
{
    if (!function_exists('deactivate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    deactivate_plugins(plugin_basename(EDDBK_FILE));

    if (is_null($reason)) {
        return;
    }

    $title   = __('Bookings for Easy Digital Downloads has been deactivated!', 'eddbk');
    $message = sprintf('<h1>%s</h1><p>%s</p>', $title, strval($reason));

    // Show wp_die screen with back link
    wp_die(
        $message,
        $title,
        array('back_link' => true)
    );
}

/**
 * Shows the EDD Bookings exception error page.
 *
 * @since 0.1
 *
 * @param Exception|Throwable $exception The exception.
 */
function eddBkErrorPage($exception)
{
    if (is_admin()) {
        ob_start();
        include EDDBK_DIR . '/templates/error-page.phtml';
        wp_die(
            ob_get_clean(),
            __('Bookings for Easy Digital Downloads Error', 'eddbk'),
            array('response' => 500)
        );
    }
}
