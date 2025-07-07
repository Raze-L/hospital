<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use OSS\Core\OssException;
use OSS\OssClient;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use App\Models\CtScan;
use App\Models\Analysis;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

class TdxController extends Controller
{
    //封装分页方法
    private function getPaginatedResults($query, Request $request) {
        // $perPage: 每页显示的记录数
        // ['*']: 查询所有列，也可以指定特定的列，例如 ['id', 'name']
        // 'page': 指定分页参数的名称，默认为 'page'
        // $page: 当前页码

        $perPage = $request->input('per_page', 10);// 从请求中获取每页显示的记录数，默认值为 10
        $page = $request->input('page', 1);// 从请求中获取当前页码，默认值为 1

        return $query->paginate($perPage, ['*'], 'page', $page);// 对查询结果进行分页处理
    }



    //注册
//    public function register(Request $request): JsonResponse
//    {
//        try {
//            $validated = $request->validate([
//                'account' => 'required|string|max:255|unique:users',
//                'password' => 'required|string|min:8',
//                'username' => 'required|string|max:255',
//                'phone' => 'required|string|size:11|unique:users',
//                'email' => 'required|email|max:255',
//                'code' => 'required|string|size:6'
//            ]);
//
//            $email = strtolower(trim($request->email));
//
//            // 检查邮箱是否已存在
//            if (User::where('email', $email)->exists()) {
//                return json_fail('邮箱已被占用', null, 422);
//            }
//
//            //先检查验证码是否属于当前邮箱
//            $code = Cache::get('verification_code_' . $email);
//            $ownerEmail = Cache::get('verification_code_owner_' . $code);
//
//            //再检查验证码是否正确
//            if (!$code || $code !== $request->code||$ownerEmail !== $email) {
//                return json_fail('验证码错误或已过期', NULL, 422);
//            }
//
//            $validated['password'] = Hash::make($validated['password']);
//            $user = User::register($validated);
//
//            // 清除验证码缓存
//            Cache::forget('verification_code_' . $email);
//            Cache::forget('verification_code_owner_' . $code);
//
//            return json_success('注册成功', null, 200);
//        } catch (Exception $e) {
//            return json_fail('注册失败', $e->getMessage(), 400);
//        }
//    }

    //验证码
//    public function sendVerificationCode(Request $request)
//    {
//        try {
//
//            $request->validate([
//                'email' => 'required|email',
//            ]);
//
//            //统一邮箱规范格式
//            $email = strtolower(trim($request->email));
//
//
//            $code = Str::random(6);
//            Cache::put('verification_code_' . $email, $code, now()->addMinutes(10));
//
//            // 记录原始邮箱到缓存（防止篡改）
//            Cache::put('verification_code_owner_' . $code, $email, now()->addMinutes(10));
//
//            Mail::raw("您的验证码是：{$code}", function ($message) use ($email) {
//                $message->to($email)->subject('注册验证码');
//            });
//
//            return json_success('获取验证码成功', ['code' => $code], 200);
//        } catch (Exception $e) {
//            return json_fail('获取验证码失败', $e->getMessage(), 500);
//        }
//    }


    //登录
//        public function login(Request $request): JsonResponse
//        {
//            try {
//                $request->validate([
//                    'account' => 'required|string|max:255',
//                    'password' => 'required|string|min:8',
//                ]);
//
//                $credentials = $request->only('account', 'password'); // 获取用户输入的账户和密码
//                $user = User::where('account', $credentials['account'])->first(); // 从数据库里面查找唯一用户名
//
//                if (!$user || !Hash::check($credentials['password'], $user->password)) {
//                    return response()->json([
//                        'code' => 401,
//                        'message' => '账户或密码错误'
//                    ]);
//                }
//
//                $token = JWTAuth::fromUser($user); // 生成token
//                return json_success('登录成功', ['token' => $token],200);
//            } catch (Exception $e) {
//                return response()->json(['code' => 500, 'message' => $e->getMessage()]);
//            }
//        }




        //登出
//    public function logout(): JsonResponse
//    {
//        try {
//            //检查 Token 是否存在
//            if (! $token = JWTAuth::getToken()) {
//                return json_fail('Token 未提供或无效', null, 401);
//            }
//
//            //让Token失效
//            JWTAuth::invalidate($token);
//
//            return json_success('用户登出成功', null, 200);
//        } catch (Exception $e) {
//            return json_fail('用户登出失败', $e->getMessage(), 500);
//        }
//    }


         //忘记密码
//         public function  forgetPassword(Request $request): JsonResponse
//         {
//             try {
//                 $validated = $request->validate([
//                     'account' => 'required|string|max:255',
//                     'new_password' => 'required|string|min:8',
//                     'confirm_password' => 'required|string|min:8',
//                     'email' => 'required|email|max:255',
//                     'code' => 'required|string|size:6'
//                 ]);
//
//                 $email = strtolower(trim($validated['email']));
//
//                 if($validated['new_password'] !== $validated['confirm_password']){
//                     return json_fail('两次密码输入不一致', null, 422);
//                 }
//
//                 $user = User::where('account', $request->account)->first();
//                 if (!$user) {
//                     return json_fail('账号不存在', NULL, 404);
//                 }
//
//
//                 //先检查验证码是否属于当前邮箱
//                 $code = Cache::get('verification_code_' . $email);
//                 $ownerEmail = Cache::get('verification_code_owner_' . $code);
//
//                 //再检查验证码是否正确
//                 if (!$code || $code !== $request->code||$ownerEmail !== $email) {
//                     return json_fail('验证码错误或已过期', NULL, 422);
//                 }
//
//                 // 更新密码
//                 $user->password = Hash::make($validated['new_password']);
//                 $user->save();
//
//                 // 更新密码成功后，立即清除两个缓存
//                 Cache::forget('verification_code_' . $email);
//                 Cache::forget('verification_code_owner_' . $code);
//                 return json_success('密码重置成功', null, 200);
//
//             } catch (Exception $e) {
//                 return json_fail('密码重置失败', $e->getMessage(), 500);
//             }
//         }


       //获取医生信息
            public function getDoctors(): JsonResponse
            {
                try {
                    //获取当前 JWT 登录用户
                    $user = JWTAuth::user();
                    if (!$user) {
                        return json_fail('未登录或登录已过期', null, 401);
                    }
                    //获取用户基本信息
                    $doctors = User::where('id',$user->id)->first();
                    //获取该医生负责的患者总数
                     $totalPatients = Patient::where('user_id', $user->id)->count();
                    //获取分析患者数
                    $analysedPatients = Patient::where('user_id', $user->id)->where('is_analysed', 'true')->count();

                    //获取未分析患者数
                    $notAnalysedPatients = Patient::where('user_id', $user->id)->where('is_analysed', 'false')->count();
                    $data = [
                        'doctors' => $doctors,
                        'totalPatients' => $totalPatients,
                        'analyzed_count' => $analysedPatients,
                        'not_analyzed_count' =>  $notAnalysedPatients
                    ];

                    return json_success('获取医生信息成功', $data, 200);
                }catch (Exception $e) {
                    return json_fail('获取医生信息失败', $e->getMessage(), 500);
                }
            }


      //患者信息添加页面
//        public function addPatients(Request $request): JsonResponse
//        {
//            JWTAuth::user();
//
//            $validated = $request->validate([
//                'patient_id' => 'required|string|max:255',
//                'name' => 'required|string|max:255',
//                'gender' => 'required|string|max:255',
//                'age' => 'nullable|integer|min:0|max:120',
//                'birth_date' => 'required|date',
//                'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
//                'address' => 'required|string|max:255',
//                'emergency_contact' => 'required|string|max:255',
//                'blood_type' => 'required|string|max:255',
//                'allergy_history' => 'required|string|max:2000',
//                'medical_history' => 'required|string|max:2000',
//                'user_id' => 'required|string|max:255',
//            ]);
//
//           $patients =  Patient::addPatients($validated);
//           if($patients){
//               return json_success('患者信息添加成功', $patients, 200);
//           }else{
//               return json_fail('患者信息添加失败', $patients, 500);
//           }
//        }




        //CT分析图上传
//        public function upload(Request $request): JsonResponse
//        {
//            try {
//                // 从配置文件中读取OSS参数
//                $accessKeyId = config('oss.access_key_id');
//                $accessKeySecret = config('oss.access_key_secret');
//                $endpoint = config('oss.endpoint');
//                $bucket = config('oss.bucket');
//
//                // 验证请求中是否包含文件
//               $validated =  $request->validate([
//                    'file' => 'required|file|max:5242880', // 限制文件大小为5G
//                    'patient_id'  => 'required|string|max:255',
//                ]);
//
//                if(!Patient::where('patient_id', $validated['patient_id'])->exists()){
//                    return json_fail('患者ID不存在', $validated['patient_id'],404);
//                }
//
//                $file = $request->file('file');
//                $originalName = $file->getClientOriginalName();
//
//                // 生成唯一文件名，避免冲突
//                $filename = uniqid() . '_' . $originalName;
//                $object = 'uploads/' . $filename;
//
//                // 保存文件到本地临时目录
//                $localPath = $file->storeAs('temp', $filename, 'public');
//                $localFile = storage_path('app/public/' . $localPath);
//
//                // 创建OSSClient实例
//                $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//
//                // 检查Bucket是否存在，不存在则创建
//                if (!$ossClient->doesBucketExist($bucket)) {
//                    $ossClient->createBucket($bucket);
//                }
//
//                // 上传文件到OSS
//                $result = $ossClient->uploadFile($bucket, $object, $localFile);
//
//                // 安全获取ETag（兼容不同SDK版本）
//                $etag = $this->extractEtag($result);
//
//
//                // 无论上传成功或失败，确保删除本地临时文件
//                $this->cleanupLocalFile($localFile);
//
//                CtScan::create([
//                    'patient_id' => $validated['patient_id'],
//                    'image_url' => "https://{$bucket}.{$endpoint}/{$object}",
//                    ]);
//
//                return response()->json([
//                    'msg' => "上传oss成功并成功记录到数据库",
//                    'path' => "https://{$bucket}.{$endpoint}/{$object}",
//                    'etag' => $etag
//                ]);
//
//            } catch (OssException $e) {
//                // 确保异常发生时也删除本地临时文件
//                $this->cleanupLocalFile(@$localFile);
//
//                return response()->json([
//                    'msg' => "上传oss失败",
//                    'error' =>  $e->getMessage()
//                ], 500);
//            } catch (\Exception $e) {
//                // 确保异常发生时也删除本地临时文件
//                $this->cleanupLocalFile(@$localFile);
//
//                return response()->json([
//                    'msg' => "上传oss失败",
//                    'error' => '上传过程失败: ' . $e->getMessage()
//                ], 400);
//            }
//        }

    /**
     * 从OSS返回结果中安全提取ETag
     */
//    private function extractEtag(array $result)
//    {
//        // 检查常见的ETag位置
//        if (isset($result['ETag'])) {
//            return $result['ETag'];
//        }
//
//        if (isset($result['etag'])) {
//            return $result['etag'];
//        }
//
//        if (isset($result['info']['etag'])) {
//            return $result['info']['etag'];
//        }
//
//        return 'unknown';
//    }

    /**
     * 清理本地临时文件
     */
//    private function cleanupLocalFile($filePath)
//    {
//        if (!empty($filePath) && file_exists($filePath)) {
//            @unlink($filePath); // @符号抑制可能的错误
//        }
//    }



    //(影像分析页面)获取分析患者对象
    public function getPatients(Request $request): JsonResponse
    {
        try {
            $patients = Patient::query()->where('user_id', $request->user()->id);

            // 调用分页方法
            $paginatedPatients = $this->getPaginatedResults($patients, $request);

            $data = [
                'data' => $paginatedPatients->items(),
                'total' => $paginatedPatients->total(),
                'current_page' => $paginatedPatients->currentPage(),
                'last_page' => $paginatedPatients->lastPage(),
                'per_page' => $paginatedPatients->perPage()
            ];

            return json_success('获取分析患者对象成功', $data, 200);
        }catch (Exception $e) {
            return json_fail('获取分析患者对象失败', $e->getMessage(), 500);
        }
    }


    //搜索患者
    public function searchPatients(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'keyword' => 'required|string|max:60',
            ]);

            $keyword = trim($validated['keyword']);
            $query = Patient::query();

            // 关键修改：支持P0000格式ID（字母P开头+数字）
            if (preg_match('/^P\d+$/i', $keyword)) {
                $query->where('patient_id', $keyword); // 精确匹配P0000格式
            } else {
                $query->where('name', 'like', "%{$keyword}%"); // 姓名模糊搜索
            }

            $patients = $query->limit(10)->get();

            return response()->json([
                'data' => $patients,
                'count' => $patients->count(),
            ]);
        }catch (Exception $e) {
            return json_fail('搜索失败',$e->getMessage(),500);
        }
    }


    //选择患者
    public function selectPatients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'string', 'regex:/^P\d+$/', 'max:20']
        ]);

        $patient = Patient::with(['ctScans' => function($query) {
            $query->select('id', 'patient_id', 'image_url', 'created_at')
                ->orderBy('created_at', 'desc');
        }])
            ->where('patient_id', $validated['patient_id'])
            ->first(['patient_id', 'name', 'gender', 'birth_date', 'phone', 'address']);

        if (!$patient) {
            return json_fail('患者不存在', null, 404);
        }

        return json_success('选择患者成功',
            ['patient_info' => $patient],  200);
    }





// 上传分析结果图
//    public function uploadAnalysisImage(Request $request): JsonResponse
//    {
//        try {
//            // 从配置文件中读取OSS参数
//            $accessKeyId = config('oss.access_key_id');
//            $accessKeySecret = config('oss.access_key_secret');
//            $endpoint = config('oss.endpoint');
//            $bucket = config('oss.bucket');
//
//            // 验证请求中是否包含文件和 ct_scan_id
//            $validated = $request->validate([
//                'file' => 'required|file|max:5242880', // 限制文件大小为5MB
//                'ct_scan_id' => 'required|integer|exists:ct_scans,id',
//            ]);
//
//            $ctScan = CtScan::findOrFail($validated['ct_scan_id']);
//
//            $file = $request->file('file');
//            $originalName = $file->getClientOriginalName();
//
//            // 生成唯一文件名，避免冲突
//            $filename = uniqid() . '_' . $originalName;
//            $object = 'ct_analysis/' . $filename;
//
//            // 保存文件到本地临时目录
//            $localPath = $file->storeAs('temp', $filename, 'public');
//            $localFile = storage_path('app/public/' . $localPath);
//
//            // 创建OSSClient实例
//            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
//
//            // 检查Bucket是否存在，不存在则创建
//            if (!$ossClient->doesBucketExist($bucket)) {
//                $ossClient->createBucket($bucket);
//            }
//
//            // 上传文件到OSS
//            $result = $ossClient->uploadFile($bucket, $object, $localFile);
//
//            // 安全获取ETag
//            $etag = $this->extractEtag($result);
//
//            // 删除本地临时文件
//            $this->cleanupLocalFile($localFile);
//
//            // 更新或创建 analysis 记录
//            $analysis = Analysis::firstOrNew(['ct_scan_id' => $ctScan->id]);
//            $analysis->result_image_url = "https://{$bucket}.{$endpoint}/{$object}";
//            $analysis->save();
//
//            return response()->json([
//                'msg' => '分析结果图上传成功并成功记录到数据库',
//                'path' => $analysis->result_image_url,
//                'etag' => $etag
//            ]);
//
//        } catch (OssException $e) {
//            $this->cleanupLocalFile(@$localFile);
//            return response()->json([
//                'msg' => '上传OSS失败',
//                'error' => $e->getMessage()
//            ], 500);
//        } catch (\Exception $e) {
//            $this->cleanupLocalFile(@$localFile);
//            return response()->json([
//                'msg' => '上传过程失败',
//                'error' => '上传过程失败: ' . $e->getMessage()
//            ], 400);
//        }
//    }




}

