<?php


namespace AbmmHasan\WebFace\Support;


class Settings
{
    /**
     * Set Controller Namespace
     *
     * @var string
     */
    public static string $base_namespace = 'App\HTTP\Controller';

    /**
     * If the route is running from Subfolder
     *
     * @var string
     */
    public static string $base_path = '';

    /**
     * Check Stored Etag before controller execution
     *
     * @var bool
     */
    public static bool $enable_pre_tag = false;

    /**
     * PreTag resource file location
     *
     * @var bool
     */
    public static string $pre_tag_file_location = '';

    /**
     * Path to route cache file
     *
     * @var string
     */
    public static string $cache_path = '\temp\routes.php';

    /**
     * Should the route loaded from cache
     *
     * @var bool
     */
    public static bool $cache_load = false;

    /**
     * While calling middleware should it inject dependency
     *
     * @var bool
     */
    public static bool $middleware_di = true;

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
     * The web domain cookie is elegible for
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