<?php

namespace Modules\Panels\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
	use SoftDeletes;

	protected $connection = 'mysql_project';
}
