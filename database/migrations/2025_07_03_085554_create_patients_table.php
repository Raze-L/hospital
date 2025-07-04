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
            $table->string('patient_id', 20)->unique();
            $table->string('name', 50);
            $table->string('gender', 10); // 'male' or 'female'
            $table->integer('age');
            $table->date('birth_date');
            $table->string('phone', 20);
            $table->string('address', 255);
            $table->string('emergency_contact', 100);
            $table->string('blood_type', 10);
            $table->text('allergy_history')->nullable();
            $table->text('medical_history')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
}