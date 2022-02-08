<?php

namespace Modules\Panels\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Panels\Services\PanelsService;

/**
 * 任务看板 项目更新 相关增量筛查处理
 */
class PanelsIncrementJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $panelsMap      = [];
	public $overTime       = [];
	public $sendCardNotice = false;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct($panelsMap, $sendCardNotice = false, $overTime = 0)
	{
		//
		$this->panelsMap      = $panelsMap;
		$this->overTime       = $overTime;
		$this->sendCardNotice = $sendCardNotice;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle(): void
	{
		Log::info('PanelsIncrementJob-init', $this->panelsMap);
		if ($this->panelsMap) {
			foreach ($this->panelsMap as $project_number => $docIds) {
				$docIds = array_filter(array_unique($docIds));
				if ($docIds) {
					PanelsService::checkPanelsIncrement($docIds, $project_number, $this->sendCardNotice, $this->overTime);
				}
			}
		}
	}
}
