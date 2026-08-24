<?php

namespace Tests\Feature;

use Tests\TestCase;

class TimezoneConfigurationTest extends TestCase
{
    public function test_laravel_and_sql_connections_default_to_asia_kolkata(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('Asia/Kolkata', date_default_timezone_get());
        $this->assertSame('+05:30', config('database.connections.mysql.timezone'));
        $this->assertSame('+05:30', config('database.connections.mariadb.timezone'));
        $this->assertSame('Asia/Kolkata', now()->timezoneName);
    }
}
