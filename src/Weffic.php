<?php

namespace Ekuwang\Weffic;

use Closure;

class Weffic
{
    private $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    private $message;

    public function dispatch($message)
    {
        $this->message = $message;
        $config = $this->config;
        $response = null;

        $response = $this->throughMiddlewares($message);

        if (empty($response) && $config['default_handler']) {
            $response = $this->call($config['default_handler']);
        }

        return $response ?? null;
    }

    private function throughMiddlewares($message)
    {
        $response = $this->throughReceivers($message);
        return $response ?? null;
    }

    private function throughReceivers($message)
    {
        $config = $this->config;

        $previous = app('cache')->get($message->ToUserName . '-' . $message->FromUserName . '-last-trigger-weixin-message-receiver');
        app('log')->debug($previous);

        $register_list = [
            $config['register_message'] ?? [],
        ];

        switch ($message->MsgType) {
            case 'event':
                array_unshift($register_list, $config['register_message_event'] ?? []);

                switch ($message->Event) {
                    case 'subscribe':
                        array_unshift($register_list, $config['register_message_event_subscribe'] ?? []);
                        break;

                    case 'unsubscribe':
                        array_unshift($register_list, $config['register_message_event_unsubscribe'] ?? []);
                        break;

                    case 'SCAN':
                        array_unshift($register_list, $config['register_message_event_SCAN'] ?? []);
                        break;

                    case 'LOCATION':
                        array_unshift($register_list, $config['register_message_event_LOCATION'] ?? []);
                        break;

                    case 'CLICK':
                        array_unshift($register_list, $config['register_message_event_CLICK'] ?? []);
                        break;

                    case 'VIEW':
                        array_unshift($register_list, $config['register_message_event_VIEW'] ?? []);
                        break;

                    default:
                        array_unshift($register_list, $config['register_message_event_other'] ?? []);
                        break;
                }
                break;

            case 'text':
                array_unshift($register_list, $config['register_message_text'] ?? []);
                break;

            case 'image':
                array_unshift($register_list, $config['register_message_image'] ?? []);
                break;

            case 'voice':
                array_unshift($register_list, $config['register_message_voice'] ?? []);
                break;

            case 'video':
                array_unshift($register_list, $config['register_message_video'] ?? []);
                break;

            case 'shortvideo':
                array_unshift($register_list, $config['register_message_shortvideo'] ?? []);
                break;

            case 'location':
                array_unshift($register_list, $config['register_message_location'] ?? []);
                break;

            case 'link':
                array_unshift($register_list, $config['register_message_link'] ?? []);
                break;

            // ... 其它消息
            default:
                array_unshift($register_list, $config['register_message_other'] ?? []);
                break;
        }

        $group = [];
        $level = [];
        $index = [];
        $priority = [];
        $receivers = [];
        foreach ($register_list as $key => $list) {
            if (count($list)) {
                foreach ($list as $value) {
                    if (!is_array($value)) {
                        $value = ['receiver' => $value, 'priority' => 0];
                    }

                    // 跳过中间件
                    if (!empty($value['middleware'])) {
                        continue;
                    }

                    // 公众号ID与当前消息不匹配
                    if (!empty($value['mpid']) && !in_array($message->ToUserName, is_array($value['mpid']) ? $value['mpid'] : [$value['mpid']])) {
                        continue;
                    }

                    // 不是扫码关注
                    if ($message->MsgType == 'event' && $message->Event == 'subscribe' && !empty($value['onlyScan']) && empty($message->EventKey)) {
                        continue;
                    }

                    if ($message->MsgType == 'text' && (!empty($value['keyword']) || !empty($value['pattern']))) {
                        $matched = false;

                        if (!empty($value['keyword'])) {
                            $keywords = is_string($value['keyword']) ? [$value['keyword']] : $value['keyword'];
                            if (in_array($message->Content, $keywords)) {
                                $matched = true;
                            }
                        }

                        if (!$matched && !empty($value['pattern'])) {
                            $patterns = is_string($value['pattern']) ? [$value['pattern']] : $value['pattern'];
                            foreach ($patterns as $pattern) {
                                if (preg_match($pattern, $message->Content)) {
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if (!$matched) {
                            continue;
                        }
                    }

                    if (isset($value['eventKey']) && $message->EventKey) {
                        $keys = is_string($value['eventKey']) ? [$value['eventKey']] : $value['eventKey'];
                        if (!in_array($message->EventKey, $keys)) {
                            continue;
                        }
                    }

                    if (!empty($previous) && !empty($value['group']) && $previous == $value['group']) {
                        $group[] = 1;
                    } else {
                        $group[] = 0;
                    }

                    array_push($receivers, $value);
                    $priority[] = $value['priority'];
                    $index[] = count($receivers) - 1;
                    $level[] = $key;
                }
            }
        }

        app('log')->debug(json_encode([$priority, $level, $index, $receivers, $register_list]));

        array_multisort($group, SORT_DESC, $level, SORT_ASC, $priority, SORT_DESC, $index, SORT_ASC, $receivers);

        foreach ($receivers as $key => $value) {
            $response = $this->call($value['receiver']);

            if ($response) {
                if (!empty($value['group'])) {
                    app('cache')->put($message->ToUserName . '-' . $message->FromUserName . '-last-trigger-weixin-message-receiver', $value['group'], 1);
                } else {
                    app('cache')->forget($message->ToUserName . '-' . $message->FromUserName . '-last-trigger-weixin-message-receiver');
                }
                return $response;
            }
        }
    }

    private function call($callable)
    {
        if ($callable instanceof Closure) {
            return call_user_func($callable, $this->message);
        } elseif (is_string($callable)) {
            if (!str_contains($callable, '@')) {
                $callable .= '@receive';
            }

            list($controller, $method) = explode('@', $callable);

            if (!class_exists($controller) || !method_exists($instance = new $controller, $method)) {
                return null;
            }

            return call_user_func([$instance, $method], $this->message) ?? null;
        }

        return null;
    }
}
