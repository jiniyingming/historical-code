<?php

namespace Modules\Panels\Database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class PanelsDatabaseSeeder extends Seeder
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
