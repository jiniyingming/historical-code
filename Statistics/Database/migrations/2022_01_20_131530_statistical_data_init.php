<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class StatisticalDataInit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('mysql')->dropIfExists('xy_statistics');
        Schema::connection('mysql')->create('xy_statistics', static function (Blueprint $table) {
            $table->increments('id')->comment('主键');
            $table->char('year',4)->comment('年');
            $table->char('month',2)->comment('月');
            $table->char('day',2)->comment('日');
            $table->char('week',2)->comment('周');
            $table->integer('stamp')->comment('日期时间戳 整点');
            $table->integer('platform')->default(0)->comment('客户端类型 0 全部类型客户端汇总数据');
            $table->integer('keep_day')->default(0)->comment('次日留存');
            $table->integer('keep_week')->default(0)->comment('次周留存');
            $table->integer('keep_month')->default(0)->comment('次月留存');
            $table->integer('pv')->default(0)->comment('pv 数量');
            $table->integer('uv')->default(0)->comment('uv 数量');
            $table->integer('reg')->default(0)->comment('注册人数量');
            $table->dateTime('log_time')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'))->comment('最近落地时间');
            $table->unique(['year', 'month', 'day', 'platform'], 'platform_unique');
            $table->index(['platform', 'stamp'], 'ps_index');
            $table->engine = 'InnoDB';

        });
        \Illuminate\Support\Facades\DB::connection('mysql')->statement("ALTER TABLE `xy_statistics` comment '运营指标数据'");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
