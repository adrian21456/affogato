<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit015ec0479d3a185ef13623441de8e6fc
{
    public static $files = array (
        'bc60d1ecc1004c1753a8d1bbc82b6a12' => __DIR__ . '/../..' . '/src/helpers.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'Zchted\\Affogato\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Zchted\\Affogato\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit015ec0479d3a185ef13623441de8e6fc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit015ec0479d3a185ef13623441de8e6fc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit015ec0479d3a185ef13623441de8e6fc::$classMap;

        }, null, ClassLoader::class);
    }
}
