<?php declare(strict_types=1);

namespace PhpTuic\Integration\ThinkPHP;

use PhpTuic\Http\TuicRequestClient;

class TuicService extends \think\Service
{
    public function register(): void
    {
        $this->app->bind(TuicRequestClient::class, function (): TuicRequestClient {
            return new TuicRequestClient(
                configPath: (string) $this->app->config->get('tuic_client.config', root_path() . 'config/tuic.yaml'),
                nodeName: $this->app->config->get('tuic_client.node_name'),
                httpPort: (int) $this->app->config->get('tuic_client.http_port', 8080),
                socksPort: (int) $this->app->config->get('tuic_client.socks_port', 1080),
                proxyMode: (string) $this->app->config->get('tuic_client.proxy_mode', 'http'),
                phpBinary: $this->app->config->get('tuic_client.php_binary'),
                startupTimeout: (float) $this->app->config->get('tuic_client.startup_timeout', 10.0),
            );
        });
    }
}
