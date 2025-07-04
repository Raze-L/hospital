<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Patient;
use App\Models\CtScan;
use App\Models\Analysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class jyhController extends Controller
{
    /**
     * 用户注册
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account' => 'required|string|max:50|unique:users',
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:6',
            'email' => 'required|string|email|max:100|unique:users',
            'phone' => 'required|string|max:20',
            'verification_code' => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 验证验证码
        $cacheKey = 'verification_code_' . $request->email;
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode !== $request->verification_code) {
            return response()->json(['error' => '验证码错误或已过期'], 400);
        }

        $user = User::create([
            'account' => $request->account,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        // 清除验证码缓存
        Cache::forget($cacheKey);

        return response()->json(['message' => '用户注册成功'], 201);
    }

    /**
     * 用户登录
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('account', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => '账号或密码错误'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * 发送验证码
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 生成6位随机验证码
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // 存储验证码到缓存，有效期10分钟
        $cacheKey = 'verification_code_' . $request->email;
        Cache::put($cacheKey, $code, 600); // 10分钟有效期

        // 模拟发送邮件 - 实际项目中应替换为真实邮件服务
        // 这里仅返回验证码用于测试
        return response()->json([
            'message' => '验证码已发送（模拟）',
            'code' => $code, // 实际项目中不应返回验证码
            'expires_in' => 600
        ]);
    }

    /**
     * 重置密码
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account' => 'required|string|max:50',
            'new_password' => 'required|string|min:6|confirmed',
            'email' => 'required|email',
            'verification_code' => 'required|string|min:6|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 验证验证码
        $cacheKey = 'verification_code_' . $request->email;
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode !== $request->verification_code) {
            return response()->json(['error' => '验证码错误或已过期'], 400);
        }

        $user = User::where('account', $request->account)
                  ->where('email', $request->email)
                  ->first();

        if (!$user) {
            return response()->json(['error' => '账号或邮箱不匹配'], 404);
        }

        // 更新密码
        $user->password = Hash::make($request->new_password);
        $user->save();

        // 清除验证码缓存
        Cache::forget($cacheKey);

        return response()->json(['message' => '密码重置成功']);
    }

    /**
     * 获取用户信息和统计信息
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInfo()
    {
        $user = auth()->user();
        
        // 获取患者统计信息
        $patientStats = Patient::where('user_id', $user->id)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when exists (select 1 from ct_scans join analyses on ct_scans.id = analyses.ct_scan_id where ct_scans.patient_id = patients.id) then 1 else 0 end) as analyzed')
            ->first();
        
        $stats = [
            'total_patients' => $patientStats->total,
            'analyzed_patients' => $patientStats->analyzed,
            'pending_patients' => $patientStats->total - $patientStats->analyzed
        ];

        return response()->json([
            'user' => [
                'username' => $user->username,
                'account' => $user->account,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'stats' => $stats
        ]);
    }

    /**
     * 添加患者信息
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|string|max:20|unique:patients',
            'name' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'age' => 'required|integer|min:0',
            'birth_date' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'emergency_contact' => 'required|string|max:100',
            'blood_type' => 'required|string|max:10',
            'allergy_history' => 'nullable|string',
            'medical_history' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = auth()->user();

        $patient = Patient::create([
            'patient_id' => $request->patient_id,
            'name' => $request->name,
            'gender' => $request->gender,
            'age' => $request->age,
            'birth_date' => $request->birth_date,
            'phone' => $request->phone,
            'address' => $request->address,
            'emergency_contact' => $request->emergency_contact,
            'blood_type' => $request->blood_type,
            'allergy_history' => $request->allergy_history,
            'medical_history' => $request->medical_history,
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => '患者信息添加成功', 'patient' => $patient], 201);
    }

    /**
     * 上传CT扫描图片
     * 
     * @param Request $request
     * @param string $patientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadCtScan(Request $request, $patientId)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = auth()->user();
        $patient = Patient::where('patient_id', $patientId)
                         ->where('user_id', $user->id)
                         ->firstOrFail();

        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            // 存储图片并获取URL
            $path = $image->store('ct_scans', 'public');
            $url = Storage::disk('public')->url($path);

            // 创建CT扫描记录
            $ctScan = CtScan::create([
                'patient_id' => $patient->id,
                'image_url' => $url,
            ]);

            $uploadedImages[] = [
                'id' => $ctScan->id,
                'url' => $url
            ];
        }

        return response()->json([
            'message' => 'CT图片上传成功',
            'images' => $uploadedImages
        ], 201);
    }

    /**
     * 获取患者列表
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPatients(Request $request)
    {
        $user = auth()->user();
        
        $query = Patient::where('user_id', $user->id)
            ->withCount('ctScans')
            ->withCount(['ctScans as analyzed_count' => function($query) {
                $query->select(DB::raw('count(distinct analyses.id)'))
                      ->leftJoin('analyses', 'ct_scans.id', '=', 'analyses.ct_scan_id');
            }]);

        // 搜索条件
        // 从 JSON 请求体获取筛选条件
        $filters = $request->json()->all();

        // 搜索条件
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('patient_id', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        // 性别筛选
        if (isset($filters['gender']) && in_array($filters['gender'], ['male', 'female'])) {
            $query->where('gender', $filters['gender']);
        }

        // 状态筛选
        if (isset($filters['status'])) {
            if ($filters['status'] === 'analyzed') {
                $query->has('ctScans.analysis');
            } elseif ($filters['status'] === 'pending') {
                $query->doesntHave('ctScans.analysis');
            }
        }

        // 获取分页参数
        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 10;
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $patients = $query->paginate($perPage, ['*'], 'page', $page);

        // 添加状态字段
        $patients->getCollection()->transform(function ($patient) {
            $patient->status = $patient->analyzed_count > 0 ? 'analyzed' : 'pending';
            return $patient;
        });

        return response()->json($patients);
    }

    /**
     * 删除患者信息（从请求体获取 patientId）
     * 小白讲解：这个函数用来删除患者信息，会从请求体里拿到 patientId 去查找并删除对应患者
     */
    public function deletePatient(Request $request)
    {
        $user = auth()->user();
        $patientId = $request->input('patientId');
        
        if (!$patientId) {
            return response()->json(['error' => '缺少 patientId 参数'], 400);
        }

        $patient = Patient::where('patient_id', $patientId)
                         ->where('user_id', $user->id)
                         ->first();
        
        if (!$patient) {
            return response()->json(['error' => '未找到对应的患者记录'], 404);
        }

        $patient->delete();

        return response()->json(['message' => '患者删除成功']);
    }

    /**
     * 获取患者详情
     * 
     * @param string $patientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPatientDetail($patientId)
    {
        $user = auth()->user();
        
        $patient = Patient::where('patient_id', $patientId)
                         ->where('user_id', $user->id)
                         ->with(['ctScans' => function($query) {
                             $query->with('analysis');
                         }])
                         ->firstOrFail();

        return response()->json($patient);
    }

    /**
     * 获取待分析患者列表
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPatientsForAnalysis(Request $request)
    {
        $user = auth()->user();
        
        $query = Patient::where('user_id', $user->id)
            ->withCount(['ctScans as has_unanalyzed' => function($query) {
                $query->doesntHave('analysis');
            }])
            ->whereHas('ctScans', function($query) {
                $query->doesntHave('analysis');
            });

        // 搜索条件
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('patient_id', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $patients = $query->paginate(4); // 每页4条

        return response()->json($patients);
    }

    /**
     * 获取患者CT扫描列表
     * 
     * @param string $patientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPatientCtScans($patientId)
    {
        $user = auth()->user();
        
        $patient = Patient::where('patient_id', $patientId)
                         ->where('user_id', $user->id)
                         ->firstOrFail();

        $ctScans = CtScan::where('patient_id', $patient->id)
                        ->withExists(['analysis'])
                        ->get();

        return response()->json($ctScans);
    }

    /**
     * 分析CT扫描图像
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeCtScan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ct_scan_id' => 'required|exists:ct_scans,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $ctScan = CtScan::find($request->ct_scan_id);

        // 模拟AI分析结果
        $analysisResult = [
            'image_analysis' => '检测到疑似肿瘤区域，大小约2.5cm×3.0cm，密度不均匀，边缘不规则。',
            'diagnostic_opinion' => '考虑为原发性肺恶性肿瘤可能性大，建议进一步活检确诊。',
            'treatment_recommendation' => '1. 建议CT引导下穿刺活检明确病理诊断；2. 全身PET-CT评估转移情况；3. 多学科会诊确定治疗方案。',
            'result_image_url' => Storage::disk('public')->url('analysis_results/' . Str::random(10) . '.jpg')
        ];

        // 创建分析记录
        $analysis = Analysis::create([
            'ct_scan_id' => $ctScan->id,
            'image_analysis' => $analysisResult['image_analysis'],
            'diagnostic_opinion' => $analysisResult['diagnostic_opinion'],
            'treatment_recommendation' => $analysisResult['treatment_recommendation'],
            'result_image_url' => $analysisResult['result_image_url'],
        ]);

        return response()->json([
            'message' => '分析完成',
            'analysis' => $analysis
        ]);
    }

    /**
     * 响应token
     * 
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}