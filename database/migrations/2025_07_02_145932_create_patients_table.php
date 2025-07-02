<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientsTable extends Migration
{
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patientID')->unique(); // 医生填写的患者ID
            $table->string('name');
            $table->enum('sex', ['男', '女']);
            $table->integer('age');
            $table->date('birth');
            $table->string('phonenum');
            $table->text('address');
            $table->string('urgentcall'); // 紧急联系人
            $table->string('bloodtype');
            $table->text('allergy')->nullable(); // 过敏史
            $table->text('pastillness')->nullable(); // 过往病例
            $table->integer('image')->default(0); // CT图数量
            $table->string('status', ['已分析', '未分析']);

            // 索引优化
            $table->index('patientID');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
}
