<?php

namespace Modules\Auth\Entities;

use Illuminate\Database\Eloquent\Model;

class ThirdTicketInfoModel extends Model
{
	protected $connection = 'mysql';

	protected $table = 'xy_third_ticket_info';

	protected $fillable = ['id', 'channel', 'info'];

	public function getLatestTicket($channel, $pushType)
	{
		$info = self::query()->where([
			'channel' => $channel,
			'type'    => $pushType,
		])->orderBy('id', 'desc')->first();
		if (!$info) {
			return null;
		}

		return json_decode($info->info, true);
	}

	public function addLatestTicket($channel, $pushType, $syncInfo): bool
	{
		return self::query()->insert([
			'channel' => $channel,
			'type'    => $pushType,
			'info'    => json_encode($syncInfo),
		]);
	}
}
