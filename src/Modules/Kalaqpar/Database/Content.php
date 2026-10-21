<?php
namespace Src\Modules\Kalaqpar\Database;

/** Original vector artwork. Public assets contain no category or future-round metadata. */
final class Content
{
    public static function catalogue(): array
    {
        $items = [];
        $add = function (string $name, int $flies, string $body) use (&$items) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" r="108" fill="#f4f1e9"/><ellipse cx="120" cy="204" rx="75" ry="8" fill="#dedbd3"/><g stroke-linecap="round" stroke-linejoin="round">' . $body . '</g></svg>';
            $items[] = ['name' => $name, 'can_fly' => $flies, 'asset' => substr(hash('sha256', $svg), 0, 24), 'svg' => $svg];
        };
        // Name, body, wing, bill length, neck length, distinguishing marks.
        $birds = [
            ['کلاغ','#252434','#3b3a4c',28,0,'<path d="M140 67l-13-22 21 7 10-12 8 26" fill="#252434"/>'],
            ['گنجشک','#9d7150','#624831',15,0,'<path d="M139 88l24-2M132 106l31-6" stroke="#302b29" stroke-width="8"/>'],
            ['کبوتر','#8595ac','#536b8c',16,0,'<path d="M136 113q11 14 28 4" stroke="#78ad95" stroke-width="13"/>'],
            ['پرستو','#243d57','#476885',15,0,'<path d="M77 164l-49 31 20-38-18-25 51 17" fill="#243d57"/><path d="M134 107l24 1" stroke="#b54a41" stroke-width="15"/>'],
            ['عقاب','#76533b','#473c32',24,5,'<circle cx="148" cy="83" r="25" fill="#f4e8d0"/><path d="M160 79l28 9-15 18" fill="#e4b840"/>'],
            ['شاهین','#697684','#405165',20,0,'<path d="M142 81l7 28" stroke="#273343" stroke-width="10"/>'],
            ['باز','#927455','#5e4b39',19,2,'<path d="M123 126l32 4m-38 12l40 4m-43 13l42 4" stroke="#e8dcc0" stroke-width="6"/>'],
            ['جغد','#ad8b67','#786249',10,0,'<circle cx="133" cy="82" r="17" fill="#f7deb2"/><circle cx="159" cy="82" r="17" fill="#f7deb2"/><circle cx="133" cy="82" r="6" fill="#242333"/><path d="M125 60l-7-20 23 15m14-1l21-13-5 25" fill="#786249"/>'],
            ['مرغ دریایی','#f9faf8','#adb8c2',30,4,'<path d="M63 144l40 21" stroke="#3e4651" stroke-width="13"/>'],
            ['اردک وحشی','#70877c','#8b6d54',29,4,'<circle cx="150" cy="83" r="24" fill="#3f8e76"/><path d="M132 106h29" stroke="#fff8df" stroke-width="7"/>'],
            ['غاز','#ecebe1','#c5c4b9',24,20,'<path d="M156 65l26 3" stroke="#eaa44a" stroke-width="10"/>'],
            ['قو','#fffdf7','#e5e6df',22,30,'<path d="M140 110q-31-37 0-55" fill="none" stroke="#fffdf7" stroke-width="23"/>'],
            ['فلامینگو','#ee9fa9','#d47b91',24,45,'<path d="M112 164l3 45m18-44l17 22-15 23" stroke="#c96c7e" stroke-width="5"/><path d="M163 55l18 10-7 14" fill="none" stroke="#303344" stroke-width="10"/>'],
            ['پلیکان','#ede8df','#c3c8c5',58,13,'<path d="M164 76l52 6-42 24z" fill="#edc17b"/>'],
            ['لک‌لک','#f6f5ee','#313944',51,29,'<path d="M110 169v42m24-42l2 42" stroke="#d7644c" stroke-width="5"/>'],
            ['درنا','#c1c8c8','#7e9096',40,34,'<circle cx="145" cy="43" r="10" fill="#c6514d"/><path d="M108 168v43m25-42v42" stroke="#46545e" stroke-width="5"/>'],
            ['هدهد','#dcaa70','#272c32',36,0,'<path d="M133 63l-18-25m28 22l-5-29m15 29l9-27" stroke="#d4854c" stroke-width="12"/><path d="M79 132l29 17m-34-4l30 18" stroke="#faf2df" stroke-width="9"/>'],
            ['دارکوب','#f5e9d2','#252c34',30,3,'<path d="M129 64l20-27 20 29z" fill="#d34e4d"/><path d="M139 89h21" stroke="#252c34" stroke-width="9"/>'],
            ['ماهی‌خورک','#31a5bd','#1b7595',41,0,'<path d="M138 108q21 36-5 57" stroke="#ea9c52" stroke-width="24"/>'],
            ['طوطی','#75b66c','#478b55',13,4,'<path d="M153 78q39-4 25 26l-18-3" fill="#d95e55"/><path d="M80 161l-11 48 23-39" fill="#56945c"/>'],
            ['مرغ عشق','#a7ca69','#65a262',10,0,'<circle cx="149" cy="83" r="23" fill="#f6db63"/><path d="M139 103h24" stroke="#2d516d" stroke-width="5"/><path d="M83 163l-5 46 23-43" fill="#4c9d80"/>'],
            ['کاکاتو','#f4f1df','#dddccf',15,2,'<path d="M136 64l-9-30m19 27l3-31m7 34l18-27" stroke="#e6bd46" stroke-width="9"/>'],
            ['توکان','#26313d','#3b4653',52,2,'<path d="M162 72q48-16 59 19l-56 8z" fill="#e8ad45"/><path d="M199 75l20 13-17 8" fill="#dd7052"/>'],
            ['مرغ مگس‌خوار','#54a58e','#378277',47,0,'<path d="M95 136L55 66l52 29 13 38" fill="#96c7bb"/><path d="M85 165l-19 32 33-24" fill="#2d716b"/>'],
            ['بلبل','#b7a286','#7f6f5e',16,0,'<path d="M79 160l-20 29 35-9" fill="#bb7753"/>'],
            ['سهره','#c1a36b','#2e3f43',14,0,'<path d="M88 130l15 26" stroke="#ebce57" stroke-width="15"/><path d="M145 68q-15 17-5 33" stroke="#d65247" stroke-width="10"/>'],
            ['سار','#454b57','#313a47',19,0,'<g fill="#d6c69e"><circle cx="117" cy="126" r="3"/><circle cx="107" cy="142" r="3"/><circle cx="131" cy="144" r="3"/><circle cx="122" cy="163" r="3"/></g>'],
            ['سینه‌سرخ','#9e9176','#6e7964',14,0,'<ellipse cx="147" cy="120" rx="25" ry="32" fill="#da825b"/>'],
            ['زاغی','#f3f1e6','#253b46',20,0,'<path d="M80 155l-48 45 53-20" fill="#305566"/><path d="M130 65q-26 18-2 46" stroke="#253b46" stroke-width="20"/>'],
            ['چکاوک','#c3a67d','#8d7553',17,0,'<path d="M132 65l11-25 15 25" fill="#a78962"/><path d="M132 121l-5 10m15-6l-4 11m-24 3l-2 9" stroke="#71573e" stroke-width="5"/>'],
        ];
        foreach ($birds as [$name,$color,$wing,$beak,$neck,$marks]) {
            $headY = 85 - $neck;
            $body = '<path d="M104 172l-5 26h-12m43-26l6 26h12" fill="none" stroke="#353545" stroke-width="5"/>';
            $body .= '<path d="M83 155l-45 27 45 3 23-19" fill="' . $color . '"/><ellipse cx="119" cy="139" rx="48" ry="46" fill="' . $color . '"/>';
            $body .= '<path d="M139 120Q119 ' . $headY . ' 148 ' . $headY . '" fill="none" stroke="' . $color . '" stroke-width="30"/><circle cx="149" cy="' . $headY . '" r="24" fill="' . $color . '"/>';
            $body .= '<ellipse cx="103" cy="145" rx="28" ry="31" fill="' . $wing . '"/><path d="M164 ' . ($headY-3) . 'l' . $beak . ' 12-' . $beak . ' 8z" fill="#efc453"/>';
            $body .= $marks . '<circle cx="154" cy="' . ($headY-4) . '" r="7" fill="white"/><circle cx="156" cy="' . ($headY-4) . '" r="3.6" fill="#252434"/>';
            $add($name, 1, $body);
        }
        $add('خفاش',1,'<path d="M109 108Q49 56 25 98q24-1 14 34 22-13 26 20 20-13 41 18h28q22-31 42-18 4-33 26-20-10-35 13-34-24-42-84 10" fill="#46435c"/><ellipse cx="120" cy="139" rx="19" ry="38" fill="#292639"/><path d="M104 109l1-29 15 13 15-13 1 29" fill="#292639"/><circle cx="112" cy="110" r="4" fill="#fff"/><circle cx="128" cy="110" r="4" fill="#fff"/>');
        $insects = [
            ['زنبور عسل','#e9bb47','bee'],['پروانه','#a88bd3','butterfly'],['سنجاقک','#51aaa9','dragonfly'],
            ['مگس','#6c8e89','fly'],['پشه','#8a725d','mosquito'],['شب‌پره','#ac9876','moth'],
            ['زنبور سرخ','#ce8154','wasp'],['کفشدوزک','#df655a','ladybug'],
        ];
        foreach ($insects as [$name,$color,$shape]) {
            $body = '<g stroke="#454252" stroke-width="4" fill="none"><path d="M111 123l-35-12m36 27l-38 9m37 7l-23 26m41-57l35-12m-35 27l38 9m-38 7l23 26M114 99l-13-23m25 23l13-23"/></g>';
            if (in_array($shape,['butterfly','moth'],true)) {
                $body .= '<path d="M118 128C78 51 25 70 52 132c-29 48 31 68 67 14M122 128c40-77 93-58 66 4 29 48-31 68-67 14" fill="'.$color.'" stroke="#655678" stroke-width="4"/><circle cx="73" cy="108" r="15" fill="#f3d780"/><circle cx="167" cy="108" r="15" fill="#f3d780"/><circle cx="81" cy="151" r="9" fill="#ece2f1"/><circle cx="159" cy="151" r="9" fill="#ece2f1"/>';
            } elseif ($shape === 'dragonfly') {
                $body .= '<g fill="#a2d8d6" stroke="#74b9bd" stroke-width="2"><ellipse cx="76" cy="107" rx="42" ry="14" transform="rotate(20 76 107)"/><ellipse cx="164" cy="107" rx="42" ry="14" transform="rotate(-20 164 107)"/><ellipse cx="78" cy="140" rx="40" ry="13"/><ellipse cx="162" cy="140" rx="40" ry="13"/></g><path d="M120 125v77" stroke="'.$color.'" stroke-width="12"/>';
            } elseif ($shape === 'ladybug') {
                $body .= '<circle cx="120" cy="140" r="43" fill="'.$color.'"/><path d="M120 100v82" stroke="#343142" stroke-width="5"/><g fill="#343142"><circle cx="100" cy="124" r="8"/><circle cx="97" cy="151" r="8"/><circle cx="139" cy="126" r="8"/><circle cx="140" cy="154" r="8"/></g>';
            } else {
                $body .= '<g fill="#d0e2e5" stroke="#a7c4ce" stroke-width="2"><ellipse cx="92" cy="108" rx="20" ry="37" transform="rotate(-35 92 108)"/><ellipse cx="148" cy="108" rx="20" ry="37" transform="rotate(35 148 108)"/></g><ellipse cx="120" cy="144" rx="'.($shape==='mosquito'?9:24).'" ry="37" fill="'.$color.'"/>';
                if (in_array($shape,['bee','wasp'],true)) { $body .= '<path d="M99 133h42m-42 20h42m-35 17h28" stroke="#51463a" stroke-width="9"/>'; }
                if ($shape==='mosquito') { $body .= '<path d="M120 96l-36-29" stroke="#665543" stroke-width="4"/>'; }
            }
            $body .= '<ellipse cx="120" cy="104" rx="'.($shape==='ladybug'?23:12).'" ry="14" fill="#454252"/><circle cx="115" cy="100" r="3" fill="white"/><circle cx="125" cy="100" r="3" fill="white"/>';
            $add($name,1,$body);
        }
        $animals = [
            ['گربه','#ba9479','cat'],['سگ','#b48762','dog'],['خرگوش','#e4ddce','rabbit'],['خرس','#8a6d55','bear'],
            ['روباه','#d38b55','fox'],['گرگ','#9babb1','wolf'],['شیر','#c7a263','lion'],['ببر','#de9f55','tiger'],
            ['گاو','#f1e5cc','cow'],['اسب','#9c7054','horse'],['گوسفند','#e8e5d9','sheep'],['بز','#b3aa94','goat'],
            ['فیل','#a8afb5','elephant'],['زرافه','#d6b570','giraffe'],['گورخر','#eee9dd','zebra'],['کرگدن','#9faaa9','rhino'],
            ['خوک','#e0a4a5','pig'],['پاندا','#f3f1e5','panda'],['کوالا','#aab4b4','koala'],['موش','#b0a4aa','mouse'],
            ['سنجاب','#bc855f','squirrel'],['جوجه‌تیغی','#a18c70','hedgehog'],['میمون','#a48261','monkey'],['شتر','#bba274','camel'],
            ['گوزن','#b6986d','deer'],['کانگورو','#b99472','kangaroo'],['پنگوئن','#354653','penguin'],['شترمرغ','#4b505a','ostrich'],
            ['لاک‌پشت','#7ca48b','turtle'],['قورباغه','#84b87a','frog'],['مار','#86a77d','snake'],['کروکودیل','#7b9876','crocodile'],
            ['ماهی','#e5a16a','fish'],['کوسه','#8da9b5','shark'],['نهنگ','#668da9','whale'],['دلفین','#91b2c5','dolphin'],
            ['هشت‌پا','#bd96b5','octopus'],['خرچنگ','#d48769','crab'],['حلزون','#a59b71','snail'],['ستاره دریایی','#dcab7c','starfish'],
        ];
        foreach ($animals as [$name,$c,$type]) {
            $body = self::animal($c,$type);
            $add($name,0,$body);
        }
        $objects = [
            ['سنگ','<path d="M42 165l21-66 54-35 64 20 31 89-29 27H68z" fill="#8c8c9c"/><path d="M63 99l54-35 64 20-43 47-51 12z" fill="#adadba"/><path d="M138 131l43-47 31 89-29 27-43-14z" fill="#737383"/>'],
            ['سیب','<path d="M121 91C47 60 46 138 66 173c30 51 45 10 57 16 39 32 68-18 67-63-1-39-37-50-69-35" fill="#d9746c"/><path d="M121 93l8-41" stroke="#85684d" stroke-width="9"/><path d="M132 74q34 7 42-27-34-3-42 27" fill="#87a977"/>'],
            ['گلابی','<path d="M120 66c-33 0-16 38-42 68-41 48 0 69 42 69s83-21 42-69c-26-30-9-68-42-68" fill="#c1c975"/><path d="M120 65l6-25" stroke="#896a51" stroke-width="8"/>'],
            ['موز','<path d="M58 72q20 91 121 51-3 73-83 59C55 174 40 118 58 72" fill="#efcd66" stroke="#bda24f" stroke-width="4"/><path d="M58 72l-5-17m126 68l10-8" stroke="#7c7852" stroke-width="9"/>'],
            ['هندوانه','<path d="M34 103h172q-4 97-86 97T34 103" fill="#6b9e7c"/><path d="M45 105h150q-11 79-75 79T45 105" fill="#f0cd9e"/><path d="M51 105h138q-11 69-69 69T51 105" fill="#da827f"/><path d="M87 128l4 9m34-15v10m24-2l-4 8m-30 12v8" stroke="#4b4952" stroke-width="5"/>'],
            ['پرتقال','<circle cx="120" cy="135" r="65" fill="#e7aa5d"/><path d="M120 70q4-37 42-32-2 34-42 32" fill="#81a17d"/><g fill="#d5914c"><circle cx="89" cy="120" r="4"/><circle cx="140" cy="154" r="4"/><circle cx="110" cy="173" r="4"/></g>'],
            ['توت‌فرنگی','<path d="M59 97q61-31 122 0-4 68-61 108-57-40-61-108" fill="#d67379"/><path d="M120 98l-47-29 34 3 12-26 15 28 32-5z" fill="#77a387"/><path d="M84 116l4 10m31-6v10m33-13l-3 10m-44 27l3 10m24-10l-2 10" stroke="#f2d9ad" stroke-width="5"/>'],
            ['آناناس','<ellipse cx="120" cy="143" rx="51" ry="64" fill="#d7bc70"/><path d="M120 87L76 40l38 21 6-40 10 40 35-21-28 50" fill="#76a083"/><path d="M84 112l71 67m-79-45l63 62m-34-105l60 64m-10-44l-70 67m79-45l-63 62m34-105l-60 64" stroke="#b49b58" stroke-width="3"/>'],
            ['هویج','<path d="M82 86q46-38 76 8L91 208z" fill="#df9a60"/><path d="M119 86L99 40m25 40l12-49m-5 54l40-29" stroke="#78a37e" stroke-width="13"/><path d="M105 115l25 9m-29 23l15 6" stroke="#bd7f4c" stroke-width="5"/>'],
            ['کدو تنبل','<ellipse cx="120" cy="141" rx="80" ry="61" fill="#dba15f"/><ellipse cx="120" cy="141" rx="49" ry="61" fill="#eab772"/><ellipse cx="120" cy="141" rx="20" ry="61" fill="#dfaa65"/><path d="M121 81l-6-34 21-6" fill="none" stroke="#788c61" stroke-width="12"/>'],
            ['قارچ','<path d="M98 126l-7 72h58l-7-72" fill="#dfd2b5"/><path d="M36 131q84-142 168 0z" fill="#c7807b"/><circle cx="89" cy="99" r="12" fill="#f5e5cb"/><circle cx="139" cy="82" r="13" fill="#f5e5cb"/><circle cx="160" cy="114" r="10" fill="#f5e5cb"/>'],
            ['درخت','<path d="M107 132h26v77h-26z" fill="#9b7a56"/><g fill="#84a58a"><circle cx="120" cy="83" r="43"/><circle cx="79" cy="116" r="39"/><circle cx="158" cy="115" r="39"/><circle cx="119" cy="134" r="38"/></g>'],
            ['گل آفتابگردان','<path d="M120 111v97m0-32q-44 5-44-32 33-3 44 32m0 5q44 5 44-32-33-3-44 32" stroke="#7f9f73" stroke-width="8" fill="#9db48a"/><g fill="#eac66b"><ellipse cx="120" cy="87" rx="59" ry="23"/><ellipse cx="120" cy="87" rx="23" ry="59"/><ellipse cx="120" cy="87" rx="57" ry="22" transform="rotate(45 120 87)"/><ellipse cx="120" cy="87" rx="57" ry="22" transform="rotate(-45 120 87)"/></g><circle cx="120" cy="87" r="26" fill="#896b4f"/>'],
            ['کاکتوس','<path d="M104 174V69q16-30 32 0v68h22V99q13-23 24 0v45q0 16-46 16v23H104V147H74q-18 0-18-18V98q12-21 23 0v25h25" fill="#83a18a"/><path d="M80 177h80l-11 33H91z" fill="#c99b83"/>'],
            ['توپ','<circle cx="120" cy="128" r="73" fill="#eee6ce" stroke="#777c88" stroke-width="4"/><path d="M103 100h34l12 31-29 19-29-19zM52 104l28-16 13-27m56 4l12 28 27 12m-23 67l-28-6-17 29m-53-35l28 7 8 25" fill="#62697b" stroke="#62697b" stroke-width="5"/>'],
            ['کتاب','<path d="M65 48h109v153H65q-23-1-23-23V69q0-21 23-21" fill="#8c83bc"/><path d="M66 175h108v26H65q-22-1-22-14 0-12 23-12" fill="#eee8da"/><path d="M73 78h72m-72 21h59m-59 21h65" stroke="#eee8da" stroke-width="6"/>'],
            ['مداد','<path d="M58 173L155 57l30 25-98 116-38 10z" fill="#e5c264"/><path d="M58 173l29 25-38 10z" fill="#d5b89a"/><path d="M49 208l5-21 14 12z" fill="#595867"/><path d="M155 57l10-13q6-8 15 0l14 12q8 7 1 15l-10 11" fill="#c8939e"/><path d="M67 180l101-117" stroke="#c9a654" stroke-width="5"/>'],
            ['فنجان','<path d="M69 84h94v83q-2 36-47 36-45 0-47-36z" fill="#aaa1c9"/><path d="M163 99q49-6 39 36-8 27-39 17" fill="none" stroke="#aaa1c9" stroke-width="13"/><path d="M93 59q-8-13 0-24m29 24q-8-13 0-24" fill="none" stroke="#c9c3d2" stroke-width="7"/>'],
            ['قاشق','<ellipse cx="121" cy="76" rx="32" ry="43" fill="#a7b6c0"/><path d="M120 114l-7 90q8 14 17 0l-7-90" fill="#a7b6c0"/>'],
            ['چنگال','<path d="M85 40v54q0 25 35 26v82m-13-162v60m25-60v60m23-60v54q0 25-35 26" fill="none" stroke="#a5b4bf" stroke-width="12"/>'],
            ['قیچی','<path d="M98 145L171 40q9 35-29 91l-5 20M138 145L65 40q-9 35 29 91l5 20" fill="#a5b2ba"/><g fill="none" stroke="#9c8db9" stroke-width="13"><ellipse cx="83" cy="169" rx="25" ry="30"/><ellipse cx="153" cy="169" rx="25" ry="30"/></g><circle cx="119" cy="120" r="7" fill="#696a75"/>'],
            ['کلید','<circle cx="92" cy="88" r="42" fill="none" stroke="#d7b878" stroke-width="18"/><path d="M121 119l67 70m-20-19l-18 18m4-33l-18 18" stroke="#d7b878" stroke-width="17"/>'],
            ['قفل','<path d="M79 107V82q0-49 41-49t41 49v25" fill="none" stroke="#989ead" stroke-width="14"/><rect x="61" y="104" width="118" height="99" rx="17" fill="#dfbe75"/><circle cx="120" cy="145" r="12" fill="#75624b"/><path d="M120 146v23" stroke="#75624b" stroke-width="10"/>'],
            ['صندلی','<path d="M70 130V54h100v80m-109 6h119v28H61zM71 168v40m99-40v40" fill="#b99573" stroke="#8f725b" stroke-width="8"/><path d="M86 74v39m22-39v39m23-39v39m22-39v39" stroke="#e2c4a0" stroke-width="8"/>'],
            ['میز','<path d="M42 93h156v31H42zM60 124v84m120-84v84" fill="#ba9471" stroke="#8f765e" stroke-width="13"/>'],
            ['ساعت','<circle cx="120" cy="124" r="79" fill="#a39ac1"/><circle cx="120" cy="124" r="64" fill="#fff9e9"/><path d="M120 78v47l35 22" fill="none" stroke="#5c596a" stroke-width="8"/><circle cx="120" cy="124" r="7" fill="#5c596a"/><path d="M120 66v8m58 50h-8m-50 57v-8m-58-49h8" stroke="#a39ac1" stroke-width="5"/>'],
            ['جوراب','<path d="M103 42h62v112q0 28-34 38l-56 17q-28 5-31-19-2-17 16-27l43-26z" fill="#bc9ebd"/><path d="M103 58h62m-62 18h62" stroke="#eee3d9" stroke-width="10"/><path d="M45 183q17-10 31 20m56-59q21-6 32 8" fill="none" stroke="#8e7ba6" stroke-width="17"/>'],
            ['کفش','<path d="M65 98l34 17 32-28 32 70 39 12q17 30-18 33H51q-24-4-13-29z" fill="#91a5b6"/><path d="M39 189h160M114 117l24-8m-17 25l24-8m-16 25l24-8" stroke="#e8e4d8" stroke-width="9"/>'],
            ['کلاه','<path d="M73 143q-9-93 47-93t47 93" fill="#b3a4c4"/><ellipse cx="120" cy="158" rx="87" ry="23" fill="#9c89b4"/><path d="M75 126h90v22H75z" fill="#e2c98c"/>'],
            ['چتر بسته','<path d="M117 34h6l38 125H80z" fill="#9c91bd"/><path d="M120 157v32q0 28-25 13" fill="none" stroke="#635a78" stroke-width="9"/><path d="M102 133h34" stroke="#e4c68a" stroke-width="8"/>'],
            ['بطری','<path d="M99 44h42v43l24 32v79H75v-79l24-32z" fill="#a0c2bb"/><path d="M97 39h46v17H97" fill="#8c87a5"/><path d="M76 132h88v37H76z" fill="#eee5c9"/>'],
            ['شمع','<rect x="88" y="98" width="64" height="103" rx="8" fill="#e2bb9d"/><path d="M120 98v-20" stroke="#63585a" stroke-width="5"/><path d="M120 38q-38 53 0 48 35-5 0-48" fill="#e7c45d"/><path d="M103 100v30m18-29v17" stroke="#f6dcc1" stroke-width="9"/>'],
        ];
        foreach ($objects as [$name,$body]) { $add($name,0,$body); }
        return $items;
    }
    private static function animal(string $c, string $t): string
    {
        $eyes = '<circle cx="148" cy="104" r="5" fill="#343342"/>';
        $base = '<ellipse cx="113" cy="146" rx="57" ry="38" fill="'.$c.'"/><path d="M79 163v37m31-29v29m32-30v30" stroke="'.$c.'" stroke-width="14"/><circle cx="154" cy="110" r="30" fill="'.$c.'"/>';
        $extra = match ($t) {
            'cat' => '<path d="M130 91l1-26 24 22 21-23 6 35M66 141q-40-54-31-8" fill="'.$c.'" stroke="'.$c.'" stroke-width="9"/><path d="M173 114l27-5m-27 13l27 6" stroke="#71605d" stroke-width="3"/>',
            'dog' => '<ellipse cx="131" cy="108" rx="13" ry="33" fill="#79634e"/><ellipse cx="178" cy="126" rx="20" ry="13" fill="#d4b493"/><path d="M60 138l-19-25" stroke="'.$c.'" stroke-width="13"/>',
            'rabbit' => '<ellipse cx="140" cy="64" rx="12" ry="38" fill="'.$c.'"/><ellipse cx="169" cy="64" rx="12" ry="38" fill="'.$c.'"/><path d="M140 39v42m29-42v42" stroke="#d5b5b8" stroke-width="7"/><circle cx="56" cy="147" r="17" fill="#f9f5e9"/>',
            'bear' => '<circle cx="135" cy="84" r="13" fill="'.$c.'"/><circle cx="175" cy="86" r="13" fill="'.$c.'"/><ellipse cx="169" cy="121" rx="16" ry="13" fill="#c2a589"/>',
            'fox' => '<path d="M129 91l-5-31 30 23 23-25 7 42" fill="'.$c.'"/><path d="M158 110l39 13-31 17" fill="#f6e3c6"/><path d="M70 150Q16 94 31 164q11 22 43 9" fill="'.$c.'"/><path d="M27 131q-5 28 14 40l-8-46" fill="#f8e8d2"/>',
            'wolf' => '<path d="M131 91l-5-28 28 23 22-24 7 33m-10 17l34 12-36 18" fill="'.$c.'"/><path d="M66 146l-35 32 40-5" fill="'.$c.'"/>',
            'lion' => '<circle cx="154" cy="109" r="43" fill="#a47c4a"/><circle cx="154" cy="110" r="28" fill="'.$c.'"/><path d="M59 147q-38-6-26 40" fill="none" stroke="#b18a56" stroke-width="8"/><circle cx="33" cy="190" r="10" fill="#a47c4a"/>',
            'tiger' => '<path d="M70 120l14 25m12-34l9 30m13-25l8 28m10-53l9 14m22-23l-4 17" stroke="#5a5143" stroke-width="9"/><path d="M58 145q-39-28-25 25" fill="none" stroke="'.$c.'" stroke-width="11"/>',
            'cow' => '<path d="M133 88l-17-20m53 16l15-20" stroke="#b4a179" stroke-width="10"/><ellipse cx="174" cy="127" rx="25" ry="17" fill="#cba89d"/><path d="M86 118q-38 23-5 38 39-13 5-38m39 1q-22 10-8 25 25-6 8-25" fill="#5f6467"/>',
            'horse' => '<path d="M136 91l-8-27 17 13 13-17 12 29m-24 13l33 8 12 34-29 8-28-22" fill="'.$c.'"/><path d="M132 85q-23 3-29 37" fill="none" stroke="#655345" stroke-width="15"/><path d="M57 137l-25 49" stroke="#655345" stroke-width="14"/>',
            'sheep' => '<g fill="#f0ece0"><circle cx="73" cy="126" r="23"/><circle cx="103" cy="118" r="25"/><circle cx="129" cy="127" r="25"/><circle cx="78" cy="153" r="23"/><circle cx="111" cy="155" r="27"/></g><ellipse cx="158" cy="115" rx="22" ry="32" fill="#8b8375"/>',
            'goat' => '<path d="M139 88q-15-48 3-48m21 47q12-44-4-44" fill="none" stroke="#7c7868" stroke-width="9"/><path d="M159 137l-12 30 26-20" fill="#d7d1b9"/>',
            'elephant' => '<circle cx="137" cy="115" r="36" fill="#919ba2"/><path d="M170 108q33 66-3 72" fill="none" stroke="'.$c.'" stroke-width="19"/><path d="M165 137l15 10" stroke="#eee5c9" stroke-width="7"/>',
            'giraffe' => '<path d="M137 144l-8-83 34-6-4 85" fill="'.$c.'"/><ellipse cx="161" cy="63" rx="28" ry="17" fill="'.$c.'"/><path d="M146 51V34m19 15V33" stroke="#997a50" stroke-width="7"/><g fill="#9b7b50"><circle cx="91" cy="138" r="12"/><circle cx="119" cy="155" r="10"/><circle cx="147" cy="94" r="9"/><circle cx="145" cy="124" r="8"/></g>',
            'zebra' => '<path d="M76 121l7 39m14-46l10 60m15-56l13 49m10-78l-3 31m20-36l-2 25" stroke="#4a4b52" stroke-width="9"/><path d="M57 136l-20 44" stroke="#4a4b52" stroke-width="12"/>',
            'rhino' => '<path d="M172 114l18-33 10 42" fill="#e4dcc1"/><ellipse cx="180" cy="126" rx="28" ry="19" fill="'.$c.'"/>',
            'pig' => '<path d="M131 89l-6-21 28 17m15 0l20-17-4 29" fill="'.$c.'"/><ellipse cx="176" cy="120" rx="22" ry="17" fill="#c5868e"/><circle cx="171" cy="120" r="4" fill="#795e6a"/><circle cx="184" cy="120" r="4" fill="#795e6a"/><path d="M57 133q-28-31-24-2 3 11 12-3" fill="none" stroke="'.$c.'" stroke-width="6"/>',
            'panda' => '<g fill="#3e414a"><circle cx="133" cy="84" r="13"/><circle cx="174" cy="85" r="13"/><ellipse cx="140" cy="106" rx="12" ry="16"/><ellipse cx="166" cy="106" rx="12" ry="16"/><ellipse cx="90" cy="145" rx="20" ry="36"/></g><circle cx="166" cy="106" r="4" fill="white"/>',
            'koala' => '<circle cx="124" cy="90" r="25" fill="'.$c.'"/><circle cx="180" cy="90" r="25" fill="'.$c.'"/><circle cx="124" cy="90" r="15" fill="#d1d0c9"/><ellipse cx="157" cy="118" rx="12" ry="19" fill="#555760"/>',
            'mouse' => '<circle cx="135" cy="82" r="22" fill="'.$c.'"/><circle cx="174" cy="82" r="22" fill="'.$c.'"/><circle cx="135" cy="82" r="14" fill="#d5b4ba"/><circle cx="174" cy="82" r="14" fill="#d5b4ba"/><path d="M57 149q-49-28-33 28" stroke="#b99ca2" stroke-width="7" fill="none"/>',
            'squirrel' => '<path d="M73 147Q4 60 46 57q48-3 39 80" fill="#b4805d"/><ellipse cx="117" cy="152" rx="24" ry="28" fill="#e0c4a4"/><path d="M136 88l-3-24 18 18" fill="'.$c.'"/>',
            'hedgehog' => '<path d="M39 159l-7-30 21 4-1-26 25 8 7-27 22 15 20-18 9 28 22-6-1 30-13 24z" fill="#746554"/><ellipse cx="157" cy="148" rx="39" ry="25" fill="#c6ad8b"/>',
            'monkey' => '<circle cx="129" cy="110" r="16" fill="#d1ad8e"/><circle cx="181" cy="110" r="16" fill="#d1ad8e"/><ellipse cx="156" cy="119" rx="23" ry="21" fill="#d1ad8e"/><path d="M60 139q-51-49-34 18 12 32 20 1" fill="none" stroke="'.$c.'" stroke-width="10"/>',
            'camel' => '<path d="M65 134q6-69 34-11 24-78 45 2" fill="'.$c.'"/><path d="M147 129V83l18-8 13 57" fill="'.$c.'"/><path d="M78 173v32m57-33v33" stroke="'.$c.'" stroke-width="10"/>',
            'deer' => '<path d="M137 88l-11-43m6 21l-17-7m20 16l14-18m15 31l9-43m-5 21l17-7m-20 16l-12-18" fill="none" stroke="#9f8766" stroke-width="7"/>',
            'kangaroo' => '<path d="M141 91l-12-39 13-5 15 39m6 0l10-39 13 5-10 40M94 176l-54 26 66-8" fill="'.$c.'"/><path d="M114 145q-19 56 26 47" fill="none" stroke="#dfc7a3" stroke-width="17"/>',
            'penguin' => '<ellipse cx="120" cy="130" rx="49" ry="72" fill="'.$c.'"/><ellipse cx="120" cy="151" rx="32" ry="43" fill="#f7eedc"/><path d="M81 199h29m20 0h29M111 108l26 8-17 12" stroke="#e5b463" fill="#e5b463" stroke-width="8"/><path d="M76 122l-18 41m105-41l18 41" stroke="'.$c.'" stroke-width="16"/>',
            'ostrich' => '<ellipse cx="108" cy="147" rx="52" ry="36" fill="#4b505a"/><ellipse cx="96" cy="149" rx="28" ry="23" fill="#ded9cd"/><path d="M134 141l8-82" stroke="#bfafa3" stroke-width="16"/><circle cx="147" cy="54" r="20" fill="#bfafa3"/><path d="M164 51l22 8-22 5" fill="#d9bd7e"/><path d="M98 173l-1 38m30-38l3 38" stroke="#bfafa3" stroke-width="7"/>',
            default => '',
        };
        if (in_array($t,['penguin','ostrich'],true)) { return $extra . ($t==='penguin'?'<circle cx="105" cy="94" r="5" fill="white"/><circle cx="133" cy="94" r="5" fill="white"/>':'<circle cx="152" cy="49" r="4" fill="#343342"/>'); }
        if ($extra !== '') { return $base . $extra . $eyes; }
        return match ($t) {
            'turtle' => '<path d="M68 156l-17 31m44-25l-4 28m61-33l16 30" stroke="#809c78" stroke-width="15"/><circle cx="182" cy="138" r="24" fill="#91b58b"/><path d="M48 160q0-115 114 0z" fill="'.$c.'" stroke="#537b67" stroke-width="5"/><path d="M108 99l-21 29 17 28 36-9-4-28z" fill="#a8c198" stroke="#537b67" stroke-width="4"/>' . $eyes,
            'frog' => '<ellipse cx="120" cy="149" rx="55" ry="45" fill="'.$c.'"/><ellipse cx="120" cy="164" rx="30" ry="25" fill="#c1d5a3"/><circle cx="91" cy="107" r="23" fill="'.$c.'"/><circle cx="149" cy="107" r="23" fill="'.$c.'"/><g fill="#fff7e4"><circle cx="91" cy="105" r="13"/><circle cx="149" cy="105" r="13"/></g><path d="M72 150l-30 37 39 8m87-45l30 37-39 8" fill="none" stroke="'.$c.'" stroke-width="15"/><circle cx="91" cy="105" r="6" fill="#343342"/><circle cx="149" cy="105" r="6" fill="#343342"/><path d="M102 137q18 13 36 0" fill="none" stroke="#597d59" stroke-width="4"/>',
            'snake' => '<path d="M55 180q-22-44 34-42t55-38q0-40 39-24" fill="none" stroke="'.$c.'" stroke-width="27"/><ellipse cx="182" cy="78" rx="28" ry="19" fill="'.$c.'"/><circle cx="190" cy="72" r="4" fill="#353543"/><path d="M206 80l14 3m-6-1l5-6" stroke="#cb8482" stroke-width="4"/><path d="M57 162l15 12m41-36l-5 13m45-41l-12-3" stroke="#b9caa0" stroke-width="8"/>',
            'crocodile' => '<path d="M36 151l-16-33 48 14q40-47 93-1l53 4-8 31H51z" fill="'.$c.'"/><path d="M66 138l11-25 14 15 12-27 13 24 14-20 10 23" fill="#5e7b5f"/><path d="M155 148h49m-131 20l-8 23m71-24l8 24" stroke="#516b55" stroke-width="6"/><circle cx="169" cy="133" r="5" fill="#343342"/>',
            'fish' => '<path d="M76 116l-44-32v83l45-26" fill="#d48b56"/><ellipse cx="132" cy="128" rx="65" ry="42" fill="'.$c.'"/><path d="M113 91l30-27 10 30m-40 70l30 23 6-23" fill="#ca875b"/><circle cx="172" cy="117" r="7" fill="#343342"/><path d="M141 104q-20 24 0 47" stroke="#f0ca99" fill="none" stroke-width="6"/>',
            'shark' => '<path d="M34 143l-13-42 37 22q38-34 117 1l39 24-48 24H63l-40 21 15-37" fill="'.$c.'"/><path d="M109 123l22-61 21 64" fill="#72909f"/><path d="M159 154l37-3-15 17" fill="#f3eee1"/><path d="M174 153l4 8 6-8" fill="#536c7c"/><circle cx="179" cy="135" r="5" fill="#343342"/>',
            'whale' => '<path d="M54 132L28 93l37 10 26-16-5 43q79-72 119 7 24 61-67 61-64 0-84-66" fill="'.$c.'"/><path d="M98 168q40 48 81 10" fill="#c1d1d9"/><circle cx="181" cy="149" r="6" fill="#343342"/><path d="M141 83V54m0 9l-17-13m17 13l17-13" fill="none" stroke="#b1cdd6" stroke-width="7"/>',
            'dolphin' => '<path d="M43 160l-19-24 30 6q76-100 132-27l33 20-41 1q-29 67-107 37l-30 15z" fill="'.$c.'"/><path d="M111 116l21-49 19 49m-39 38l15 32 26-27" fill="#7599ad"/><circle cx="175" cy="119" r="5" fill="#343342"/>',
            'octopus' => '<g fill="none" stroke="'.$c.'" stroke-width="17"><path d="M81 143q-4 60-37 36m55-34q-18 74-36 47m53-44q-9 72-21 53m32-53q9 72 21 53m-4-56q18 74 36 47m-18-49q4 60 37 36"/></g><ellipse cx="120" cy="112" rx="50" ry="58" fill="'.$c.'"/><circle cx="102" cy="115" r="6" fill="#343342"/><circle cx="138" cy="115" r="6" fill="#343342"/>',
            'crab' => '<g fill="none" stroke="'.$c.'" stroke-width="9"><path d="M80 139l-31-20-15 11m46 24l-34 3-12 19m60-7l-35 16m101-46l31-20 15 11m-46 24l34 3 12 19m-60-7l35 16M82 131L59 90m99 41l23-41"/></g><ellipse cx="120" cy="144" rx="48" ry="32" fill="'.$c.'"/><path d="M58 102Q17 83 48 56l8 27 19-22q13 31-17 41m124 0q41-19 10-46l-8 27-19-22q-13 31 17 41" fill="'.$c.'"/><path d="M102 122v-18m36 18v-18" stroke="'.$c.'" stroke-width="7"/><circle cx="102" cy="102" r="7" fill="#343342"/><circle cx="138" cy="102" r="7" fill="#343342"/>',
            'snail' => '<path d="M40 184q49-28 114-17l26-44q33 47 9 69H40" fill="#aeb58d"/><circle cx="107" cy="133" r="50" fill="#b79672"/><path d="M89 145q-23-37 18-38 39 4 19 36-16 24-32 2 0-19 13-13" fill="none" stroke="#8b7157" stroke-width="6"/><path d="M180 133l-8-27m17 30l15-25" stroke="#aeb58d" stroke-width="7"/><circle cx="172" cy="104" r="5" fill="#343342"/><circle cx="204" cy="108" r="5" fill="#343342"/>',
            'starfish' => '<path d="M120 39l25 60 65 4-50 43 15 64-55-35-55 35 15-64-50-43 65-4z" fill="'.$c.'" stroke="#be8968" stroke-width="4"/><g fill="#f1cba4"><circle cx="120" cy="83" r="5"/><circle cx="75" cy="119" r="5"/><circle cx="163" cy="119" r="5"/><circle cx="92" cy="161" r="5"/><circle cx="146" cy="161" r="5"/></g>',
            default => throw new \LogicException('Missing artwork: '.$t),
        };
    }
}
