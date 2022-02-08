<?php

use Modules\Auth\Services\common\AuthConstMap;

return [
	'name'                       => 'Auth',
	'driver'                     => [
		AuthConstMap::LOGIN_DRIVER_BY_DING     => [
			'corp_id'       => env('THIRD_DING_CORP_ID'),
			'suite_id'      => env('THIRD_DING_SUITE_ID'),
			'min_app_id'    => env('THIRD_DING_MIN_APP_ID'),
			'app_id'        => env('THIRD_DING_APP_ID'),
			'client_id'     => env('THIRD_DING_KEY'),
			'client_secret' => env('THIRD_DING_SECRET'),
			'redirect'      => env('THIRD_CALLBACK_URL'),
			'token'         => env('SUITE_TICKET_TOKEN'),
			'secret'        => env('SUITE_TICKET_SECRET'),
		],
		AuthConstMap::LOGIN_DRIVER_BY_BAIDU    => [
			'app_key'    => env('THIRD_BAIDU_APP_KEY', ''),
			'secret_key' => env('THIRD_BAIDU_SECRET_KEY', ''),
			'sign_key'   => env('THIRD_BAIDU_SIGN_KEY', ''),
			'app_id'     => env('THIRD_BAIDU_APP_ID', ),
			'redirect'   => env('THIRD_CALLBACK_URL', ''),
		],
		AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK => [
			'app_id'       => env('THIRD_FLY_APP_ID', ''),
			'app_secret'   => env('THIRD_FLY_APP_SECRET', ''),
			'redirect'     => env('THIRD_CALLBACK_URL', ''),
			'encrypt_key'  => env('THIRD_FLY_TICKET_ENCRYPT_KEY', ''),
			'self_builder' => false

		],
	], //--已开启第三方的服务
	'open_third_services'        => [
		AuthConstMap::LOGIN_DRIVER_BY_DING,
		AuthConstMap::LOGIN_DRIVER_BY_BAIDU,
		//		AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK,
	], //--仅对个人版进行开放的第三方应用
	'third_services_by_personal' => [
		AuthConstMap::LOGIN_DRIVER_BY_BAIDU,
		//		AuthConstMap::LOGIN_DRIVER_BY_FLY_BOOK,
	],
];
