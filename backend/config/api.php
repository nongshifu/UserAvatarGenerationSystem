<?php
// 豆包 API 与默认配置（运行时可被 site_settings 表覆盖）
return [
    'ark_api_key' => '',                                  // 豆包 API Key（必填，建议放环境变量 ARK_API_KEY）
    'ark_model'   => 'doubao-seedream-5-0-260128',
    'ark_base_url'=> 'https://ark.cn-beijing.volces.com/api/v3',
    'image_size'  => '2k',   // 豆包要求小写：1k/2k/3k/4k 或 宽x高
    'watermark'   => true,
];
