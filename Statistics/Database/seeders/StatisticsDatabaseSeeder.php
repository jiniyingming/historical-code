<?php

namespace Modules\Statistics\Database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class StatisticsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // $this->call("OthersTableSeeder");
    }
}