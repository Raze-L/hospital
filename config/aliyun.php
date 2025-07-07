<?php

return [
    'access_key_id' => env('ALIYUN_ACCESS_KEY_ID'),
    'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET'),
    'region_id' => env('ALIYUN_REGION_ID', 'cn-hangzhou'),

    'vision' => [
        'endpoint' => 'vision.cn-hangzhou.aliyuncs.com',
        'api_version' => '2018-12-30',
    ],

];
