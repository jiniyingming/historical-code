<?php

namespace Modules\InternalApi\Services;

use App\component\AliClient;
use App\Jobs\ContentReviewJob;
use App\Models\Media\MediaFileResourcesModel;
use App\Models\ProjectFileModel;
use App\Models\ShootFileModel;
use App\Services\ContentReviewService;
use Log;

class ImageSpotService extends ContentReviewService
{
	/**
	 * @param int $channel
	 * @param int $fileSign
	 */
	public function contentReview(int $channel, int $fileSign): void
	{
		$params = func_get_args();
		switch ($channel) {
			case self::PROJECT_TYPE:
				$obj = ProjectFileModel::find($fileSign);
				break;
			case self::MEDIA_TYPE:
				$obj = MediaFileResourcesModel::find($fileSign);
				break;
			case self::IOT_TYPE:
			case self::PHOTO_TYPE:
				$obj = ShootFileModel::find($fileSign);
				break;
			default:
				return;
		}
		$params[] = $obj->user_id ?? 0;
		ContentReviewJob::dispatch(...$params)->onQueue(config('queue.spot'));
		//--封面图识别
		if (($obj->cover_img ?? false) && $obj->status !== self::COMMON_DISABLE_STATUS) {
			$params[] = $obj->cover_img;
			ContentReviewJob::dispatch(...$params)->onQueue(config('queue.spot'));
		}
	}

	/**
	 * @param $imageUrl
	 *
	 * @return array
	 */
	public function spotImage($imageUrl): array
	{
		$returnVal['spotStatus'] = true;
		$returnVal['spotError']  = null;
		$returnVal['spotValue']  = [];
		try {
			$returnVal['spotValue'] = AliClient::getInstance()->contentReview($imageUrl);
		} catch (\Exception $e) {
			$returnVal['spotError']  = $e->getMessage();
			$returnVal['spotStatus'] = false;
		}
		$returnVal['labelMap']      = self::$labelMap;
		$returnVal['imageLevelMap'] = self::$levelMap;

		Log::info('spotImage', $returnVal);

		return $returnVal;
	}
}
