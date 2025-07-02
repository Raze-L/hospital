<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalysesTable extends Migration
{
    public function up()
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('patientID'); // 关联患者ID
            $table->text('analysis'); // 影像分析
            $table->text('advice'); // 诊断意见
            $table->text('treatadvice'); // 治疗建议
            $table->timestamps();

            // 外键关联
            //级联删除：当患者记录删除时，相关分析记录自动删除
            $table->foreign('patientID')
                ->references('patientID')
                ->on('patients')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('analyses');
    }
}
