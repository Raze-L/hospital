<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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

    public function upload(Request $request): JsonResponse
    {
        try {
            // 从配置文件中读取OSS参数
            $accessKeyId = config('oss.access_key_id');
            $accessKeySecret = config('oss.access_key_secret');
            $endpoint = config('oss.endpoint');
            $bucket = config('oss.bucket');

            // 验证请求中是否包含文件
            $request->validate([
                'file' => 'required|file|max:5242880', // 限制文件大小为5G
                'patient_id' => 'required|string|max:255',
                'image_url' => 'required|string|max:255',
            ]);

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();

            // 生成唯一文件名，避免冲突
            $filename = uniqid() . '_' . $originalName;
            $object = 'uploads/' . $filename;

            // 保存文件到本地临时目录
            $localPath = $file->storeAs('temp', $filename, 'public');
            $localFile = storage_path('app/public/' . $localPath);

            // 创建OSSClient实例
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            // 检查Bucket是否存在，不存在则创建
            if (!$ossClient->doesBucketExist($bucket)) {
                $ossClient->createBucket($bucket);
            }

            // 上传文件到OSS
            $result = $ossClient->uploadFile($bucket, $object, $localFile);

            // 安全获取ETag（兼容不同SDK版本）
            $etag = $this->extractEtag($result);

            // 无论上传成功或失败，确保删除本地临时文件
            $this->cleanupLocalFile($localFile);

            return response()->json([
                'msg' => "上传oss成功",
                'path' => "https://{$bucket}.{$endpoint}/{$object}",
                'etag' => $etag
            ]);

        } catch (OssException $e) {
            // 确保异常发生时也删除本地临时文件
            $this->cleanupLocalFile(@$localFile);

            return response()->json([
                'msg' => "上传oss失败",
                'error' =>  $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // 确保异常发生时也删除本地临时文件
            $this->cleanupLocalFile(@$localFile);

            return response()->json([
                'msg' => "上传oss失败",
                'error' => '上传过程失败: ' . $e->getMessage()
            ], 400);
        }
    }



    /**
     * 从OSS返回结果中安全提取ETag
     */
    private function extractEtag(array $result)
    {
        // 检查常见的ETag位置
        if (isset($result['ETag'])) {
            return $result['ETag'];
        }

        if (isset($result['etag'])) {
            return $result['etag'];
        }

        if (isset($result['info']['etag'])) {
            return $result['info']['etag'];
        }

        return 'unknown';
    }

    /**
     * 清理本地临时文件
     */
    private function cleanupLocalFile($filePath)
    {
        if (!empty($filePath) && file_exists($filePath)) {
            @unlink($filePath); // @符号抑制可能的错误
        }
    }

}
