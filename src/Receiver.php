<?php

namespace Ekuwang\Weffic;

class Receiver
{
    protected function reply($data)
    {
        return [
            'remember' => true,
            'data' => $data,
        ];
    }

    protected function finish($data)
    {
        return [
            'remember' => false,
            'data' => $data,
        ];
    }
}
