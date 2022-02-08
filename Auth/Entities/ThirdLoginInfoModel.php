<?php

namespace Modules\Auth\Entities;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Modules\BaiDu\Entities\BaiDuToken;
use Illuminate\Database\Eloquent\Model;
use Modules\BaiDu\Entities\BaiDuStatistic;
use Modules\Auth\Services\common\AuthConstMap;

class ThirdLoginInfoModel extends Model
{

	protected $connection = 'mysql';

	protected $table = 'xy_third_login_info';

	protected $fillable = [
		'channel',
		'user_id',
		'unique_id',
		'open_id',
		'info',
		'created_at',
		'last_login_time',
		'status',
	];

	public const    BIND_STATUS        = 1;
	protected const REMOVE_BIND_STATUS = 0;

	public function getThirdRegistered($channel, $uniqueId)
	{
		return self::query()->where([
			'channel'   => $channel,
			'unique_id' => $uniqueId,
			'status'    => self::BIND_STATUS,
		])->first();
	}

	public function getThirdByFilter($filter)
	{
		return self::query()->where(array_merge($filter, ['status' => self::BIND_STATUS]))->first();
	}

	public function bindThirdAccount($channel, $personId, $nickname, $unionId, $info): array
	{
		$time      = date('Y-m-d H:i:s');
		$bindExist = self::query()->where([
			'channel'   => $channel,
			'user_id'   => $personId,
			'unique_id' => $unionId,
		])->first();
		if ($bindExist) {
			$bindExist->nickname !== $nickname && $bindExist->nickname = $nickname;
			$bindExist->info !== json_encode($info) && $bindExist->info = json_encode($info);
			$bindExist->created_at = $time;
			$bindExist->status     = self::BIND_STATUS;
			$status                = $bindExist->save();
		} else {
			$status = self::insert([
				'channel'    => $channel,
				'user_id'    => $personId,
				'nickname'   => $nickname,
				'open_id'    => $info['openId'] ?? null,
				'unique_id'  => $unionId,
				'info'       => json_encode($info),
				'status'     => self::BIND_STATUS,
				'created_at' => $time,
			]);
			if ($channel === AuthConstMap::LOGIN_DRIVER_BY_BAIDU) {
				$tokenInfo = $info['other']['tokenInfo'] ?? [];
				BaiDuToken::query()
					->updateOrCreate([
						'user_id' => $personId,
					], [
						'access_token' => $tokenInfo['access_token'] ?? '',
						'refresh_token' => $tokenInfo['refresh_token'] ?? '',
						'expires_in' => Carbon::now()->addSeconds($tokenInfo['expires_in'])->subHours(5),
					]);
				$day = date("Ymd");
				Redis::sAdd("statistic:baidu:daily_bind_user:" . $day, $personId);
				BaiDuStatistic::updateOrCreate(
					['day' => $day],
					[
						'day' => $day,
						'bind_user' => Redis::sCard("statistic:baidu:daily_user:" . $day),
					]
				);
			}
		}
		if (!$status) {
			return [];
		}

		return [
			'channel'    => $channel,
			'user_id'    => $personId,
			'nickname'   => $nickname,
			'unique_id'  => $unionId,
			'created_at' => $time,
		];
	}

	public function unBindThirdAccount($channel, $personId): int
	{
		return DB::connection($this->connection)->table($this->table)->where([
			'channel' => $channel,
			'user_id' => $personId,
		])->update(['status' => self::REMOVE_BIND_STATUS]);
	}
}
