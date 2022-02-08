<?php

namespace Modules\Statistics\Services;

use App\Models\UserModel;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

abstract class StatisticalService
{

    /**
     * @param Request $request
     * @return void
     */
    public static function dataInit(Request $request): void
    {

        $info = $request->input('userInfo');

        if (!self::getUvStatus($info['person_id'], StatisticalConstMap::PLATFORM_ALL_SIGN)) {
            self::statisticUv($info['person_id'], StatisticalConstMap::PLATFORM_ALL_SIGN);
            self::statisticKeep($info['person_id'], $info['platform'], false);
        }
        if (!self::getUvStatus($info['person_id'], $info['platform'])) {
            self::statisticUv($info['person_id'], $info['platform']);
            self::statisticKeep($info['person_id'], $info['platform'], true);
        }


        self::logPlatform($info['platform']);
    }

    private static function logPlatform($platform): void
    {
        $key = StatisticalConstMap::getPlatformKey();
        if (!Redis::sismember($key, $platform)) {
            Redis::sAdd($key, $platform);
            Redis::expire($key, StatisticalConstMap::STATISTICAL_EXPIRE);
        }
    }


    /**
     * @param $mainUid
     * @param $platform
     * @return void
     * Uv统计
     */
    private static function statisticUv($mainUid, $platform): void
    {
        $key = StatisticalConstMap::getUvKey($platform);
        if (!self::getUvStatus($mainUid, $platform)) {
            Redis::sAdd($key, $mainUid);
            Redis::expire($key, StatisticalConstMap::STATISTICAL_EXPIRE);
        }
    }

    private static function getUvStatus($mainUid, $platform)
    {
        $key = StatisticalConstMap::getUvKey($platform);

        return Redis::sismember($key, $mainUid);
    }


    /**
     * 留存数据
     */
    private static function statisticKeep($mainUid, $platform, bool $isPlatform = false): void
    {

        $user = UserModel::query()->where('id', $mainUid)->select('created_at')->first();
        if (!$user) {
            return;
        }
        foreach (StatisticalConstMap::KEEP_MAP as $keep => $_) {//次,7,30日留存
            $time = self::getRangeTime(-$keep);
            if ($user['created_at'] >= $time['start'] && $user['created_at'] < $time['end']) {
                if ($isPlatform) {
                    $key = StatisticalConstMap::getKeepKeyByPlatform($keep, $platform);
                } else {
                    $key = StatisticalConstMap::getKeepKeyByAll($keep);
                }
                Redis::incr($key);
                Redis::expire($key, StatisticalConstMap::STATISTICAL_EXPIRE);
            }
        }
    }

    private static function getRangeTime($day = -1): array
    {
        $time = strtotime(date('Y-m-d'));
        return [
            'start' => ($time + $day * 86400),
            'end' => $time
        ];
    }

    /**
     * @param $platform
     * @param $mainUid
     * @return void
     * 注册统计
     */
    public static function statisticRegister($platform, $mainUid): void
    {
        $key = StatisticalConstMap::getRegKey($platform);
        if (!Redis::sismember($key, $mainUid)) {
            Redis::sAdd($key, $mainUid);
            Redis::expire($key, StatisticalConstMap::STATISTICAL_EXPIRE);
        }
        $key = StatisticalConstMap::getRegKey(StatisticalConstMap::PLATFORM_ALL_SIGN);
        if (!Redis::sismember($key, $mainUid)) {
            Redis::sAdd($key, $mainUid);
            Redis::expire($key, StatisticalConstMap::STATISTICAL_EXPIRE);
        }
    }

    /**
     * @param $platform
     * @param $day
     * @return mixed
     * 注册统计
     */
    public static function getStatisticRegister($platform, $day)
    {
        $key = StatisticalConstMap::getRegKey(...func_get_args());
        return (int)Redis::scard($key);
    }

    /**
     * @param $platform
     * @param int $day
     * @return mixed
     * 获取Uv 数据 保留三天
     */
    public static function getStatisticUv($platform, int $day = 0): int
    {
        $key = StatisticalConstMap::getUvKey(...func_get_args());
        return (int)Redis::scard($key);
    }

    /**
     * @param $keep
     * @param $platform
     * @param $day
     * @return mixed
     * 获取留存数据 各端留存
     */
    public static function getStatisticKeepByPlatform($keep, $platform, $day): int
    {
        $key = StatisticalConstMap::getKeepKeyByPlatform($keep, $platform, $day);
        return (int)Redis::get($key);
    }

    /*
     * @param $keep
     * @param $platform
     * @param $day
     * @return mixed
     * 获取 汇总留存数据
     */
    public static function getStatisticKeepByAll($keep, $day): int
    {
        $key = StatisticalConstMap::getKeepKeyByAll($keep, $day);
        return (int)Redis::get($key);
    }


    public static function getPlatformList($day = 0)
    {
        $key = StatisticalConstMap::getPlatformKey($day);

        return Redis::smembers($key);
    }

    public static function getStatisticPv($platform, $day): int
    {
        return 0;
    }

    public static function clearStatisticCache(array $platformList, $day): void
    {

        $keyList[] = StatisticalConstMap::getKeepKeyByAll(StatisticalConstMap::PLATFORM_ALL_SIGN, $day);
        foreach ($platformList as $platform) {

            $keyList[] = StatisticalConstMap::getKeepKeyByPlatform(1, $platform, $day);
            $keyList[] = StatisticalConstMap::getKeepKeyByPlatform(7, $platform, $day);
            $keyList[] = StatisticalConstMap::getKeepKeyByPlatform(30, $platform, $day);
            $keyList[] = StatisticalConstMap::getRegKey($platform, $day);
            $keyList[] = StatisticalConstMap::getUvKey($platform, $day);

        }
        $keyList[] = StatisticalConstMap::getKeepKeyByAll(1, $day);
        $keyList[] = StatisticalConstMap::getKeepKeyByAll(7, $day);
        $keyList[] = StatisticalConstMap::getKeepKeyByAll(30, $day);
        $keyList[] = StatisticalConstMap::getPlatformKey($day);

        foreach ($keyList as $key) {
            Redis::expire($key, 0);
        }
    }

    /**
     * @param $platform
     * @param int $day
     * @return array
     * 数据结构
     */
    public static function getStatisticData($platform, int $day = 0): array
    {
        $stamp = strtotime(date('Y-m-d', strtotime(sprintf('+%d day', $day))));
        $isAll = $platform === StatisticalConstMap::PLATFORM_ALL_SIGN;
        return [
            'year' => date('Y', $stamp),
            'month' => date('m', $stamp),
            'day' => date('d', $stamp),
            'week' => date('W', $stamp),
            'stamp' => $stamp,
            'platform' => $platform,
            'keep_day' => $isAll ? self::getStatisticKeepByAll(1, $day) : self::getStatisticKeepByPlatform(1, $platform, $day),
            'keep_week' => $isAll ? self::getStatisticKeepByAll(7, $day) : self::getStatisticKeepByPlatform(7, $platform, $day),
            'keep_month' => $isAll ? self::getStatisticKeepByAll(30, $day) : self::getStatisticKeepByPlatform(30, $platform, $day),
            'pv' => self::getStatisticPv($platform, $day),
            'uv' => self::getStatisticUv($platform, $day),
            'reg' => self::getStatisticRegister($platform, $day),
        ];
    }
}
