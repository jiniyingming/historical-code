<?php

namespace Modules\Panels\Entities;

class PanelsCardCommentModel extends BaseModel
{
	protected $table = 'xy_panels_card_comment';

	protected $fillable = ['info_id', 'card_id', 'project_id', 'user_id', 'id'];
}
