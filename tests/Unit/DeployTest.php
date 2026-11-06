<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The k3s nginx sidecar must serve exactly what compose serves. */
final class DeployTest extends TestCase
{
    public function test_k3s_nginx_config_is_the_compose_one(): void
    {
        $compose = (string) file_get_contents(dirname(__DIR__, 2).'/nginx/default.conf');
        $k3s = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/k3s/nginx.conf');

        $this->assertSame($compose, $k3s, 'deploy/k3s/nginx.conf drifted from nginx/default.conf — copy it over.');
    }
}
