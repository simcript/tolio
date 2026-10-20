<?php

namespace Src\Foundation;

use Pano\Foundation\Foundation;
use Pano\Kernel\ModuleResolverEnum;
use Src\Modules\Calma\CalmaModule;
use Src\Modules\Colorfy\ColorfyModule;
use Src\Modules\Default\DefaultModule;
use Src\Modules\Idate\IdateModule;
use Src\Modules\Kalaqpar\KalaqparModule;
use Src\Modules\Laralog\LaralogModule;
use Src\Modules\Musician\MusicianModule;
use Src\Modules\Onregex\OnregexModule;
use Src\Modules\RedJson\RedJsonModule;

class DefaultFoundation extends Foundation
{
    protected static array $modules = [
        '' => [
            'class' => DefaultModule::class,
            'resolver' => ModuleResolverEnum::PATH
        ],
        'calma' => CalmaModule::class,
        'colorfy' => ColorfyModule::class,
        'idate' => IdateModule::class,
        'laralog' => LaralogModule::class,
        'musician' => MusicianModule::class,
        'onregex' => OnregexModule::class,
        'red-json' => RedJsonModule::class,
        'kalaqpar' => KalaqparModule::class,
    ];

    public static function exception(): string
    {
        return DefaultException::class;
    }
}
