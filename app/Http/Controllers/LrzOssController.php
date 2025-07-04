<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OSS\OssClient;
use OSS\Core\OssException;

class LrzOssController extends Controller
{
    public function LrzUploadImg(Request $request): JsonResponse
    {
        // 验证请求：支持多文件上传 + 患者ID
        $request->validate([
            'files.*' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 多文件验证
            'patientId' => 'required|exists:patients,patient_id',
        ]);

        // 检查是否有文件上传
        if (!$request->hasFile('files')) {
            return response()->json([
                'code' => 400,
                'message' => '未上传任何文件',
            ], 400);
        }

        try {
            // OSS 配置
            $accessKeyId = config('services.oss.access_key_id');
            $accessKeySecret = config('services.oss.access_key_secret');
            $endpoint = config('services.oss.endpoint');
            $bucket = config('services.oss.bucket');

            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $patientId = $request->input('patientId');
            $patient = Patient::where('patient_id', $patientId)->firstOrFail();

            $uploadedFiles = [];
            $files = $request->file('files'); // 获取所有文件

            // 遍历处理每张图片
            foreach ($files as $file) {
                $fileName = $file->getClientOriginalName();
                $object = "ct_scans/{$patientId}/" . uniqid() . '_' . $fileName;

                // 直接读取文件内容并上传到 OSS
                $content = file_get_contents($file->getPathname());
                $ossClient->putObject($bucket, $object, $content);
                $imageUrl = "https://{$bucket}.{$endpoint}/{$object}";

                // 关联患者并创建 CT 记录
                $ctScan = $patient->ctScans()->create([
                    'image_url' => $imageUrl,
                    'patient_id' => $patientId,
                ]);

                $uploadedFiles[] = [
                    'fileName' => $fileName,
                    'ossPath' => $object,
                    'ossUrl' => $imageUrl,
                    'ctScanId' => $ctScan->id,
                ];
            }

            return response()->json([
                'code' => 200,
                'message' => 'CT 图像上传成功',
                'data' => [
                    'patientId' => $patientId,
                    'uploadedFiles' => $uploadedFiles,
                    'total' => count($uploadedFiles),
                ]
            ]);

        } catch (OssException $e) {
            return response()->json([
                'code' => 500,
                'message' => 'OSS 上传失败',
                'error' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '服务器错误',
                'error' => $e->getMessage(),
            ], 500);
        } finally {

            if (isset($files)) {
                foreach ($files as $file) {
                    if ($file && file_exists($file->getPathname())) {
                        @unlink($file->getPathname());
                    }
                }
            }
        }
    }
}
