<?php


namespace AbmmHasan\WebFace\Router\Asset;


final class Settings
{
    /**
     * If the route is running from Sub-folder
     *
     * @var string|null
     */
    public static ?string $basePath = null;

    /**
     * PreTag resource file location
     *
     * @var string|null
     */
    public static ?string $preTagFileLocation = null;

    /**
     * Path to route resources
     *
     * @var string|null
     */
    public static ?string $resourcePath = null;

    /**
     * Path to route cache file
     *
     * @var string|null
     */
    public static ?string $cachePath = null;

    /**
     * Should the route loaded from cache
     *
     * @var bool
     */
    public static bool $cacheLoad = false;

    /**
     * The method to call when execute a class as middleware
     *
     * @var string
     */
    public static string $middlewareCallMethod = 'handle';

    /**
     * Default Cookie Lifetime
     *
     * @var int
     */
    public static int $cookieLifetime = 60;

    /**
     * Cookie path
     *
     * @var string
     */
    public static string $cookiePath = '/';

    /**
     * The web domain cookie is eligible for
     *
     * @var string
     */
    public static string $cookieDomain = '';

    /**
     * Is the cookie secure (eligible)
     *
     * @var bool
     */
    public static bool $cookieIsSecure = true;

    /**
     * Is the cookie http only
     *
     * @var bool
     */
    public static bool $cookieHttpOnly = true;

    /**
     * Cookie Same site settings
     *
     * @var string
     */
    public static string $cookie_same_site = '';
}