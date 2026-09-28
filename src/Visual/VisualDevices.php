<?php

namespace PrestaFlow\Library\Visual;

final class VisualDevices
{
    public const PRESETS = [
        'desktop' => [
            'width' => 1920, 'height' => 1080,
            'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 PrestaFlow',
        ],
        'tablet' => [
            'width' => 768, 'height' => 1024,
            'userAgent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 PrestaFlow',
        ],
        'mobile' => [
            'width' => 390, 'height' => 844,
            'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 PrestaFlow',
        ],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::PRESETS);
    }

    /** @return array{width:int,height:int,userAgent:string} */
    public static function get(string $device): array
    {
        if (!isset(self::PRESETS[$device])) {
            throw new \InvalidArgumentException("Unknown visual device « {$device} »");
        }

        return self::PRESETS[$device];
    }
}
