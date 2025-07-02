<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OSS\OssClient;
use OSS\Core\OssException;
use Tymon\JWTAuth\Facades\JWTAuth;

class OssController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth'); // 确保所有请求都经过 JWT 认证
    }

    public function upload(Request $request)
    {
        // JWT 验证已在中间件中完成
        $user = auth()->user(); // 获取当前用户

        try {
            // 获取 OSS 配置
            $accessKeyId = config('services.oss.access_key_id');
            $accessKeySecret = config('services.oss.access_key_secret');
            $endpoint = config('services.oss.endpoint');
            $bucket = config('services.oss.bucket');

            // 创建 OSS 客户端
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            // 处理上传文件
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->getPathname();

            // 生成唯一文件名
            $object = uniqid() . '_' . $fileName;

            // 上传到 OSS
            $ossClient->uploadFile($bucket, $object, $filePath);

            return response()->json([
                'code' => 200,
                'message' => '上传成功',
                'data' => [
                    'fileName' => $fileName,
                    'ossPath' => $object,
                    'ossUrl' => "https://{$bucket}.{$endpoint}/{$object}"
                ]
            ]);
        } catch (OssException $e) {
            return response()->json([
                'code' => 500,
                'message' => 'OSS 上传失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
