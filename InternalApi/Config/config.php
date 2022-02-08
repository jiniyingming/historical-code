<?php

return [
	'name'        => 'InternalApi',
	'application' => [
		'yl.content.image' => [
			'Modules\InternalApi\Http\Controllers\InternalApiController@contentReview',
			'Modules\InternalApi\Http\Controllers\InternalApiController@spot',
			'Modules\InternalApi\Http\Controllers\InternalApiController@transcodeCallback',
		],
	],
];
