<?php

namespace Ekuwang\Weffic;

use Closure;

class Weffic extends Receiver
{
    private $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    private $message;

    public function dispatch($message)
    {
        app('log')->debug('receive', [$message]);
        $this->message = $message;
        $response = null;

        list($middlewares, $receivers) = $this->filterRegister($message);

        app('log')->debug('pipes', [$middlewares, $receivers]);

        $response = $this->throughMiddlewares($middlewares, function ($message) use ($receivers) {
            return $this->throughReceivers($message, $receivers);
        });

        app('log')->debug('response', [$response]);

        if (!empty($response['group']) && !empty($response['remember'])) {
            app('cache')->put($this->getCacheKey($message->ToUserName, $message->FromUserName), $response['group'], 360);
        } else {
            app('cache')->forget($this->getCacheKey($message->ToUserName, $message->FromUserName));
        }

        return $response ?? null;
    }

    private function throughMiddlewares($middlewares, $destination)
    {
        $middlewares = array_reverse($middlewares);
        return call_user_func(array_reduce($middlewares, function ($stack, $middleware) {
            return function ($message) use ($stack, $middleware) {
                return $this->call($middleware['middleware'], [$message, $stack]);
            };
        }, $destination), $this->message);
    }

    private function throughReceivers($message, $receivers)
    {
        foreach ($receivers as $key => $value) {
            $response = $this->call($value['receiver'], [$message]);

            if ($response) {
                if (empty($response['data'])) {
                    $response = $this->reply($response);
                }

                if (!empty($response['data'])) {
                    $response['group'] = $value['group'] ?? '';

                    return $response;
                }
            }
        }

        return null;
    }

    private function getCacheKey($toUserName, $fromUserName)
    {
        $key = $toUserName . '-' . $fromUserName . '-last-trigger-weixin-message-receiver';
        return $key;
    }

    private function filterRegister($message)
    {
        $config = $this->config;

        $previous = app('cache')->get($this->getCacheKey($message->ToUserName, $message->FromUserName));

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
        $middlewares = [];
        foreach ($register_list as $key => $list) {
            if (count($list)) {
                $subMiddlewares = [];
                foreach ($list as $value) {
                    // 公众号ID与当前消息不匹配
                    if (!empty($value['mpid']) && !in_array($message->ToUserName, is_array($value['mpid']) ? $value['mpid'] : [$value['mpid']])) {
                        continue;
                    }

                    // 中间件
                    if (is_array($value) && !empty($value['middleware'])) {
                        array_push($subMiddlewares, $value);
                        continue;
                    }

                    if (!is_array($value)) {
                        $value = ['receiver' => $value, 'priority' => 0];
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
                    $priority[] = $value['priority'] ?? 0;
                    $index[] = count($receivers) - 1;
                    $level[] = $key;
                }

                $middlewares = array_merge($subMiddlewares, $middlewares);

            }
        }

        array_multisort($group, SORT_DESC, $level, SORT_ASC, $priority, SORT_DESC, $index, SORT_ASC, $receivers);

        if ($this->config['default_handler']) {
            array_push($receivers, ['receiver' => $config['default_handler']]);
        }

        return array($middlewares, $receivers);
    }

    private function call($callable, $params)
    {
        if ($callable instanceof Closure) {
            return call_user_func_array($callable, $params);
        } elseif (is_string($callable)) {
            if (!str_contains($callable, '@')) {
                $callable .= '@receive';
            }

            list($controller, $method) = explode('@', $callable);

            if (!class_exists($controller) || !method_exists($instance = new $controller, $method)) {
                return null;
            }

            return call_user_func_array([$instance, $method], $params) ?? null;
        }

        return null;
    }
}
