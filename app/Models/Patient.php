<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'name',
        'gender',
        'age',
        'birth_date',
        'phone',
        'address',
        'emergency_contact',
        'blood_type',
        'allergy_history',
        'medical_history',
        'user_id'
    ];



public static function addPatients($validated){
    try{
       $excitingPatient =  self::where('patient_id', $validated['patient_id'])->first();
        if($excitingPatient)
        {
           return NULL;
        }

        return Patient::create([
            'patient_id' => $validated['patient_id'],
            'name' => $validated['name'],
            'gender' => $validated['gender'],
            'age' => $validated['age'],
            'birth_date' => $validated['birth_date'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'emergency_contact' => $validated['emergency_contact'],
            'blood_type' => $validated['blood_type'],
            'allergy_history' => $validated['allergy_history'],
            'medical_history' => $validated['medical_history'],
            'user_id' => $validated['user_id']
        ]);

    }catch (\Exception $e){
        return $e->getMessage();
    }
}


    // 一个患者有多张CT
    public function ctScans()
    {
        return $this->hasMany(CtScan::class, 'patient_id', 'patient_id');
    }


    // 一个患者属于一个医生
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
