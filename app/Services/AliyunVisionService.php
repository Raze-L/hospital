<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AliyunVisionService
{
    protected $client;
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('app.dashscope_api_key');
        $this->apiUrl = config('app.dashscope_api_url');
    }

    public function analyzeCTImage($imageUrl)
    {
        try {
            // 根据 AI 接口要求构造请求参数
            $params = [
                'model' => 'qwen-vl-max',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => $imageUrl
                            ],
                            [
                                'type' => 'text',
                                'text' => '请分析这张CT图像，并提供影像结果、诊断意见、治疗建议。'
                            ]
                        ]
                    ]
                ]
            ];

            // 发送请求到 AI 接口
            $response = $this->client->post($this->apiUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $params,
            ]);

            // 解析 AI 接口返回的结果
            $result = json_decode($response->getBody(), true);
            return $result;
        } catch (\Exception $e) {
            Log::error('AI 接口调用失败: ' . $e->getMessage());
            return null;
        }
    }
}
