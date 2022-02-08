<?php

namespace Modules\Panels\Entities;

class PanelsCardCommentInfoModel extends BaseModel
{

	protected $table = 'xy_panels_card_comment_info';

	protected $fillable = ['comment_desc', 'upload_map', 'notice_map'];
}
