<?php

namespace Database\Seeders;

use App\Models\BuildingCase;
use Illuminate\Database\Seeder;

class SongzhuDunfuSeeder extends Seeder
{
    public function run(): void
    {
        $case = BuildingCase::create([
            'name' => '松竹敦富',
            'builder_name' => '松竹建設',
            'location' => '台中市北屯區',
            'area_range' => '28-42 坪',
            'target_audience' => 'first_buyer',
            'tone' => 'warm_family',
            'video_length_seconds' => 60,
            'character_nickname' => '松松',
            'character_dna' => 'a chubby and round Golden Retriever puppy with cute round face, solo single puppy character, NOT realistic photo of a dog, stylized cartoon proportions with oversized head and small body, wearing a soft blue knitted turtleneck sweater, with a cute small canvas backpack on its back, warm amber eyes (NOT blue), bright curious sparkling expression, honey-gold fluffy fur with cream-colored chest, in 3D Pixar movie style, fully cartoon stylized animation, soft volumetric lighting, cinematic depth of field, consistent character design, same exact dog character across all shots',
            'story_outline' => '松松（Golden Retriever 小狗）跟著主人找新家，從捷運出發探索松竹敦富的公設和生活機能，最後一家人溫馨入住。',
            'must_have' => '角色一致性、公設示意圖標註、合規標註',
            'taboos' => '不用「最」字、不點名具體地標距離、不寫中文在 AI 圖上',
        ]);

        $shots = [
            [
                'shot_id' => 'S01', 'shot_order' => 1, 'duration_seconds' => 4,
                'scene_description' => '松松爪戳地圖松竹站＋大字卡 Hook',
                'voiceover_text' => '咦…主人？你怎麼一直停在這站？',
                'subtitle' => '咦？這站？',
                'emotion' => '好奇興奮',
                'flux_prompt' => '[松松DNA v2], close-up of golden paw firmly tapping a blank circular station marker on a Taiwan MRT route map, station name will be added in post-production, puppy\'s curious head tilted slightly with bright eyes looking down at the map, sitting on a clean Taiwan MRT train seat, blurred Taichung Beitun street view through window in background, soft afternoon sunlight, tall vertical portrait composition, full body framed top to bottom, cinematic frame',
                'kling_prompt' => 'golden paw moves and taps the station marker on the map, puppy tilts head with curious expression, train rocks slightly, camera dollies in to map close-up',
            ],
            [
                'shot_id' => 'S02', 'shot_order' => 2, 'duration_seconds' => 4,
                'scene_description' => '松松從捷運站出口走出，抬頭望向建案',
                'voiceover_text' => '原來啊…我們要看新家！',
                'subtitle' => '鄰近捷運松竹站',
                'emotion' => '恍然大悟',
                'flux_prompt' => '[松松DNA v2], standing at the exit of a modern Taiwan MRT station, looking up with happy excited expression and slightly tilted head, ears perked up, map tucked into the side pocket of the backpack, green leafy street trees with soft sunlight filtering through, beautiful new modern apartment building visible in background slightly out of focus, warm golden hour lighting, tall vertical portrait composition, slight low-angle shot',
                'kling_prompt' => 'puppy slowly looks up with widening eyes, tail wagging gently in soft motion, ears perking up, camera tilts up following puppy\'s gaze, slow dolly in',
            ],
            [
                'shot_id' => 'S03', 'shot_order' => 3, 'duration_seconds' => 4,
                'scene_description' => '松松站在大廳中央，被雙電梯吸引',
                'voiceover_text' => '',
                'subtitle' => '寬敞迎賓 / 雙電梯　※公設示意圖',
                'emotion' => '驚嘆',
                'flux_prompt' => '[松松DNA v2], standing in a spacious elegant modern apartment lobby, looking up at two large polished elevator doors with awe-struck wide-eyed expression, ears perked up, backpack still on, warm ambient lighting, marble flooring with soft reflections, plants in corners, tall vertical portrait composition, slight low-angle shot',
                'kling_prompt' => 'elevator doors slide open silently revealing soft warm light, puppy\'s eyes widen, ears perk up, camera pulls back slightly to reveal full lobby',
            ],
            [
                'shot_id' => 'S04', 'shot_order' => 4, 'duration_seconds' => 6,
                'scene_description' => '松松躺在空中花園軟墊上，黃昏柔光',
                'voiceover_text' => '11 樓的空中花園…週末，剛好可以發呆。',
                'subtitle' => '11F 空中花園　※公設示意圖／以建照核准為準',
                'emotion' => '滿足',
                'flux_prompt' => '[松松DNA v2], lying happily on a soft outdoor cushion on a beautiful rooftop sky garden, warm sunset light bathing the scene, contented satisfied expression with closed mouth gentle smile, wooden deck floor, green plants and small trees around, distant Taichung city skyline silhouette out of focus, tall vertical portrait composition, cinematic warm tones, soft pastel sky',
                'kling_prompt' => 'puppy shifts comfortably on cushion, gentle warm sunset breeze ruffling fur, sun lowers slightly, camera slowly orbits to reveal city view',
            ],
            [
                'shot_id' => 'S05', 'shot_order' => 5, 'duration_seconds' => 5,
                'scene_description' => '松松坐在書房窗邊陪主人看書',
                'voiceover_text' => '主人在看書…我也來陪。',
                'subtitle' => '共讀書房　※公設示意圖',
                'emotion' => '寧靜',
                'flux_prompt' => '[松松DNA v2], sitting on a wooden chair by a large window in a cozy reading room, paws gently resting on an open picture book, focused gentle expression, a softly out-of-focus young woman\'s hand at the right edge of frame turning a page of another book, warm afternoon sunlight casting soft shadows, bookshelves filled with books in background, light oak floor, tall vertical portrait composition, peaceful atmosphere',
                'kling_prompt' => 'puppy\'s paw gently turns a page of the book, sunlight rays slowly shift, dust particles float in light beams, camera holds steady with subtle zoom in',
            ],
            [
                'shot_id' => 'S06', 'shot_order' => 6, 'duration_seconds' => 5,
                'scene_description' => '松松在健身球上萌反差',
                'voiceover_text' => '健身房嗎？呃，我可是運動健將。',
                'subtitle' => '健身房　※依管委會規約開放',
                'emotion' => '活潑萌反差',
                'flux_prompt' => '[松松DNA v2], playfully standing balanced on top of a large blue exercise ball in a modern bright gym, clearly four paws visible anatomically correct, closed mouth happy smile expression, paws spread for balance, treadmills and exercise equipment softly visible in background, large windows with natural light, white clean walls, tall vertical portrait composition, slight comedic angle',
                'kling_prompt' => 'puppy playfully bounces on exercise ball, ball slightly rolls and wobbles, puppy maintains balance with closed mouth happy smile, static camera',
            ],
            [
                'shot_id' => 'S07', 'shot_order' => 7, 'duration_seconds' => 6,
                'scene_description' => '客廳沙發，年輕夫妻＋松松，溫馨家庭',
                'voiceover_text' => "他們說啊…家，就是一家人在一起。\n我也覺得啊。",
                'subtitle' => '家，是一家人在一起的距離',
                'emotion' => '溫暖核心',
                'flux_prompt' => 'A 3D Pixar animated movie scene, fully cartoon stylized animation, NOT photorealistic. A young Asian cartoon couple in their early 30s rendered in Pixar 3D animation style, sitting on a beige sofa: husband on the left in casual sweater reading a book in soft profile, wife on the right in cream sweater leaning gently on his shoulder smiling. Between them, a chubby and round Golden Retriever puppy with cute round face, warm amber eyes (NOT blue), honey-gold fluffy fur with cream chest, wearing a soft blue knitted turtleneck sweater, snuggling content with eyes half-closed. All characters in consistent Pixar 3D style with soft volumetric lighting, warm evening lamp light, light oak wooden floor, beige walls, potted monstera plant in left corner, tall vertical portrait composition, intimate cozy mood',
                'kling_prompt' => 'puppy slowly closes eyes settling deeper into the couple\'s lap, husband gently turns a page in background, lamp light flickers softly, camera slowly dollies in',
            ],
            [
                'shot_id' => 'S08', 'shot_order' => 8, 'duration_seconds' => 5,
                'scene_description' => '松松坐陽台看夜景＋機能字卡',
                'voiceover_text' => '這附近啊…什麼都剛好。',
                'subtitle' => '這附近什麼都剛好｜機能位置示意',
                'emotion' => '安心',
                'flux_prompt' => '[松松DNA v2], sitting on a balcony railing of a modern apartment, looking out at a beautiful generic Taiwan night city view, peaceful eyes-closed expression with gentle smile, warm city lights twinkling below in soft bokeh, gentle night breeze ruffling fur, no specific landmarks visible, same beige interior wall visible through sliding glass door behind, light oak floor extending to balcony, tall vertical portrait composition, romantic night cinematic lighting',
                'kling_prompt' => 'gentle night breeze, puppy\'s golden fur ruffles softly, distant city lights twinkle in soft bokeh, camera slowly pans from puppy to city skyline view',
            ],
            [
                'shot_id' => 'S09', 'shot_order' => 9, 'duration_seconds' => 6,
                'scene_description' => '松松蓋推薦印章',
                'voiceover_text' => '松松…推薦！',
                'subtitle' => '松松推薦　※品牌虛構代言人',
                'emotion' => '堅定可愛',
                'flux_prompt' => '[松松DNA v2], close-up of golden paw firmly pressing down a small wooden stamp onto white paper, leaving a clean blank red circular ink stamp print on the paper, text will be added in post-production, determined satisfied expression visible above with closed mouth gentle smile, soft warm lighting, clean white tabletop background, tall vertical portrait composition, dynamic close-up',
                'kling_prompt' => 'puppy\'s paw lifts up holding wooden stamp, presses down firmly with satisfying motion, slight bounce on impact, blank red circular ink stamp appears clearly, close-up shot',
            ],
        ];

        foreach ($shots as $shot) {
            $case->shots()->create($shot);
        }
    }
}
