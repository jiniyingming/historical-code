<?php

namespace Modules\Panels\Services;

use App\Models\Company\CompanyUserModel;
use App\Services\HelperServiceTrait;

/**
 * 主要业务逻辑实现
 */
abstract class PanelsBaseService
{
	use HelperServiceTrait;

	/****通知类型****/
	public const    NOTICE_LEADER_CODE = 136;
	public const    NOTICE_USER_CODE   = 137;
	protected const DELETE_COMMENT     = 140;

	/**
	 * 任务看板相关通知 编号定义
	 */
	public const PANELS_ITEM_NOTICE         = 84;
	public const PANELS_ITEM_DELETE_NOTICE  = 86;
	public const PANELS_CARD_INFO_NOTICE    = 85;
	public const PANELS_CARD_NOTICE         = 87;
	public const PANELS_CARD_DELETED_NOTICE = 89;
	public const PANELS_UNBIND_PROJECT      = 90;

	/***权限组***/
	protected const MOVE_PERMISSION_MAP = [
		1 => ['creator', 'admin'],
		2 => ['member', 'creator'],
		3 => ['creator', 'admin', 'member'],
		4 => ['creator'],
	];

	public const SORT_INCR_VAL = 0.0001;
	/**
	 * 评论标签
	 */
	protected const CONTENT_HTML_SIGN = '<span style="color:#638DFA">@%s </span>';
	protected const UPLOAD_NUM_LIMIT  = 9;
	protected const NOTICE_ALL_MEMBER = 'ALL';
	protected const NOTICE_PLACE_SIGN = '@!';
	protected const UPDATE_LIMIT_TIME = 5;

	/**
	 * @param array $noticeUsers
	 *
	 * @return array
	 * 过滤外部联系人
	 */
	protected static function checkOutsideUsers(array $noticeUsers): array
	{
		//--外部联系人限制
		if (!empty($noticeUsers) && config('panels.taskExternalSwitch') && (getProjectInfo()->company_id ?? false)) {
			$data        = CompanyUserModel::query()->select('identity', 'user_id')->where(['company_id' => getProjectInfo()->company_id])->whereIn('identity', config('panels.taskExternalMap'));
			$data        = $data->whereIn('user_id', $noticeUsers);
			$noticeUsers = $data->get()->pluck('user_id')->toArray();
		}

		return $noticeUsers;
	}

	public static function arraySort($array, $keys, $sort = SORT_DESC)
	{
		$keysValue = [];
		foreach ($array as $k => $v) {
			$keysValue[ $k ] = $v[ $keys ];
		}
		array_multisort($keysValue, $sort, $array);

		return $array;
	}

	/**
	 * @param        $str
	 * @param int    $start
	 * @param        $length
	 * @param string $charset
	 * @param bool   $suffix
	 *
	 * @return false|string
	 */
	public static function substr($str, $start = 0, $length, $charset = "utf-8", $suffix = true)
	{

		if (function_exists("mb_substr")) {
			return mb_substr($str, $start, $length, $charset);
		}

		if (function_exists('iconv_substr')) {
			return iconv_substr($str, $start, $length, $charset);
		}

		$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";

		$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";

		$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";

		$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";

		preg_match_all($re[ $charset ], $str, $match);

		$slice = implode("", array_slice($match[0], $start, $length));

		if ($suffix) {
			return $slice . "…";
		}

		return $slice;
	}
}
