<?php

namespace Modules\Statistics\Console;

use Exception;
use Illuminate\Console\Command;
use Log;
use Modules\Statistics\Entities\StatisticsInfoModel;
use Modules\Statistics\Services\StatisticalConstMap;
use Modules\Statistics\Services\StatisticalService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CollectScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '运营数据统计';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $sign = -1;
        $date = date('Y-m-d', strtotime(sprintf('+%d day', $sign)));
        $platformList = StatisticalService::getPlatformList($sign);
        $insertData = [];
        $logInfo[] = [PHP_EOL . '-------- statistic:run -------------'];
        $logInfo[] = ['date: ' . $date . '-----init-----'];
        try {
            foreach ($platformList as $platform) {
                $insertData[] = StatisticalService::getStatisticData($platform, $sign);
            }
            $status = false;
            if ($insertData) {
                $insertData[] = StatisticalService::getStatisticData(StatisticalConstMap::PLATFORM_ALL_SIGN, $sign);
                $statisticExec = new StatisticsInfoModel();
                $status = $statisticExec->addAll($insertData);
            }
            $logInfo[] = ['insertStatus: ' . $status . '-----executeIng-----'];
        } catch (Exception $e) {
            $logInfo[] = ['executeError: ' . $e->getMessage() . '-----executeIng-----'];
        }
        if (($status ?? false) && $platformList) {
            StatisticalService::clearStatisticCache($platformList, $sign);
            $logInfo[] = ['executeEnd:-----Clear Cache-----'];
        }
        Log::channel('statistics')->info(implode(PHP_EOL, array_column($logInfo, 0)));
        return;
    }


    /**
     * @throws Exception
     */
    private function addTestData(): void
    {
        $platformList = [1, 2, 4, 21, 43, 42, 16, 17, 31, 23];
        $timestamp = strtotime(date('Y-m-d')) - 86400;
        $max = $timestamp - (86400 * 360);
        while ($max < $timestamp) {

            $insertData = [];
            foreach ($platformList as $platform) {
                $insertData[] = [
                    'year' => date('Y', $max),
                    'month' => date('m', $max),
                    'day' => date('d', $max),
                    'week' => date('W', $max),
                    'stamp' => $max,
                    'platform' => $platform,
                    'keep_day' => random_int(1000, 9000),
                    'keep_week' => random_int(5000, 10000),
                    'keep_month' => random_int(8000, 20000),
                    'pv' => random_int(15000, 55000),
                    'uv' => random_int(5000, 10000),
                    'reg' => random_int(1000, 5000),
                ];
            }
            $insertData[] = [
                'year' => date('Y', $max),
                'month' => date('m', $max),
                'day' => date('d', $max),
                'week' => date('W', $max),
                'stamp' => $max,
                'platform' => -1,
                'keep_day' => array_sum(array_column($insertData, 'keep_day')),
                'keep_week' => array_sum(array_column($insertData, 'keep_week')),
                'keep_month' => array_sum(array_column($insertData, 'keep_month')),
                'pv' => array_sum(array_column($insertData, 'pv')),
                'uv' => array_sum(array_column($insertData, 'uv')),
                'reg' => array_sum(array_column($insertData, 'reg')),
            ];

            $statisticExec = new StatisticsInfoModel();
            $status = $statisticExec->addAll($insertData);
            $max = 86400 + $max;
        }

        die;
    }

}
