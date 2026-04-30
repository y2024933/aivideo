<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // 強制使用測試 DB，不碰開發資料
        $app['config']->set('database.connections.mysql.database', 'aivideo_testing');

        return $app;
    }
}
