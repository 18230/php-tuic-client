<?php declare(strict_types=1);

namespace PhpTuic\Integration\Laravel;

use PhpTuic\Http\TuicRequestClient;

class TuicServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/laravel/tuic-client.php', 'tuic-client');

        $this->app->singleton(TuicRequestClient::class, function ($app): TuicRequestClient {
            return new TuicRequestClient(
                configPath: (string) $app['config']->get('tuic-client.config', base_path('config/tuic.yaml')),
                nodeName: $app['config']->get('tuic-client.node_name'),
                httpPort: (int) $app['config']->get('tuic-client.http_port', 8080),
                socksPort: (int) $app['config']->get('tuic-client.socks_port', 1080),
                proxyMode: (string) $app['config']->get('tuic-client.proxy_mode', 'http'),
                phpBinary: $app['config']->get('tuic-client.php_binary'),
                startupTimeout: (float) $app['config']->get('tuic-client.startup_timeout', 10.0),
            );
        });

        $this->app->alias(TuicRequestClient::class, 'tuic-client.http');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/laravel/tuic-client.php' => config_path('tuic-client.php'),
            ], 'tuic-client-config');
        }
    }
}
