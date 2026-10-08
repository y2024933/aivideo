<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    /*
     * ⚠️ 混合部署下兩台機器會把 supervisor 註冊到「同一份 Redis」，/horizon 上若名稱
     *    相同就完全分不出誰是誰（MasterSupervisor::name() 會用 hostname，但容器的
     *    hostname 是隨機 hash）。生產機設 HORIZON_NAME=prod、Mac 設 HORIZON_NAME=mac。
     */
    'name' => env('HORIZON_NAME', env('APP_ROLE', 'prod')),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Environment
    |--------------------------------------------------------------------------
    |
    | HorizonCommand 解析順序是 --environment → config('horizon.env') → config('app.env')。
    | 兩台機器的 APP_ENV 都是 production，但要部署不同的 supervisor 組合，所以另外開一個
    | HORIZON_ENV：生產機留空（= production），Mac 設 production-mac。
    |
    */

    'env' => env('HORIZON_ENV'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
        /*
         * ⚠️ browser queue 用 default 的 60 秒門檻會一直誤報：
         *    1. 單一任務本身就要 20–120 秒（蝦皮商品頁 goto → 攔 get_pc）
         *    2. maxProcesses=1，第二筆任務天生就要排隊
         *    3. 不在 09:00–23:00 台灣時間時任務會刻意留在佇列裡等
         *    4. Mac 睡眠／關機時整個佇列就是停著的（這是設計，不是故障）
         *    真正要盯的是 BrowserHealthWidget 的心跳與積壓，不是這個 event。
         */
        'redis:browser' => 1800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    /*
     * ⚠️⚠️ Laravel 硬規則：每個 supervisor 的 timeout 必須「小於」
     *      config('queue.connections.redis.retry_after')（目前 900 秒）。
     *
     *      retry_after 是「job 被取走後多久沒 ack 就重新派送」。若 timeout >= retry_after，
     *      queue 會在 worker 還在跑的時候把同一個 job 再派一次 —— 對 browser queue 來說
     *      等於「同一個 Chrome user-data-dir 被兩個 process 同時開啟」，profile 會損壞，
     *      而修復 profile 的代價是重新 SMS OTP 登入蝦皮。
     *
     *      tests/Unit/Config/HorizonQueueConfigTest.php 會斷言這個關係，別把它改大。
     */
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],

        /*
         * browser queue 的專屬 supervisor。
         *
         * 在這個 supervisor 出現之前，supervisor-1 只吃 ['default']，所以所有
         * ->onQueue('browser') 的 job（ScrapeShopeeProductJob 等）會被推進 Redis
         * 然後「永遠沒有人取」—— 商品卡在 importing、UI 看起來像當掉。
         *
         * ⚠️ maxProcesses 必須是 1，不是效能設定：
         *    1. 同一個 Chrome profile（user-data-dir）不能被多個 process 同時開啟，
         *       會直接損壞 → 要重新 SMS OTP
         *    2. 併發抓取是最明顯的機器人特徵，蝦皮封的是帳號
         *    3. Mac 上的 browser 容器 memory limit 3g，兩個 Chromium 就 OOM
         */
        'supervisor-browser' => [
            'connection' => 'redis',
            'queue' => ['browser'],
            // simple（非 auto）：只有一個 queue，沒有東西要 balance
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            // 128 不夠：Playwright client 本身加上要在記憶體裡搬 HAR / trace
            'memory' => 256,
            // 重試與 backoff 全部在 PlaywrightBrowserAutomation 內部做，
            // 這層再重試會讓單一商品打出十幾次請求、撞爆 rate limit
            'tries' => 1,
            // 600 < retry_after(900)，見上方硬規則說明
            'timeout' => 600,
            'nice' => 0,
        ],
    ],

    /*
     * ⚠️ Horizon 的 environments 是「覆寫」而非「白名單」：ProvisioningPlan 對每個
     *    environment 做 array_replace_recursive(defaults, plan)，所以 defaults 裡的
     *    supervisor 會出現在每個 environment。要讓某台機器不部署某個 supervisor，
     *    唯一的方法是把 maxProcesses 設成 0（deploy() 只在 maxProcesses > 0 時 add）。
     */
    'environments' => [
        // 生產 VPS（1 CPU / 2GB RAM）：只跑 default，絕對不跑瀏覽器
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            // 0 = 不部署。生產機的 BROWSER_SERVICE_URL 留空，真的取了 job 只會立刻失敗；
            // 留在佇列裡等 Mac 開機才是正確行為。
            'supervisor-browser' => [
                'maxProcesses' => 0,
            ],
        ],

        // Paul 的 Mac（HORIZON_ENV=production-mac）：只跑 browser
        'production-mac' => [
            'supervisor-1' => [
                'maxProcesses' => 0,
            ],
            'supervisor-browser' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-browser' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
