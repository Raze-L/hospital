<?php

// config/oss.php
return [
    'access_key_id' => env('OSS_ACCESS_KEY_ID'),
    'access_key_secret' => env('OSS_ACCESS_KEY_SECRET'),
    'endpoint' => env('OSS_ENDPOINT'),
    'bucket' => env('OSS_BUCKET'),
    'default_object_prefix' => 'uploads/', // 可选：默认文件路径前缀
];
