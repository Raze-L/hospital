<?php

namespace App\Http\Controllers;

use App\Models\CtScan;
use App\Models\Patient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LrzController
{
    public function LrzAddPatient(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validatedData = $this->validateRequest($request);
            $patient = $this->createPatientRecord($validatedData);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '患者信息提交成功',
                'data' => [
                    'patient' => $patient,
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => '验证失败',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('患者信息提交失败：' . $e->getMessage(), [
                'request' => $request->all(),
                'user' => auth()->id(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => '提交失败，请重试',
            ], 500);
        }
    }

    private function validateRequest(Request $request): array
    {
        $rules = [
            'patient_id' => 'required|string|unique:patients,patient_id',
            'name' => 'required|string|max:50',
            'gender' => 'required|in:男,女,其他',
            'age' => 'required|integer|min:0|max:150',
            'birth_date' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'emergency_contact' => 'required|string|max:50',
            'blood_type' => 'required|in:A,B,AB,O,A+,A-,B+,B-,AB+,AB-,O+,O-',
            'allergy_history' => 'sometimes|string',
            'medical_history' => 'sometimes|string',
        ];

        return $request->validate($rules);
    }

    private function createPatientRecord(array $validatedData): Patient
    {
        return Patient::create([
            'patient_id' => $validatedData['patient_id'],
            'name' => $validatedData['name'],
            'gender' => $validatedData['gender'],
            'age' => $validatedData['age'],
            'birth_date' => $validatedData['birth_date'],
            'phone' => $validatedData['phone'],
            'address' => $validatedData['address'],
            'emergency_contact' => $validatedData['emergency_contact'],
            'blood_type' => $validatedData['blood_type'],
            'allergy_history' => $validatedData['allergy_history'] ?? null,
            'medical_history' => $validatedData['medical_history'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    public function LrzGetPatientInfo($patientId): JsonResponse
    {
        $patient = Patient::select([
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
            'medical_history'
        ])->findOrFail($patientId);

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'patient' => $patient
            ]
        ]);
    }

    public function LrzSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patientId' => 'nullable|string',
            'name' => 'nullable|string',
            'gender' => 'nullable|string|in:男,女,未知',
            'status' => 'nullable|string|in:已分析,未分析', // 筛选状态
            'page' => 'integer|min:1',
            'pageSize' => 'integer|min:1|max:100',
            'sortBy' => 'string|in:patient_id,name,created_at,ct_count',
            'sortOrder' => 'string|in:asc,desc'
        ]);

        $query = Patient::query()
            ->select([
                'patients.patient_id',
                'patients.name',
                'patients.gender',
                \DB::raw('COUNT(DISTINCT ct_scans.id) as ct_count'),
                \DB::raw("MAX(IF(ct_scans.is_analysised = true, 1, 0)) as has_analyzed"),
            ])
            ->leftJoin('ct_scans', 'patients.patient_id', '=', 'ct_scans.patient_id')
            ->groupBy('patients.patient_id', 'patients.name', 'patients.gender');

        // 动态添加筛选条件
        if ($request->has('patientId') && $request->patientId !== null) {
            $query->where('patients.patient_id', $request->patientId);
        }

        if ($request->has('name') && $request->name !== null) {
            $query->where('patients.name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('gender') && $request->gender !== null) {
            $query->where('patients.gender', $request->gender);
        }

        if ($request->has('status') && $request->status !== null) {
            $statusCondition = $request->status === '已分析' ? '= 1' : '= 0';
            $query->havingRaw("MAX(IF(ct_scans.is_analysised = true, 1, 0)) {$statusCondition}");
        }

        // 添加排序
        $sortBy = $request->input('sortBy', 'created_at');
        $sortOrder = $request->input('sortOrder', 'asc');

        $sortableFields = [
            'patient_id' => 'patients.patient_id',
            'name' => 'patients.name',
            'created_at' => 'patients.created_at',
            'ct_count' => 'ct_count',
        ];

        if (isset($sortableFields[$sortBy])) {
            $query->orderBy($sortableFields[$sortBy], $sortOrder);
        }

        // 执行分页查询
        $patients = $query->paginate(
            $request->input('pageSize', 10),
            ['*'],
            'page',
            $request->input('page', 1)
        );

        return response()->json([
            'code' => 200,
            'message' => '查询成功',
            'data' => $patients
        ]);
    }

    public function LrzGetAnalysesData(Request $request): JsonResponse
    {
        try {
            $patientId = $request->query('patientId');

            if (!$patientId) {
                return response()->json([
                    'code' => 400,
                    'message' => '缺少必要参数: patientId'
                ], 400);
            }

            // 查找患者及其CT扫描和分析数据
            $patient = Patient::with(['ctScans.analysis' => function ($query) {
                $query->select(
                    'id',
                    'ct_scan_id',
                    'image_analysis',
                    'diagnostic_opinion',
                    'treatment_recommendation',
                    'result_image_url',
                    'created_at'
                );
            }])->findOrFail($patientId);

            // 获取第一条CT扫描的分析数据
            $analysis = $patient->ctScans->first()->analysis;

            if (!$analysis) {
                return response()->json([
                    'code' => 404,
                    'message' => '未找到该患者的分析数据'
                ], 404);
            }

            // 只返回需要的字段
            return response()->json([
                'code' => 200,
                'message' => '查询成功',
                'data' => [
                    'image_analysis' => $analysis->image_analysis,
                    'diagnostic_opinion' => $analysis->diagnostic_opinion,
                    'treatment_recommendation' => $analysis->treatment_recommendation,
                    'result_image_url' => $analysis->result_image_url,
                    'created_at' => $analysis->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '查询失败: ' . $e->getMessage()
            ], 500);
        }
    }
    public function LrzDeletePatients($patientId): JsonResponse
    {
        try {
            \DB::beginTransaction();

            // 查找患者（使用 $patientId 参数）
            $patient = Patient::findOrFail($patientId);

            // 删除关联的CT扫描记录
            CtScan::where('patient_id', $patientId)->delete();

            // 删除患者
            $patient->delete();

            \DB::commit();

            return response()->json([
                'code' => 200,
                'message' => '患者删除成功'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => '患者删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
