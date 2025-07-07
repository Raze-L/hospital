<?php

namespace App\Http\Controllers;

set_time_limit(60); // 设置为 60 秒，0 表示不限制
use App\Models\CTScan;
use App\Models\Analysis;
use Illuminate\Http\Request;
use App\Services\AliyunVisionService;
use Illuminate\Support\Facades\Storage;
use OSS\OssClient;
use OSS\Core\OssException;

class CTScanController extends Controller
{
    protected $aiAnalysisService;

    public function __construct(AliyunVisionService $aiAnalysisService)
    {
        $this->aiAnalysisService = $aiAnalysisService;
    }

    public function analyseCtScan(Request $request)
    {
        $validatedData = $request->validate([
            'ct_scan_id' => 'required|integer|exists:ct_scans,id',
        ]);

        $ctScan = CTScan::findOrFail($validatedData['ct_scan_id']);

        // 调用 AI 接口分析 CT 图
        $result = $this->aiAnalysisService->analyzeCTImage($ctScan->image_url);

        if ($result && isset($result['choices'][0]['message']['content'])) {
            $analysisResult = $result['choices'][0]['message']['content'];

            // 判断是否存在肿瘤
            $hasTumor = str_contains($analysisResult, '肿瘤') || str_contains($analysisResult, '占位性病变');

            // 根据判断选择本地图路径
            if ($hasTumor) {
                $localImagePath = "ct_analysis/{$ctScan->id}_tumor_marked.jpg";
                $ossImagePath = "ct/analyzed/{$ctScan->id}_tumor_marked.jpg";
            } else {
                $localImagePath = "ct_analysis/{$ctScan->id}_normal_enhanced.jpg";
                $ossImagePath = "ct/analyzed/{$ctScan->id}_normal_enhanced.jpg";
            }

            // 上传到 OSS
            $resultImageUrl = $this->uploadLocalImageToOss($localImagePath, $ossImagePath);

            // 提取文本内容
            $imageAnalysis = $this->extractImageAnalysis($analysisResult);
            $diagnosticOpinion = $this->extractDiagnosticOpinion($analysisResult);
            $treatmentRecommendation = $this->extractTreatmentRecommendation($analysisResult);

            // 保存到数据库
            $analysis = new Analysis();
            $analysis->ct_scan_id = $ctScan->id;
            $analysis->image_analysis = $imageAnalysis;
            $analysis->diagnostic_opinion = $diagnosticOpinion;
            $analysis->treatment_recommendation = $treatmentRecommendation;
            $analysis->result_image_url = $resultImageUrl;
            $analysis->save();

            // 更新 CT 扫描状态
            $ctScan->is_analysed = 'true';
            $ctScan->save();

            return response()->json([
                'message' => 'CT 图分析成功',
                'analysis' => $analysis,
            ]);
        } else {
            return response()->json(['message' => 'CT 图分析失败'], 500);
        }
    }


    private function extractImageAnalysis($text)
    {
        preg_match('/### 影像结果(.*?)(### 诊断意见|$)/s', $text, $matches);
        return trim($matches[1] ?? '');
    }

    private function extractDiagnosticOpinion($text)
    {
        preg_match('/### 诊断意见(.*?)(### 治疗建议|$)/s', $text, $matches);
        return trim($matches[1] ?? '');
    }

    private function extractTreatmentRecommendation($text)
    {
        preg_match('/### 治疗建议(.*?)(### 结论|$)/s', $text, $matches);
        return trim($matches[1] ?? '');
    }



    private function uploadLocalImageToOss($localPath, $ossPath): ?string
    {
        try {
            $localFullPath = storage_path('app/' . $localPath);

            if (!file_exists($localFullPath)) {
                \Log::error("本地文件不存在：{$localFullPath}");
                return null;
            }

            $accessKeyId = config('oss.access_key_id');
            $accessKeySecret = config('oss.access_key_secret');
            $endpoint = config('oss.endpoint');
            $bucket = config('oss.bucket');

            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            $ossClient->uploadFile($bucket, $ossPath, $localFullPath);

            return "https://{$bucket}.{$endpoint}/{$ossPath}";
        } catch (OssException $e) {
            \Log::error('上传 OSS 失败：' . $e->getMessage());
            return null;
        }
    }
}
