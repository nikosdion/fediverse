<?php

declare(strict_types=1);

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Type;

use Exception;

/**
 * \ActivityPhp\Type\Dialect is an abstract class for
 * dialects management.
 */
abstract class Dialect
{
    /**
     * A list of supported dialects by their names
     *
     * @var array
     */
    private static $dialects = [];

    /**
     * A list of types => properties
     * where properties overloads basic ones
     *
     * @var array
     *
     * [
     *  'Person' => [
     *      'propertyName' => [
     *          'defaultValue' => null,
     *          'validator'    => '',
     *          'dialects'     => ['mastodon', 'peertube'],
     *      ]
     *  ]
     * ]
     */
    private static $definitions = [];

    /**
     * Loaded types definitions
     *
     * @var array
     */
    private static $loaded = [];

    /**
     * Clear all dialects, definitions and loaded array
     */
    public static function clear(): void
    {
        self::$dialects = [];
        self::$definitions = [];
        self::$loaded = [];
    }

    /**
     * Load a dialect as an active one.
     *
     * @param  string $dialect Dialect name.
     */
    public static function load(string $dialect): void
    {
        $dialects = [];

        if ($dialect === '*') {
            $dialects = self::$dialects;
        } else {
            $dialects[] = $dialect;
        }

        foreach ($dialects as $dialect) {
            // Dialect does not exist
            if (! in_array($dialect, self::$dialects)) {
                throw new Exception(
                    "Dialect '{$dialect}' has no definition"
                );
            }

            // dialect not already loaded ?
            if (! in_array($dialect, self::$loaded)) {
                array_push(self::$loaded, $dialect);
            }
        }

        // Load new types
        foreach (self::$definitions as $type => $properties) {
            foreach ($properties as $property => $definition) {
                if (count(array_intersect($definition['dialects'], self::$loaded))) {
                    if (! TypeResolver::exists($type)) {
                        TypeResolver::addDialectType($type);
                    }
                }
            }
        }
    }

    /**
     * Unload a dialect.
     *
     * @param string $dialect Dialect name.
     */
    public static function unload(string $dialect): void
    {
        self::$loaded = array_filter(
            self::$loaded,
            static function ($value) use ($dialect): bool {
                return $value !== $dialect
                    && $dialect !== '*';
            }
        );

        // Unload new types
        foreach (self::$definitions as $type => $properties) {
            foreach ($properties as $property => $definition) {
                if (! count(array_intersect($definition['dialects'], self::$loaded))) {
                    if (TypeResolver::exists($type)) {
                        TypeResolver::removeDialectType($type);
                    }
                }
            }
        }
    }

    /**
     * Add a dialect definition in the pool.
     *
     * @param  string $name       Dialect name.
     * @param  array  $definition Types definitions
     */
    public static function add(string $name, array $definition, bool $load = true): void
    {
        // dialect not already defined ?
        if (! in_array($name, self::$dialects)) {
            array_push(self::$dialects, $name);
        }

        /* ---------------------------------------------------------
         | Push definition into definitions
         | --------------------------------------------------------- */
        // Extend Types properties
        foreach ($definition as $types => $properties) {
            $xpt = explode('|', $types);
            foreach ($xpt as $type) {
                if (! is_array($properties)) {
                    throw new Exception(
                        "Properties for Type '{$type}' must be an array."
                        . ' Given=' . print_r($properties, true)
                    );
                }
                self::putType($type, $properties, $name);

                // Define new types if needed
                if ($load && ! TypeResolver::exists($type)) {
                    TypeResolver::addDialectType($type);
                }
            }
        }

        // load if needed
        if ($load && ! in_array($name, self::$loaded)) {
            array_push(self::$loaded, $name);
        }
    }

    /**
     * Add a type in the pool.
     *
     * @param  string $type Type name.
     * @param  string $dialect Dialect name
     */
    private static function putType(string $type, array $properties, string $dialect): void
    {
        // Type already extended ?
        if (! isset(self::$definitions[$type])) {
            self::$definitions[$type] = [];
        }

        // Define a property
        foreach ($properties as $property => $config) {
            if (is_string($config)) {
                $property = $config;
            }
            self::$definitions[$type][$property] =
                self::createProperty($type, $property, $config, $dialect);
        }
    }

    /**
     * Transform various form of property configs into a local and
     * exploitable property configuration.
     *
     * @param   string|array $config
     * @return  array
     */
    private static function createProperty(string $type, string $property, $config, string $dialect): array
    {
        $local = [
            'defaultValue' => null,
            'validator'    => null,
            'dialects'     => [],
        ];

        // Property already defined
        if (array_key_exists($property, self::$definitions[$type])) {
            $local = self::$definitions[$type][$property];
        }

        // New dialect attachment for this property
        if (! in_array($dialect, $local['dialects'])) {
            array_push($local['dialects'], $dialect);
        }

        $local['defaultValue'] = is_array($config) && isset($config['defaultValue'])
            ? $config['defaultValue']
            : null;

        // Validator should be loaded in type factory when this
        // dialect is loaded
        $local['validator'] = is_array($config) && isset($config['validator'])
            ? $config['validator']
            : null;

        return $local;
    }

    /**
     * Check if there is a dialect that extends a given type
     */
    public static function extend(AbstractObject $type): void
    {
        // No extensions for this type
        if (! isset(self::$definitions[$type->type])) {
            return;
        }

        $definition = self::$definitions[$type->type];

        foreach ($definition as $property => $config) {
            // Dialect is not loaded for this property
            if (! count(array_intersect(self::$loaded, $config['dialects']))) {
                continue;
            }

            // Extends type
            $type->extend($property, $config['defaultValue']);

            // @todo Loads a validator for property
        }
    }

    /**
     * Get all loaded dialects (defined and loaded)
     *
     * @return array
     */
    public static function getLoadedDialects(): array
    {
        return self::$loaded;
    }

    /**
     * Get defined dialects (ready to load)
     *
     * @return array
     */
    public static function getDialects(): array
    {
        return self::$dialects;
    }

    /**
     * Get all defined ActivityPub Dialects definitions
     *
     * @return array
     */
    public static function getDefinitions(): array
    {
        return self::$definitions;
    }
}
