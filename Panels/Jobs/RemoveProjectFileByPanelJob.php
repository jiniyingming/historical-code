<?php

namespace Modules\Panels\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Modules\Panels\Services\PanelsService;

class RemoveProjectFileByPanelJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	private $params;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(array $params)
	{
		//
		$this->params = $params;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle(): void
	{
		//-- 任务看板相关文件操作处理
		try {
			PanelsService::removeProjectFileByPanel(...$this->params);
		} catch (\Exception $exception) {
			Log::error('RemoveProjectFileByPanelJob', $this->params);
		}
	}
}
