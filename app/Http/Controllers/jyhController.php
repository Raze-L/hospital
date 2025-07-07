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
use OSS\OssClient;
use OSS\Core\OssException;
use Illuminate\Support\Facades\Log;

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
 * @return \Illuminate\Http\JsonResponse
 */
public function uploadCtScan(Request $request)
{
    // 详细记录请求信息
    Log::info('CT图片上传请求', [
        'user_id' => auth()->id(),
        'patient_id' => $request->input('patient_id'),
        'file_count' => $request->hasFile('images') ? count($request->file('images')) : 0
    ]);

    // 参数验证 - 支持多文件
    $validator = Validator::make($request->all(), [
        'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        'patient_id' => 'required|string|exists:patients,patient_id',
    ], [
        'images.*.required' => '请至少上传一张CT图片',
        'images.*.image' => '上传的文件必须是图片',
        'images.*.mimes' => '只支持JPEG, PNG, JPG, GIF格式的图片',
        'images.*.max' => '每张图片大小不能超过2MB',
    ]);

    if ($validator->fails()) {
        Log::warning('CT图片上传参数验证失败', $validator->errors()->toArray());
        return response()->json([
            'code' => 422,
            'message' => '参数验证失败',
            'errors' => $validator->errors()
        ], 422);
    }

    // 获取当前用户和患者信息
    $user = auth()->user();
    $patientId = $request->input('patient_id');
    
    try {
        $patient = Patient::where('patient_id', $patientId)
                        ->where('user_id', $user->id)
                        ->firstOrFail();
    } catch (\Exception $e) {
        Log::error('患者查找失败', [
            'patient_id' => $patientId, 
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'code' => 404,
            'message' => '找不到该患者或您无权操作'
        ], 404);
    }

    $images = $request->file('images');
    $uploadedFiles = [];
    
    try {
        // 获取OSS配置
        $accessKey = config('oss.access_key') ?: env('OSS_ACCESS_KEY', env('OSS_ACCESS_KEY_ID'));
        $secretKey = config('oss.secret_key') ?: env('OSS_SECRET_KEY', env('OSS_ACCESS_KEY_SECRET'));
        $endpoint = config('oss.endpoint') ?: env('OSS_ENDPOINT');
        $bucket = config('oss.bucket') ?: env('OSS_BUCKET');
        
        // 验证配置完整性
        if (empty($accessKey) || empty($secretKey) || empty($endpoint) || empty($bucket)) {
            throw new \Exception("OSS配置不完整，请检查access_key/secret_key/endpoint/bucket配置");
        }

        // 正确初始化OSS客户端
        $ossClient = new OssClient($accessKey, $secretKey, $endpoint, false);
        
        // 循环处理每个上传的文件
        foreach ($images as $image) {
            // 生成唯一文件名和存储路径
            $fileName = 'ctscan_'.$patient->id.'_'.md5(time().uniqid()).'.'.$image->getClientOriginalExtension();
            $objectPath = 'patients/' . $patient->patient_id . '/' . $fileName;
            
            // 读取文件内容
            $fileContent = file_get_contents($image->getRealPath());
            if (!$fileContent) {
                Log::warning('无法读取文件内容', ['file' => $image->getClientOriginalName()]);
                continue;
            }
            
            // 上传到OSS
            $uploadResult = $ossClient->putObject($bucket, $objectPath, $fileContent, [
                OssClient::OSS_CONTENT_TYPE => $image->getMimeType(),
                OssClient::OSS_LENGTH => strlen($fileContent),
                OssClient::OSS_CHECK_MD5 => true
            ]);
            
            // 生成正确的三级域名URL
            $url = 'https://' . $bucket . '.' . $endpoint . '/' . $objectPath;
            
            // 创建CT扫描记录
            $ctScan = CtScan::create([
                'patient_id' => $patient->patient_id,
                'image_url' => $url,
                'original_name' => $image->getClientOriginalName(),
                'storage_path' => $objectPath,
                'file_size' => $image->getSize()
            ]);
            
            // 记录上传成功的文件信息
            $uploadedFiles[] = [
                'id' => $ctScan->id,
                'url' => $url,
                'oss_path' => $objectPath,
                'file_info' => [
                    'original_name' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'mime_type' => $image->getMimeType()
                ]
            ];
        }
        
        // 检查是否有成功上传的文件
        if (empty($uploadedFiles)) {
            throw new \Exception("没有文件成功上传");
        }
        
        Log::info('所有CT图片上传完成', [
            'total_count' => count($images),
            'success_count' => count($uploadedFiles),
            'patient_id' => $patient->patient_id
        ]);
        
        return response()->json([
            'code' => 201,
            'message' => 'CT图片上传成功',
            'data' => [
                'total_files' => count($images),
                'uploaded_files' => count($uploadedFiles),
                'files' => $uploadedFiles,
                'patient_info' => [
                    'id' => $patient->id,
                    'patient_id' => $patient->patient_id
                ]
            ]
        ], 201);
        
    } catch (OssException $e) {
        $debugInfo = [
            'error' => $e->getMessage(),
            'request_id' => $e->getRequestId(),
            'code' => $e->getErrorCode(),
            'endpoint_used' => $endpoint ?? '未获取',
            'bucket_used' => $bucket ?? '未获取',
        ];
        
        Log::error('OSS上传异常', $debugInfo);
        
        return response()->json([
            'code' => 500,
            'message' => 'OSS上传失败',
            'error' => $e->getMessage(),
            'oss_code' => $e->getErrorCode(),
            'request_id' => $e->getRequestId(),
            'debug' => config('app.debug') ? $debugInfo : null
        ], 500);
        
    } catch (\Exception $e) {
        Log::error('CT图片上传系统异常', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'patient_info' => [
                'user_id' => $user->id ?? null,
                'patient_id_requested' => $patientId,
                'patient_found' => isset($patient) ? [
                    'id' => $patient->id,
                    'patient_id' => $patient->patient_id
                ] : '未找到'
            ]
        ]);
        
        return response()->json([
            'code' => 500,
            'message' => '系统错误: ' . $e->getMessage(),
            'debug' => config('app.debug') ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'hint' => '请检查患者ID关联关系'
            ] : null
        ], 500);
    }

}  


/**
 * 获取患者详情（开放权限版）
 * 
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getPatientDetail(Request $request)
{
    $patientId = $request->input('patient_id');
    
    // 保留原有参数验证
    if (!$patientId) {
        return response()->json([
            'code' => 422,
            'message' => '参数验证失败',
            'errors' => ['patient_id' => ['patient_id 不能为空']]
        ], 422);
    }

    // 仅移除用户ID过滤条件（保留其他所有逻辑）
    $patient = Patient::where('patient_id', $patientId)
                    ->with(['ctScans' => function($query) {
                        $query->with('analysis');
                    }])
                    ->firstOrFail();

    return response()->json($patient);
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
 * 获取患者详情（公开访问）
 * 
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getPatientDetailPublic(Request $request)
{
    $patientId = $request->input('patient_id');
    
    if (!$patientId) {
        return response()->json([
            'code' => 400,
            'message' => '缺少必要参数',
            'errors' => [
                'patient_id' => ['patient_id 参数是必需的']
            ]
        ], 400);
    }

    $patient = Patient::where('patient_id', $patientId)
                     ->with(['ctScans' => function($query) {
                         $query->with('analysis')->orderBy('created_at', 'desc');
                     }])
                     ->first();

    if (!$patient) {
        return response()->json([
            'code' => 404,
            'message' => '找不到该患者',
            'hint' => '请检查 patient_id 是否正确'
        ], 404);
    }

    return response()->json([
        'code' => 200,
        'message' => '获取患者信息成功',
        'data' => $patient
    ]);
}
    
    /**
     * 查看图片的分析结果
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImageAnalysisResult(Request $request)
    {
        // 验证请求体，确保包含 ct_scan_id
        $request->validate([
            'ct_scan_id' => 'required|integer',
        ]);

        // 从 analyses 表中获取分析结果
        $analysis = \App\Models\Analysis::where('ct_scan_id', $request->ct_scan_id)->first();

        if (!$analysis) {
            return response()->json(['message' => '未找到对应的分析结果'], 404);
        }

        return response()->json($analysis);
    }



    /**
 * 公开获取患者CT扫描列表（无需认证）
 * 
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getPatientCtScans(Request $request)
{
    // 验证参数
    $validator = Validator::make($request->all(), [
        'patient_id' => 'required|string|exists:patients,patient_id'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'code' => 422,
            'message' => '参数验证失败',
            'errors' => $validator->errors()
        ], 422);
    }

    $patient = Patient::where('patient_id', $request->patient_id)
                     ->firstOrFail();

    $ctScans = CtScan::where('patient_id', $patient->patient_id)
                    ->with(['analysis'])
                    ->orderBy('created_at', 'desc')
                    ->get();

    // 过滤敏感字段
    $filteredScans = $ctScans->map(function ($scan) {
        return [
            'id' => $scan->id,
            'image_url' => $scan->image_url,
            'created_at' => $scan->created_at,
            'analysis' => $scan->analysis ? [
                'result' => $scan->analysis->result,
                'created_at' => $scan->analysis->created_at
            ] : null
        ];
    });

    return response()->json([
        'code' => 200,
        'message' => '获取CT扫描列表成功',
        'data' => [
            'patient' => [
                'patient_id' => $patient->patient_id,
                'name' => $patient->name,
                'gender' => $patient->gender,
                'age' => $patient->age
            ],
            'ct_scans' => $filteredScans
        ]
    ]);
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
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }


    public function store(Request $request)
    {
        // 验证请求中是否包含文件
        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }

        // 获取上传的文件
        $file = $request->file('file');

        // 验证文件是否有效
        if (!$file->isValid()) {
            return response()->json(['message' => 'File is not valid'], 400);
        }

        // 设置文件在OSS中的存储路径
        $filePath = 'uploads/' . $file->getClientOriginalName();

        // 配置OSS客户端
        try {
            $ossClient = new OssClient(
                env('OSS_ACCESS_KEY_ID'),
                env('OSS_ACCESS_KEY_SECRET'),
                env('OSS_ENDPOINT')
            );

            // 将文件上传到OSS
            $ossClient->putObject(
                env('OSS_BUCKET'),
                $filePath,
                file_get_contents($file->getRealPath())
            );

            return response()->json(['message' => 'File uploaded successfully']);
        } catch (OssException $e) {
            return response()->json(['message' => 'Upload failed', 'error' => $e->getMessage()], 500);
        }
    }










































}
