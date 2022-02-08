<?php

namespace Modules\Panels\Http\Middleware;

use App\component\RedisTool\RedisCache;
use App\component\RedisTool\RedisCode;
use App\Jobs\ClearCacheKeyJob;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Language\Status;
use Modules\Panels\Services\PanelsCardService;
use Modules\Panels\Services\PanelsService;

/**
 * 定制缓存规则及其路由
 * 缓存原则：
 *       1.公共数据且避免关联用户维度
 *       2.缓存归属模块及其涉及参数 （方便缓存管理同步及其移除）
 *       3.数据缓存更新：
 *                  - 采用API 数据缓存方式
 *                  - 缓存定义模板 $cacheFilterMap   fieldsMap 空值 用传入参作为唯一键  有指定请求的参数 则按参数绑定
 *                  - 缓存更新原则 目前是已依赖增量更新方式 发生增量更新 则清除 其API下所有缓存 作用于更新
 *      4. 缓存清除方式 ClearCacheKeyJob::dispatch($nameSpace)->onQueue(config('queue.low'));
 */
class PanelsDataMiddleware
{
	private $cacheFilterMap = [];
	private $nameSpace;
	private $isCache        = false;

	/**
	 * @param Request $request
	 * @param Closure $next
	 *
	 * @return mixed
	 * 返回数据处理
	 * @throws Exception
	 */
	public function handle(Request $request, Closure $next)
	{
		$this->cacheFilterMap = config('panels.cacheApiMap');
		$this->nameSpace      = $request->route()->getActionName();
		//--数据缓存处理
		$this->filterCache($request);

		$response = $next($request);
		$content  = json_decode($response->content(), true);

		if (isset($content['code']) && (int)$content['code'] === Status::SUCCESS) {
			//--数据格式处理
			$this->dealProcessingFormat($request, $response);

			//--缓存设置
			$this->cacheData($response);
		}

		return $response;

	}

	private function dealProcessingFormat($request, $response): void
	{
		$content = json_decode($response->content(), true);
		switch ($this->nameSpace) {
			case 'Modules\Panels\Http\Controllers\PanelsV2Controller@calendarList':
				$response->setContent(json_encode($this->dealCalendarListResponse($content, $request)));
				break;
			//.....
		}
	}

	private function dealCalendarListResponse($content, $request)
	{
		$params = $request->input();
		if (isset($params['page_no'], $params['page_size']) && $params['day'] > 0) {
			if ($params['format'] ?? false) {
				$num                         = (($content['data']['page_info']['data_total'] ?? 0) - PanelsCardService::$calendarMax);
				$over_num                    = $num < 0 ? 0 : $num;
				$content['data']             = $content['data']['data_map'][0];
				$content['data']['over_num'] = $over_num;

				return $content;
			}
			$content['data']['date_type'] = $content['data']['data_map'][0]['data_map']['date_type'] ?? 'NOW';
			$content['data']['data_map']  = $content['data']['data_map'][0]['data_map'] ?? [];

			return $content;
		}

		return $content;
	}

	/**
	 * @throws Exception
	 */
	private function filterCache(Request $request): void
	{
		$this->isCache = false;
		if ($map = $this->checkIsCache()) {
			[$data, $requestFilter] = $map;
			$cacheData = RedisCache::getInstance()->getCache($data, $requestFilter['moduleType']);
			if ($cacheData) {
				echo json_encode($cacheData);
				die;
			}
			$this->isCache = true;
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function checkIsCache(): array
	{
		if (isset($this->cacheFilterMap[ $this->nameSpace ]) && config('panels.cacheApiSwitch')) {
			$requestFilter = $this->cacheFilterMap[ $this->nameSpace ];
			$data          = $requestFilter['method'] === 'GET' ? $_GET : $_POST;

			if ($requestFilter['fieldsMap'] ?? false) {
				if (count($data) !== count($requestFilter['fieldMap'])) {
					return [];
				}
				$keyMap = [];
				foreach ($requestFilter['fieldMap'] as $datum) {
					if (isset($data[ $datum ])) {
						$keyMap[ $datum ] = $data[ $datum ];
					}
				}
			} else {
				$keyMap = $data;
			}
			$keyMap[] = $this->nameSpace;
			if (isset($keyMap['verify'])) {
				unset($keyMap['verify']);
			}
			if (empty($keyMap)) {
				return [];
			}
			if (isset($data['updated_at']) || isset($data['updated'])) {
				ClearCacheKeyJob::dispatch($this->nameSpace)->onQueue(config('queue.high'));

				return [];
			}

			return [$keyMap, $requestFilter];
		}

		return [];
	}

	/**
	 * @throws Exception
	 */
	private function cacheData($response): void
	{
		if (($map = $this->checkIsCache()) && $this->isCache) {
			[$data, $requestFilter] = $map;

			$content = json_decode($response->content(), true);
			RedisCache::getInstance()->cacheKey($this->nameSpace)->setCache($data, $content, $requestFilter['moduleType']);
		}
	}

}
