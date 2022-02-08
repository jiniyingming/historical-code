<?php

namespace Modules\Statistics\Services;


abstract class StatisticalConstMap
{
    public const STATISTICAL_EXPIRE = 86400 * 3;

    public static function getUvKey($platform, $day = 0): string
    {
        return sprintf('statistic:uv:%s:%s', $platform, date('Y-m-d', strtotime(sprintf('+%d day', $day))));
    }

    public static function getKeepKeyByPlatform($keep, $platform, $day = 0): string
    {
        return sprintf('statistic:keep:platform:%s:%s:%s', $keep, $platform, date('Y-m-d', strtotime(sprintf('+%d day', $day))));
    }

    public static function getKeepKeyByAll($keep, $day = 0): string
    {
        return sprintf('statistic:keep:all:%s:%s', $keep, date('Y-m-d', strtotime(sprintf('+%d day', $day))));
    }

    public static function getRegKey($platform, $day = 0): string
    {
        return sprintf('statistic:reg:%s:%s', $platform, date('Y-m-d', strtotime(sprintf('+%d day', $day))));
    }

    public static function getPlatformKey($day = 0): string
    {
        return sprintf('statistic:platform:%s', date('Y-m-d', strtotime(sprintf('+%d day', $day))));
    }

    public const PLATFORM_ALL_SIGN = -1;

    public const KEEP_MAP = [
        1 => '次日留存',
        7 => '次周留存',
        30 => '次月留存',
    ];

    private const PLATFORM_MAP = [
        1 => 'web',
        2 => '小程序',
        ...

    ];
}
