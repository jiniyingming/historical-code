<?php

namespace Modules\Statistics\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BaseModel extends Model
{
    public function addAll(Array $data): bool
    {
        return DB::table($this->getTable())->insert($data);
    }
}
