<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\NavigationItem;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $siteAdminRole = Role::firstOrCreate(['name' => 'site_admin', 'guard_name' => 'web']);

        // =====================================================================
        // 刪除舊的 future-habitat 站台
        // =====================================================================
        $oldSite = Site::where('slug', 'future-habitat')->first();
        if ($oldSite) {
            // 清除關聯資料
            NavigationItem::where('site_id', $oldSite->id)->delete();
            Page::where('site_id', $oldSite->id)->delete();
            Project::where('site_id', $oldSite->id)->delete();
            NewsArticle::where('site_id', $oldSite->id)->delete();
            ProgressUpdate::where('site_id', $oldSite->id)->delete();
            ContactMessage::where('site_id', $oldSite->id)->delete();
            SiteSetting::where('site_id', $oldSite->id)->delete();
            SiteDomain::where('site_id', $oldSite->id)->delete();
            $oldSite->users()->detach();
            $oldSite->delete();
        }

        // =====================================================================
        // 站 A：大宅威建設
        // =====================================================================
        $siteA = Site::updateOrCreate(
            ['slug' => 'da-zhai-wei'],
            [
                'name' => '大宅威建設',
                'brand_name' => '金州開發建設',
                'primary_domain' => 'dzw.local',
                'theme_key' => 'builder-classic',
                'primary_color' => '#2c3e50',
                'secondary_color' => '#c0965c',
                'contact_email' => 'service@dzw.com.tw',
                'contact_phone' => '04-8955531',
                'is_active' => true,
            ]
        );

        SiteDomain::updateOrCreate(
            ['domain' => 'dzw.local'],
            ['site_id' => $siteA->id, 'is_primary' => true]
        );

        // =====================================================================
        // 站 B：泉宇建設（editorial theme）
        // =====================================================================
        $siteB = Site::updateOrCreate(
            ['slug' => 'chyuan-yeu'],
            [
                'name' => '泉宇建設',
                'brand_name' => 'CHYUAN YEU',
                'primary_domain' => 'chyuanyeu.local',
                'theme_key' => 'builder-editorial',
                'primary_color' => '#184c61',
                'secondary_color' => '#b59a6a',
                'contact_email' => 'service@chyuan-yeu.tw',
                'contact_phone' => '04-2259-6826',
                'is_active' => true,
            ]
        );

        SiteDomain::updateOrCreate(
            ['domain' => 'chyuanyeu.local'],
            ['site_id' => $siteB->id, 'is_primary' => true]
        );

        // =====================================================================
        // 站 A SiteSetting
        // =====================================================================
        SiteSetting::updateOrCreate(
            ['site_id' => $siteA->id],
            [
                'homepage_sections' => ['hero', 'projects', 'news', 'contact'],
                'hero_content' => [
                    'eyebrow' => '大宅威建設・金州開發建設',
                    'headline' => '文化為本・世代傳家',
                    'subheadline' => '以建築承載文化，用品質傳承價值',
                    'cta_label' => '了解更多',
                    'cta_link' => '/about',
                    'video_url' => null,
                    'background_image' => null,
                ],
                'seo_defaults' => [
                    'title' => '大宅威建設・金州開發建設',
                    'description' => '文化為本・世代傳家，以建築承載文化，用品質傳承價值。',
                ],
                'about_content' => [
                    'eyebrow' => 'About',
                    'headline' => '文化為本・世代傳家',
                    'summary' => '秉持「文化傳家」的核心精神深耕彰化住宅建設與土地開發，我們始終深信，一座好的建築不應僅提供居住空間，更是承載家庭情感、生活記憶與世代價值的生命容器。',
                    'highlights' => [
                        ['title' => '文化為本', 'description' => '一座好的建築不僅提供居住空間，更承載家庭情感與世代價值。'],
                        ['title' => '品質至上', 'description' => '從選地到交付，嚴格把關每一個建築進程。'],
                        ['title' => '責任承諾', 'description' => '確保每一份託付都能作為永恆的根基。'],
                    ],
                    'team_members' => [],
                ],
                'service_content' => [
                    'eyebrow' => 'Services',
                    'headline' => '延續建築價值',
                    'summary' => '專業包租代管服務，將建築專業延續至資產管理的每一個環節。',
                    'cta_label' => '查看服務',
                    'cards' => [
                        ['number' => '01', 'title' => '不動產', 'description' => '深耕在地不動產開發與銷售。'],
                        ['number' => '02', 'title' => '代租代管', 'description' => '從招租、簽約到維護，建立穩定的管理流程。'],
                    ],
                ],
                'contact_content' => [
                    'eyebrow' => 'Contact',
                    'headline' => '聯絡我們',
                    'summary' => '大宅威建設與金州開發建設秉持「文化為本・品質至上・責任承諾」三大信念。',
                    'cta_label' => '前往聯絡表單',
                    'inquiry_types' => ['預約看屋', '線上報修', '包租代管', '合作提案', '建議事項', '其他'],
                    'highlights' => [],
                ],
                'social_links' => [
                    'facebook' => 'https://facebook.com/',
                    'instagram' => 'https://instagram.com/',
                    'line' => 'https://line.me/',
                ],
                'footer_content' => [
                    'address' => '彰化縣二林鎮斗苑路五段345號一樓',
                    'phone' => '04-8955531',
                    'sales_phone' => '0977-665967・0975-385-732',
                    'fax' => '04-8955531',
                    'email' => 'service@dzw.com.tw',
                    'copyright' => '© 大宅威建設・金州開發建設有限公司 All Rights Reserved.',
                ],
            ]
        );

        // =====================================================================
        // 站 B SiteSetting（泉宇建設完整設定）
        // =====================================================================
        SiteSetting::updateOrCreate(
            ['site_id' => $siteB->id],
            [
                'homepage_sections' => ['hero', 'about', 'services', 'projects', 'news', 'progress', 'contact'],
                'hero_content' => [
                    'eyebrow' => '泉宇建設 CHYUAN YEU',
                    'headline' => '文化為本，世代傳家',
                    'subheadline' => '以建築承載文化、用品質傳承價值，從品牌官網、建案展示到工程進度與聯絡服務，都由同一個後台整合維護。',
                    'cta_label' => '查看建案',
                    'cta_link' => '/projects',
                    'video_url' => null,
                    'background_image' => null,
                ],
                'seo_defaults' => [
                    'title' => '泉宇建設',
                    'description' => '以文化為本、品質至上、責任承諾作為品牌核心的建設公司。',
                ],
                'about_content' => [
                    'eyebrow' => 'About',
                    'headline' => '關於我們',
                    'summary' => '以文化為本、品質至上、責任承諾作為品牌核心，讓每一件作品都能承載世代生活記憶。',
                    'highlights' => [
                        ['title' => '品牌價值', 'description' => '深耕在地住宅建設與土地開發，強調作品與生活的關係。'],
                        ['title' => '品質承諾', 'description' => '從選地、規劃、施工到交付，維持一致的標準與細節要求。'],
                        ['title' => '世代傳承', 'description' => '讓居住不只是一時的產品，而是可以延續的家庭資產。'],
                    ],
                    'team_members' => [
                        [
                            'name' => '林承岳',
                            'title' => '總經理',
                            'company' => '承岳建築',
                            'description' => '堅持用將心比心與自己要住的心情，專業、認真完成每一位購屋者的託付。',
                            'photo' => null,
                        ],
                        [
                            'name' => '陳宇衡',
                            'title' => '總經理',
                            'company' => '立衡營造',
                            'description' => '對於品質做到專業、精準，持續以制度與工法細節追求更高標準。',
                            'photo' => null,
                        ],
                    ],
                ],
                'service_content' => [
                    'eyebrow' => 'Services',
                    'headline' => '多元服務',
                    'summary' => '從售後維護到代租代管，將建築價值延伸到居住之後的每一個環節。',
                    'cta_label' => '查看服務',
                    'cards' => [
                        ['number' => '01', 'title' => '包租代管', 'description' => '協助招租收租、房客服務與日常管理。'],
                        ['number' => '02', 'title' => '售後維護', 'description' => '保固追蹤、設備維護與居住回報整合。'],
                        ['number' => '03', 'title' => '品牌內容', 'description' => '建案、消息與工程內容持續更新，維持品牌節奏。'],
                    ],
                ],
                'contact_content' => [
                    'eyebrow' => 'Contact',
                    'headline' => '聯絡我們',
                    'summary' => '所有詢問會統一進後台，由站台管理員依類型與建案分流處理。',
                    'cta_label' => '前往聯絡表單',
                    'inquiry_types' => ['預約看屋', '線上報修', '包租代管', '合作提案', '建議事項', '其他'],
                    'highlights' => [
                        ['title' => '留言類別', 'description' => '預約看屋 / 報修 / 包租代管'],
                        ['title' => '對應建案', 'description' => '可依專案選擇，訊息自動帶入後台'],
                        ['title' => '管理流程', 'description' => '新訊息、處理中、已結案'],
                    ],
                ],
                'social_links' => [
                    'facebook' => 'https://facebook.com/',
                    'instagram' => 'https://instagram.com/',
                    'line' => 'https://line.me/',
                ],
                'footer_content' => [
                    'address' => '台中市西屯區朝馬二街5號2F',
                    'phone' => '(04) 2259-6826',
                    'email' => 'service@chyuan-yeu.tw',
                    'copyright' => 'Copyright © 泉宇建設',
                ],
            ]
        );

        // =====================================================================
        // Users
        // =====================================================================
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $superAdmin->syncRoles([$superAdminRole]);
        $superAdmin->sites()->syncWithoutDetaching([$siteA->id, $siteB->id]);

        $siteAdmin = User::updateOrCreate(
            ['email' => 'siteadmin@example.com'],
            [
                'name' => 'Site Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $siteAdmin->syncRoles([$siteAdminRole]);
        $siteAdmin->sites()->sync([$siteA->id]);

        // =====================================================================
        // 站 A Pages（大宅威 7 頁）
        // =====================================================================
        $siteAPages = $this->seedPages($siteA, $siteAdmin, [
            [
                'title' => '首頁',
                'slug' => 'home',
                'page_type' => 'home',
                'layout_key' => 'builder-classic',
                'summary' => '大宅威建設首頁，聚合品牌主標、精選建案、最新消息與聯絡 CTA。',
                'content' => '<p>大宅威建設與金州開發建設秉持「文化為本、品質至上、責任承諾」三大信念，讓每一處居所不僅是住宅，更是生活價值的延續。</p>',
                'sort_order' => 1,
            ],
            [
                'title' => '關於我們',
                'slug' => 'about',
                'page_type' => 'about',
                'layout_key' => 'builder-classic',
                'summary' => '文化為本，世代傳家。',
                'content' => '<p>秉持「文化傳家」的核心精神深耕彰化住宅建設與土地開發，我們始終深信，一座好的建築不應僅提供居住空間，更是承載家庭情感、生活記憶與世代價值的生命容器。</p><p>從選地開始到每一處規劃細節，從反覆推敲與嚴謹施工直到最終圓滿交付，我們嚴格把關每一個建築進程，確保每一份託付都能作為永恆的根基。</p>',
                'sort_order' => 2,
            ],
            [
                'title' => '建築作品',
                'slug' => 'projects',
                'page_type' => 'projects',
                'layout_key' => 'builder-classic',
                'summary' => '熱銷新案與歷史建案統一由後台維護。',
                'content' => '<p>我們所打造的不只是建築本身，更是在構築一處能與時間共鳴、讓情感傳承的生活場域。</p>',
                'sort_order' => 3,
            ],
            [
                'title' => '最新消息',
                'slug' => 'news',
                'page_type' => 'news',
                'layout_key' => 'builder-classic',
                'summary' => '文章、活動與工程紀錄可由編輯器維護。',
                'content' => '<p>建築，是時間的紀錄；土地，是乘載記憶的紋理。在這裡，我們見證建築旅程的每一步前行。</p>',
                'sort_order' => 4,
            ],
            [
                'title' => '多元服務',
                'slug' => 'services',
                'page_type' => 'services',
                'layout_key' => 'builder-classic',
                'summary' => '延續建築價值的多元服務。',
                'content' => '<p>憑藉深耕彰化的建設經驗，我們進一步延伸服務版圖，成立專業包租代管團隊，將建築專業延續至資產管理的每一個環節。</p>',
                'sort_order' => 5,
            ],
            [
                'title' => '工程進度',
                'slug' => 'progress',
                'page_type' => 'progress',
                'layout_key' => 'builder-classic',
                'summary' => '工程節點更新與照片記錄。',
                'content' => '<p>從地基開挖、結構施作、水電配置到每一處細節修整，每一道工序皆如實紀錄、透明呈現。</p>',
                'sort_order' => 6,
            ],
            [
                'title' => '聯絡我們',
                'slug' => 'contact',
                'page_type' => 'contact',
                'layout_key' => 'builder-classic',
                'summary' => '整合聯絡資料與表單說明。',
                'content' => '<p>無論您是在尋覓一處足以承載情感的傳家居所，或是尋求資產價值的專業維護與延續，我們團隊都會以最嚴謹的專業與溫暖的服務，聆取您的需求。</p>',
                'sort_order' => 7,
            ],
        ]);

        // =====================================================================
        // 站 A Navigation
        // =====================================================================
        // Primary nav
        $siteAPrimaryNav = [
            ['label' => '關於我們', 'slug' => 'about', 'sort_order' => 1],
            ['label' => '建築作品', 'slug' => 'projects', 'sort_order' => 2],
            ['label' => '最新消息', 'slug' => 'news', 'sort_order' => 3],
            ['label' => '多元服務', 'slug' => 'services', 'sort_order' => 4],
            ['label' => '工程進度', 'slug' => 'progress', 'sort_order' => 5],
            ['label' => '聯絡我們', 'slug' => 'contact', 'sort_order' => 6],
        ];

        foreach ($siteAPrimaryNav as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteA->id, 'position' => 'primary', 'label' => $item['label']],
                [
                    'page_id' => $siteAPages[$item['slug']]->id,
                    'url' => '/' . $item['slug'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // Secondary nav — 建築作品子選單
        $projectsParentA = NavigationItem::updateOrCreate(
            ['site_id' => $siteA->id, 'position' => 'secondary', 'label' => '建築作品'],
            [
                'page_id' => $siteAPages['projects']->id,
                'url' => '/projects',
                'target' => '_self',
                'sort_order' => 1,
                'is_visible' => true,
            ]
        );

        foreach ([
            ['label' => '熱銷新案', 'url' => '/projects?status=selling', 'sort_order' => 1],
            ['label' => '歷史建案', 'url' => '/projects?status=completed', 'sort_order' => 2],
        ] as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteA->id, 'parent_id' => $projectsParentA->id, 'label' => $item['label']],
                [
                    'position' => 'secondary',
                    'url' => $item['url'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // Secondary nav — 多元服務子選單
        $servicesParentA = NavigationItem::updateOrCreate(
            ['site_id' => $siteA->id, 'position' => 'secondary', 'label' => '多元服務'],
            [
                'page_id' => $siteAPages['services']->id,
                'url' => '/services',
                'target' => '_self',
                'sort_order' => 2,
                'is_visible' => true,
            ]
        );

        foreach ([
            ['label' => '不動產', 'url' => '/services#real-estate', 'sort_order' => 1],
            ['label' => '代租代管', 'url' => '/services#property-management', 'sort_order' => 2],
        ] as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteA->id, 'parent_id' => $servicesParentA->id, 'label' => $item['label']],
                [
                    'position' => 'secondary',
                    'url' => $item['url'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // Footer nav
        $siteAFooterNav = [
            ['label' => '首頁', 'slug' => 'home', 'url_override' => '/', 'sort_order' => 1],
            ['label' => '關於我們', 'slug' => 'about', 'sort_order' => 2],
            ['label' => '建築作品', 'slug' => 'projects', 'sort_order' => 3],
            ['label' => '最新消息', 'slug' => 'news', 'sort_order' => 4],
            ['label' => '多元服務', 'slug' => 'services', 'sort_order' => 5],
            ['label' => '工程進度', 'slug' => 'progress', 'sort_order' => 6],
            ['label' => '聯絡我們', 'slug' => 'contact', 'sort_order' => 7],
        ];

        foreach ($siteAFooterNav as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteA->id, 'position' => 'footer', 'label' => $item['label']],
                [
                    'page_id' => $siteAPages[$item['slug']]->id,
                    'url' => $item['url_override'] ?? '/' . $item['slug'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // =====================================================================
        // 站 A Projects（大宅威 demo 建案）
        // =====================================================================
        $dzwProjectDefaults = [
            'featured_image_path' => null,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ];

        // 熱銷新案
        $dzwProject1 = Project::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'tianxia-yipin-7'],
            array_merge($dzwProjectDefaults, [
                'name' => '天下一品7',
                'status' => 'selling',
                'project_category' => 'residential',
                'location' => '彰化',
                'address' => '彰化縣二林鎮',
                'launch_year' => 2026,
                'tagline' => '文化傳家・品質再現',
                'summary' => '天下一品系列第七代作品，延續品牌經典。',
                'description' => '<p>天下一品系列第七代作品，以文化傳家為核心，延續品牌對居住品質的堅持。</p>',
                'area' => '35-52 坪',
                'households' => '38 戶',
                'floors' => '地上 12 層 / 地下 2 層',
                'layout_plan' => '3-4 房',
                'is_featured' => true,
                'sort_order' => 1,
            ])
        );

        Project::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'wenhua-chuanjia-2'],
            array_merge($dzwProjectDefaults, [
                'name' => '文化傳家2',
                'status' => 'selling',
                'project_category' => 'residential',
                'location' => '彰化',
                'address' => '彰化縣二林鎮',
                'launch_year' => 2025,
                'tagline' => '傳承文化・安居之所',
                'summary' => '文化傳家系列第二代，傳承品牌價值。',
                'description' => '<p>文化傳家系列延續品牌「文化為本」理念，打造適合世代居住的住宅。</p>',
                'area' => '30-45 坪',
                'households' => '32 戶',
                'floors' => '地上 10 層 / 地下 2 層',
                'layout_plan' => '3 房',
                'is_featured' => true,
                'sort_order' => 2,
            ])
        );

        // 歷史建案
        $dzwHistoryProjects = [
            ['name' => '天下一品6', 'slug' => 'tianxia-yipin-6', 'year' => 2024, 'category' => 'residential', 'tagline' => '品質經典・穩健傳承', 'area' => '33-48 坪', 'households' => '36 戶', 'floors' => '地上 11 層 / 地下 2 層', 'layout_plan' => '3-4 房', 'sort_order' => 3],
            ['name' => '天下一品5', 'slug' => 'tianxia-yipin-5', 'year' => 2023, 'category' => 'residential', 'tagline' => '都心首選・安心之作', 'area' => '30-45 坪', 'households' => '30 戶', 'floors' => '地上 10 層 / 地下 2 層', 'layout_plan' => '3 房', 'sort_order' => 4],
            ['name' => '天下一品3', 'slug' => 'tianxia-yipin-3', 'year' => 2022, 'category' => 'residential', 'tagline' => '文化根基・品質標竿', 'area' => '28-42 坪', 'households' => '28 戶', 'floors' => '地上 9 層 / 地下 1 層', 'layout_plan' => '2-3 房', 'sort_order' => 5],
            ['name' => '天下一品2', 'slug' => 'tianxia-yipin-2', 'year' => 2021, 'category' => 'villa', 'tagline' => '靜巷別墅・悠然生活', 'area' => '55-70 坪', 'households' => '12 戶', 'floors' => '地上 4 層', 'layout_plan' => '4 房', 'sort_order' => 6],
            ['name' => '天下一品1', 'slug' => 'tianxia-yipin-1', 'year' => 2020, 'category' => 'villa', 'tagline' => '系列起點・經典之作', 'area' => '50-65 坪', 'households' => '10 戶', 'floors' => '地上 3 層', 'layout_plan' => '4 房', 'sort_order' => 7],
            ['name' => '文化傳家1', 'slug' => 'wenhua-chuanjia-1', 'year' => 2019, 'category' => 'residential', 'tagline' => '文化傳家・開創之作', 'area' => '28-40 坪', 'households' => '24 戶', 'floors' => '地上 8 層 / 地下 1 層', 'layout_plan' => '2-3 房', 'sort_order' => 8],
        ];

        foreach ($dzwHistoryProjects as $p) {
            Project::updateOrCreate(
                ['site_id' => $siteA->id, 'slug' => $p['slug']],
                array_merge($dzwProjectDefaults, [
                    'name' => $p['name'],
                    'status' => 'completed',
                    'project_category' => $p['category'],
                    'location' => '彰化',
                    'address' => '彰化縣二林鎮',
                    'launch_year' => $p['year'],
                    'tagline' => $p['tagline'],
                    'summary' => '完銷｜' . $p['tagline'],
                    'description' => '<p>' . $p['name'] . '，' . $p['tagline'] . '。</p>',
                    'area' => $p['area'],
                    'households' => $p['households'],
                    'floors' => $p['floors'],
                    'layout_plan' => $p['layout_plan'],
                    'is_featured' => false,
                    'sort_order' => $p['sort_order'],
                ])
            );
        }

        // =====================================================================
        // 站 A News
        // =====================================================================
        NewsArticle::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'tianxia7-launch'],
            [
                'title' => '天下一品7 正式公開',
                'category' => '新訊動態',
                'summary' => '天下一品系列第七代作品正式亮相，歡迎蒞臨現場了解。',
                'content' => '<p>天下一品7 延續品牌經典，以文化傳家為核心理念，現場提供基地模型、建材展示與專人導覽。</p>',
                'published_at' => now()->subDays(3),
                'is_published' => true,
                'sort_order' => 1,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        NewsArticle::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'wenhua2-progress'],
            [
                'title' => '文化傳家2 工程穩步推進',
                'category' => '工程進度',
                'summary' => '文化傳家2 主體結構工程順利進行，預計如期交付。',
                'content' => '<p>文化傳家2 主體結構持續推進，各樓層施工按期完成，品質控管嚴格落實。</p>',
                'published_at' => now()->subDays(10),
                'is_published' => true,
                'sort_order' => 2,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        NewsArticle::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'dzw-brand-story'],
            [
                'title' => '大宅威建設品牌故事',
                'category' => '新訊動態',
                'summary' => '深耕彰化在地住宅建設，以文化為本、品質至上的經營信念。',
                'content' => '<p>大宅威建設與金州開發建設，秉持文化傳家精神，持續在彰化打造高品質住宅。</p>',
                'published_at' => now()->subDays(25),
                'is_published' => true,
                'sort_order' => 3,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        NewsArticle::updateOrCreate(
            ['site_id' => $siteA->id, 'slug' => 'tianxia6-completion'],
            [
                'title' => '天下一品6 圓滿完工',
                'category' => '工程進度',
                'summary' => '天下一品6 順利完工交付，感謝所有住戶的信任與支持。',
                'content' => '<p>天下一品6 歷經嚴謹施工與品質把關，順利完工交付，為品牌再添經典之作。</p>',
                'published_at' => now()->subDays(60),
                'is_published' => true,
                'sort_order' => 4,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        // =====================================================================
        // 站 A Progress（綁天下一品7）
        // =====================================================================
        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteA->id, 'title' => '主體結構工程'],
            [
                'project_id' => $dzwProject1->id,
                'summary' => '天下一品7 主體結構工程穩定推進中。',
                'content' => '<p>各樓層結構施作依序完成，品質把關嚴格落實。</p>',
                'progress_percent' => 65,
                'reported_at' => now()->subDays(2),
                'is_published' => true,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteA->id, 'title' => '地下基礎工程'],
            [
                'project_id' => $dzwProject1->id,
                'summary' => '基礎開挖與地下結構施作已完成。',
                'content' => '<p>地下結構與防水節點全數完成，進入上部結構階段。</p>',
                'progress_percent' => 100,
                'reported_at' => now()->subMonths(2),
                'is_published' => true,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteA->id, 'title' => '水電配管工程'],
            [
                'project_id' => $dzwProject1->id,
                'summary' => '機電管線配置持續進行中。',
                'content' => '<p>各樓層機電配置與弱電點位同步施作確認中。</p>',
                'progress_percent' => 50,
                'reported_at' => now()->subDays(5),
                'is_published' => true,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteA->id, 'title' => '開工典禮'],
            [
                'project_id' => $dzwProject1->id,
                'summary' => '天下一品7 開工動土，正式啟動建設。',
                'content' => '<p>天下一品7 開工典禮圓滿完成，工程正式啟動。</p>',
                'progress_percent' => 100,
                'reported_at' => now()->subMonths(6),
                'is_published' => true,
                'created_by' => $siteAdmin->id,
                'updated_by' => $siteAdmin->id,
            ]
        );

        // =====================================================================
        // 站 A Contact（示範）
        // =====================================================================
        ContactMessage::updateOrCreate(
            ['site_id' => $siteA->id, 'name' => '張先生', 'phone' => '0912-345-678'],
            [
                'project_id' => $dzwProject1->id,
                'inquiry_type' => '預約看屋',
                'email' => 'buyer@example.com',
                'message' => '想了解天下一品7 目前可售樓層與付款方式。',
                'status' => 'new',
                'source_page' => '/contact',
                'assigned_to' => $siteAdmin->name,
            ]
        );

        // =====================================================================
        // 站 B Pages（泉宇建設 7 頁）
        // =====================================================================
        $siteBPages = $this->seedPages($siteB, $superAdmin, [
            [
                'title' => '首頁',
                'slug' => 'home',
                'page_type' => 'home',
                'layout_key' => 'builder-editorial',
                'summary' => '泉宇建設首頁，品牌官網形式呈現。',
                'content' => '<p>以建築承載文化、用品質傳承價值，從品牌官網、建案展示到工程進度與聯絡服務，都由同一個後台整合維護。</p>',
                'sort_order' => 1,
            ],
            [
                'title' => '關於我們',
                'slug' => 'about',
                'page_type' => 'about',
                'layout_key' => 'builder-editorial',
                'summary' => '以文化為本、品質至上、責任承諾作為品牌核心。',
                'content' => '<p>秉持「文化傳家」的核心精神深耕住宅建設與土地開發，讓每一件作品都能承載世代生活記憶。</p>',
                'sort_order' => 2,
            ],
            [
                'title' => '建築業績',
                'slug' => 'projects',
                'page_type' => 'projects',
                'layout_key' => 'builder-editorial',
                'summary' => '熱銷新案與歷史建案統一由後台維護。',
                'content' => '<p>我們所打造的不只是建築本身，更是在構築一處能與時間共鳴、讓情感傳承的生活場域。</p>',
                'sort_order' => 3,
            ],
            [
                'title' => '最新消息',
                'slug' => 'news',
                'page_type' => 'news',
                'layout_key' => 'builder-editorial',
                'summary' => '文章、活動與工程紀錄。',
                'content' => '<p>建築，是時間的紀錄；土地，是乘載記憶的紋理。</p>',
                'sort_order' => 4,
            ],
            [
                'title' => '多元服務',
                'slug' => 'services',
                'page_type' => 'services',
                'layout_key' => 'builder-editorial',
                'summary' => '從售後維護到代租代管，延伸建築價值。',
                'content' => '<p>從售後維護到代租代管，將建築價值延伸到居住之後的每一個環節。</p>',
                'sort_order' => 5,
            ],
            [
                'title' => '工程進度',
                'slug' => 'progress',
                'page_type' => 'progress',
                'layout_key' => 'builder-editorial',
                'summary' => '工程節點更新與照片記錄。',
                'content' => '<p>每一道工序皆如實紀錄、透明呈現。</p>',
                'sort_order' => 6,
            ],
            [
                'title' => '聯絡我們',
                'slug' => 'contact',
                'page_type' => 'contact',
                'layout_key' => 'builder-editorial',
                'summary' => '整合聯絡資料與表單說明。',
                'content' => '<p>所有詢問會統一進後台，由站台管理員依類型與建案分流處理。</p>',
                'sort_order' => 7,
            ],
        ]);

        // =====================================================================
        // 站 B Navigation
        // =====================================================================
        $siteBPrimaryNav = [
            ['label' => '關於我們', 'slug' => 'about', 'sort_order' => 1],
            ['label' => '建築作品', 'slug' => 'projects', 'sort_order' => 2],
            ['label' => '最新消息', 'slug' => 'news', 'sort_order' => 3],
            ['label' => '多元服務', 'slug' => 'services', 'sort_order' => 4],
            ['label' => '工程進度', 'slug' => 'progress', 'sort_order' => 5],
            ['label' => '聯絡我們', 'slug' => 'contact', 'sort_order' => 6],
        ];

        foreach ($siteBPrimaryNav as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteB->id, 'position' => 'primary', 'label' => $item['label']],
                [
                    'page_id' => $siteBPages[$item['slug']]->id,
                    'url' => '/' . $item['slug'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        $projectsParentB = NavigationItem::updateOrCreate(
            ['site_id' => $siteB->id, 'position' => 'secondary', 'label' => '建築作品'],
            [
                'page_id' => $siteBPages['projects']->id,
                'url' => '/projects',
                'target' => '_self',
                'sort_order' => 1,
                'is_visible' => true,
            ]
        );

        foreach ([
            ['label' => '熱銷新案', 'url' => '/projects?status=selling', 'sort_order' => 1],
            ['label' => '歷史建案', 'url' => '/projects?status=completed', 'sort_order' => 2],
        ] as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteB->id, 'parent_id' => $projectsParentB->id, 'label' => $item['label']],
                [
                    'position' => 'secondary',
                    'url' => $item['url'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // 站 B Footer nav
        $siteBFooterNav = [
            ['label' => '首頁', 'slug' => 'home', 'url_override' => '/', 'sort_order' => 1],
            ['label' => '品牌故事', 'slug' => 'about', 'sort_order' => 2],
            ['label' => '泉宇新訊', 'slug' => 'news', 'sort_order' => 3],
            ['label' => '熱銷建案', 'slug' => 'projects', 'sort_order' => 4],
            ['label' => '建築軌跡', 'slug' => 'projects', 'url_override' => '/projects?status=completed', 'sort_order' => 5],
            ['label' => '工程進度', 'slug' => 'progress', 'sort_order' => 6],
            ['label' => '宇您有約', 'slug' => 'contact', 'sort_order' => 7],
        ];

        foreach ($siteBFooterNav as $item) {
            NavigationItem::updateOrCreate(
                ['site_id' => $siteB->id, 'position' => 'footer', 'label' => $item['label']],
                [
                    'page_id' => $siteBPages[$item['slug']]->id,
                    'url' => $item['url_override'] ?? '/' . $item['slug'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                ]
            );
        }

        // =====================================================================
        // 站 B Projects（泉宇建設核心 3 筆）
        // =====================================================================
        $cyProjectDefaults = [
            'featured_image_path' => null,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ];

        $cyProject1 = Project::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'opera-residence'],
            array_merge($cyProjectDefaults, [
                'name' => '泉宇雲鼎',
                'status' => 'selling',
                'project_category' => 'residential',
                'location' => '台中歌劇院特區',
                'address' => '台中市西屯區河南路二段與市政北七路口',
                'launch_year' => 2026,
                'tagline' => '國家歌劇院特區，稀有雙併產品。',
                'summary' => '國家歌劇院特區，稀有雙併產品。',
                'description' => '<p>泉宇雲鼎位於歌劇院特區，以俐落量體與雙併規劃，呈現品牌對生活尺度與居住品質的思考。</p>',
                'area' => '64-66 坪',
                'households' => '47 戶',
                'floors' => '地上 14 層 / 地下 3 層',
                'layout_plan' => '3-4 房',
                'project_features' => [
                    ['label' => '基地位置', 'value' => '歌劇院特區核心'],
                    ['label' => '產品定位', 'value' => '雙併高坪數住宅'],
                    ['label' => '公設規劃', 'value' => '迎賓大廳、交誼廳、健身空間'],
                    ['label' => '生活尺度', 'value' => '景觀棟距與私領域尺度並重'],
                ],
                'is_featured' => true,
                'sort_order' => 1,
            ])
        );

        Project::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'green-courtyard'],
            array_merge($cyProjectDefaults, [
                'name' => '泉宇謙和',
                'status' => 'completed',
                'project_category' => 'villa',
                'location' => '彰化員林核心區',
                'address' => '彰化縣員林市三民東街 118 號旁',
                'launch_year' => 2024,
                'tagline' => '低密度街廓裡，以光與綠為核心的靜巷作品。',
                'summary' => '低密度街廓裡，以光與綠為核心的靜巷作品。',
                'description' => '<p>以舒適棟距與採光面配置，營造與街區節奏和諧共存的生活尺度。</p>',
                'area' => '42-58 坪',
                'households' => '17 戶',
                'floors' => '地上 4 層',
                'layout_plan' => '3 房 / 4 房',
                'project_features' => [
                    ['label' => '基地位置', 'value' => '員林核心生活圈'],
                    ['label' => '建築類型', 'value' => '低密度透天別墅'],
                    ['label' => '空間特色', 'value' => '靜巷棟距與自然採光'],
                    ['label' => '產品調性', 'value' => '慢居尺度與街廓和諧'],
                ],
                'is_featured' => true,
                'sort_order' => 2,
            ])
        );

        Project::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'river-atelier'],
            array_merge($cyProjectDefaults, [
                'name' => '泉宇川玥',
                'status' => 'planning',
                'project_category' => 'mixed_use',
                'location' => '彰化八卦山景觀軸',
                'address' => '彰化市東民街與中山路景觀軸帶',
                'launch_year' => 2027,
                'tagline' => '面山視野與會所機能整合的下一階段作品。',
                'summary' => '面山視野與會所機能整合的下一階段作品。',
                'description' => '<p>規劃中的新案，將作為品牌下一階段的代表作品。</p>',
                'area' => '38-52 坪',
                'households' => '52 戶',
                'floors' => '地上 15 層 / 地下 2 層',
                'layout_plan' => '2-4 房',
                'project_features' => [
                    ['label' => '基地位置', 'value' => '八卦山景觀軸'],
                    ['label' => '規劃概念', 'value' => '景觀會所與社區共享空間'],
                    ['label' => '產品範圍', 'value' => '中坪數景觀住宅'],
                    ['label' => '開發階段', 'value' => '規劃中'],
                ],
                'is_featured' => false,
                'sort_order' => 3,
            ])
        );

        // =====================================================================
        // 站 B News（泉宇建設 3 筆）
        // =====================================================================
        NewsArticle::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'new-launch'],
            [
                'title' => '泉宇雲鼎・新案鉅獻',
                'category' => '公司新訊',
                'summary' => '新案資訊正式公開，現場提供基地模型、建材展示與專人導覽。',
                'content' => '<p>新案資訊正式公開，現場提供基地模型、建材展示與專人導覽。</p>',
                'published_at' => now()->subDays(5),
                'is_published' => true,
                'sort_order' => 1,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        NewsArticle::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'community-walkthrough'],
            [
                'title' => '開箱家配日，美好的生活群像',
                'category' => '活動紀錄',
                'summary' => '邀請住戶與團隊一起走入未來日常，體驗空間細節與動線安排。',
                'content' => '<p>從公設導覽到家配提案，透過實際走訪與說明，讓客戶更具體理解空間配置與生活節奏。</p>',
                'published_at' => now()->subDays(18),
                'is_published' => true,
                'sort_order' => 2,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        NewsArticle::updateOrCreate(
            ['site_id' => $siteB->id, 'slug' => 'service-team-visit'],
            [
                'title' => '大樓體浴室廠房參訪',
                'category' => '品牌分享',
                'summary' => '透過產地走訪了解材料與設備供應流程。',
                'content' => '<p>透過產地走訪了解材料與設備供應流程，作為售後維護與產品把關的基礎。</p>',
                'published_at' => now()->subDays(40),
                'is_published' => true,
                'sort_order' => 3,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        // =====================================================================
        // 站 B Progress（綁泉宇雲鼎）
        // =====================================================================
        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteB->id, 'title' => '主體結構工程'],
            [
                'project_id' => $cyProject1->id,
                'summary' => '目前工程進度穩定推進中。',
                'content' => '<p>可於後台持續發布各樓層施工與驗收資訊。</p>',
                'progress_percent' => 78,
                'reported_at' => now()->subDay(),
                'is_published' => true,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteB->id, 'title' => '地下基礎工程'],
            [
                'project_id' => $cyProject1->id,
                'summary' => '基礎開挖與結構施作完成。',
                'content' => '<p>地下結構與防水節點逐步完成。</p>',
                'progress_percent' => 100,
                'reported_at' => now()->subMonths(3),
                'is_published' => true,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        ProgressUpdate::updateOrCreate(
            ['site_id' => $siteB->id, 'title' => '水電設備工程'],
            [
                'project_id' => $cyProject1->id,
                'summary' => '機電管線與弱電配置持續進行中。',
                'content' => '<p>各樓層機電配置與設備點位同步調整確認。</p>',
                'progress_percent' => 84,
                'reported_at' => now()->subDays(18),
                'is_published' => true,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        // =====================================================================
        // 站 B Contact（示範）
        // =====================================================================
        ContactMessage::updateOrCreate(
            ['site_id' => $siteB->id, 'name' => '王小明', 'phone' => '0912-345-678'],
            [
                'project_id' => $cyProject1->id,
                'inquiry_type' => '建案諮詢',
                'email' => 'buyer@example.com',
                'message' => '想了解目前可售樓層與付款方式。',
                'status' => 'new',
                'source_page' => '/contact',
                'assigned_to' => $superAdmin->name,
            ]
        );

        /* ===== Demo 圖片批次指定 ===== */
        $allProjects = Project::orderBy('id')->get();
        foreach ($allProjects as $i => $p) {
            $imgNum = ($i % 12) + 1;
            $p->update(['featured_image_path' => "project-images/building-{$imgNum}.jpg"]);
        }

        $allNews = NewsArticle::orderBy('id')->get();
        foreach ($allNews as $i => $n) {
            $imgNum = (($i + 4) % 12) + 1;
            $n->update(['featured_image_path' => "project-images/building-{$imgNum}.jpg"]);
        }

        /* 關於我們頁面 — 形象照 2×2 grid */
        Page::where('slug', 'about')->update([
            'gallery' => json_encode([
                'project-images/building-9.jpg',
                'project-images/building-10.jpg',
                'project-images/building-11.jpg',
                'project-images/building-12.jpg',
            ]),
            'cover_image_path' => 'project-images/building-1.jpg',
        ]);
    }

    /**
     * 建立頁面並回傳 slug => Page 的 Collection
     */
    private function seedPages(Site $site, User $author, array $pageRecords): \Illuminate\Support\Collection
    {
        return collect($pageRecords)->mapWithKeys(function (array $pageData) use ($author, $site) {
            $page = Page::updateOrCreate(
                ['site_id' => $site->id, 'slug' => $pageData['slug']],
                [
                    ...$pageData,
                    'is_published' => true,
                    'published_at' => now(),
                    'created_by' => $author->id,
                    'updated_by' => $author->id,
                ]
            );

            return [$pageData['slug'] => $page];
        });
    }
}
