<?php

use App\component\RedisTool\RedisCode;

return [
	'name'               => 'Panels',
	/**
	 * 任务看板 verify验证规则
	 */
	'paramsRuleMap'      => [
		'Modules\Panels\Http\Controllers\PanelsSysController@createCard'   => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@updateCard'   => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@deleteCard'   => ['project_id'],
		//        'Modules\Panels\Http\Controllers\PanelsSysController@sortCard' => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@createPanels' => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@updatePanels' => ['id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@deletePanels' => ['id'],
		'Modules\Panels\Http\Controllers\PanelsSysController@sortPanels'   => ['project_id', 'item_id'],

		'Modules\Panels\Http\Controllers\PanelsV2Controller@cardDetail' => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsV2Controller@cardView'   => ['item_id', 'page_id'],
		'Modules\Panels\Http\Controllers\PanelsV2Controller@itemView'   => ['project_id', 'page_id'],

		'Modules\Panels\Http\Controllers\PanelsCardController@checkNum'  => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsCardController@bind'      => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsCardController@unBind'    => ['project_id'],
		'Modules\Panels\Http\Controllers\PanelsCardController@checkBind' => ['project_id'],

	],
	'panels_write_auth'  => [
		'Modules\Panels\Http\Controllers\PanelsSysController@createPanels',
		'Modules\Panels\Http\Controllers\PanelsSysController@updatePanels',
		'Modules\Panels\Http\Controllers\PanelsSysController@deletePanels',
	],
	/**
	 * 任务看板 外部联系人访问开关
	 */
	'taskExternalSwitch' => true,
	'taskExternalMap'    => [1, 2, 3, 4],
	'noticeMap' => [84, 85, 86, 87, 89, 90, 120, 119],
	/**
	 * 任务看板 API 缓存规则
	 */
	'cacheApiMap'        => [
		'Modules\Panels\Http\Controllers\PanelsV2Controller@itemView'      => [
			'moduleType' => RedisCode::TASK_PANELS_MODULE,
			'method'     => 'GET',
			'fieldsMap'  => []
		],
		'Modules\Panels\Http\Controllers\PanelsSysController@calendarList' => [
			'moduleType' => RedisCode::TASK_PANELS_CALENDAR_MODULE,
			'method'     => 'GET',
			'fieldsMap'  => []
		],
		'Modules\Panels\Http\Controllers\PanelsV2Controller@cardView'      => [
			'moduleType' => RedisCode::TASK_PANELS_CALENDAR_MODULE,
			'method'     => 'GET',
			'fieldsMap'  => []
		],
	],
	/**
	 * 任务看板缓存开关
	 */
	'cacheApiSwitch'     => false,

];
