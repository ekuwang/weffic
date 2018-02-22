<?php

return [

    'default_handler' => null,

    'register_message' => [
        // function ($message) {

        // },
        // App\Weixin\Common::class,
        // 'App\Weixin\Common@onMessage',
        // [
        //     'receiver' => 'App\Weixin\Common@onMessage',
        //     'priority' => 2,
        //     'mpid' => '', // 指定公众号id，可以是array
        // ],
    ],

    'register_message_text' => [
        // [
        //     'receiver' => 'App\Weixin\Common@onText',
        //     'priority' => 2,
        //     'mpid' => '', // 指定公众号id，可以是array
        //     'keyword' => '', // 指定关键词，可以是array
        //     'pattern' => '', // 指定正则表达式，可以是array
        // ],
    ],

    'register_message_image' => [

    ],

    'register_message_voice' => [

    ],

    'register_message_video' => [

    ],

    'register_message_shortvideo' => [

    ],

    'register_message_location' => [

    ],

    'register_message_link' => [

    ],

    'register_message_other' => [

    ],

    'register_message_event' => [

    ],

    'register_message_event_subscribe' => [
        // [
        //     'receiver' => 'App\Weixin\Common@subscribe',
        //     'priority' => 0,
        //     'mpid' => '', // 指定公众号id，可以是array
        //     'onlyScan' => false, // 只接受扫码关注事件
        // ],
    ],

    'register_message_event_unsubscribe' => [

    ],

    'register_message_event_SCAN' => [

    ],

    'register_message_event_LOCATION' => [

    ],

    'register_message_event_CLICK' => [
        // [
        //     'receiver' => 'App\Weixin\Common@onClick',
        //     'priority' => 0,
        //     'mpid' => '', // 指定公众号id，可以是array
        //     'eventKey' => '', // 指定EventKey，可以是array
        // ],
    ],

    'register_message_event_VIEW' => [

    ],

    'register_message_event_other' => [

    ],

];
