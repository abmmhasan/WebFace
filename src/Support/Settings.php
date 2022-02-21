<?php


namespace AbmmHasan\WebFace\Support;


final class Settings
{
    /**
     * If the route is running from Sub-folder
     *
     * @var string|null
     */
    public static ?string $base_path = null;

    /**
     * PreTag resource file location
     *
     * @var string|null
     */
    public static ?string $pre_tag_file_location = null;

    /**
     * Path to route resources
     *
     * @var string|null
     */
    public static ?string $resource_path = null;

    /**
     * Path to route cache file
     *
     * @var string|null
     */
    public static ?string $cache_path = null;

    /**
     * Should the route loaded from cache
     *
     * @var bool
     */
    public static bool $cache_load = false;

    /**
     * The method to call when execute a class as middleware
     *
     * @var string
     */
    public static string $middleware_call_on_method = 'handle';

    /**
     * Default Cookie Lifetime
     *
     * @var int
     */
    public static int $cookie_lifetime = 60;

    /**
     * Cookie path
     *
     * @var string
     */
    public static string $cookie_path = '/';

    /**
     * The web domain cookie is eligible for
     *
     * @var string
     */
    public static string $cookie_domain = '';

    /**
     * Is the cookie secure (eligible)
     *
     * @var bool
     */
    public static bool $cookie_is_secure = true;

    /**
     * Is the cookie http only
     *
     * @var bool
     */
    public static bool $cookie_http_only = true;

    /**
     * Cookie Same site settings
     *
     * @var string
     */
    public static string $cookie_same_site = '';
}