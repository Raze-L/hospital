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
            $table->foreignId('ct_scan_id')->constrained()->onDelete('cascade');
            $table->text('image_analysis');
            $table->text('diagnostic_opinion');
            $table->text('treatment_recommendation');
            $table->string('result_image_url', 255);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('analyses');
    }
}