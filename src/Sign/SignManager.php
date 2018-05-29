<?php

namespace luffyzhao\laravelTools\Sign;

use Carbon\Carbon;
use luffyzhao\laravelTools\Sign\Drivers\Md5Sign;
use luffyzhao\laravelTools\Sign\Drivers\RsaSign;
use luffyzhao\laravelTools\Sign\Exceptions\SignException;
use Illuminate\Http\Request;

class SignManager
{
    /**
     * 加签.
     *
     * @method sign
     *
     * @param Request $request  [description]
     * @param string  $signType [description]
     *
     * @return array [description]
     *
     * @author luffyzhao@vip.126.com
     */
    public function sign(Request $request, $signType = 'md5'): array
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $data = collect($request->except(['_sign', '_sign_type']))->put('_timestamp', $timestamp)->all();

        return [
          '_sign' => $this->signObj($signType)->sign($data),
          '_sign_type' => $signType,
          '_timestamp' => $timestamp,
        ];
    }

    /**
     * 验证
     *
     * @method validate
     *
     * @param Request $request [description]
     *
     * @return bool [description]
     *
     * @author luffyzhao@vip.126.com
     */
    public function validate(Request $request): bool
    {
        $data = $request->except(['_sign', '_sign_type']);
        $header = $this->validateParams($request);

        return $this->signObj($header['_sign_type'])->verify($data, $header['_sign']);
    }

    /**
     * 验证参数.
     *
     * @method validateParams
     *
     * @param Request $request [description]
     *
     * @return array [description]
     *
     * @author luffyzhao@vip.126.com
     */
    protected function validateParams(Request $request): array
    {
        $data = collect($request->header())->only('_sign', '_sign_type', '_timestamp');
        if ($data->isEmpty()) {
            throw new SignException('_sign and _sign_type and _timestamp must be filled in');
        }

        return $data->each(function ($item, $key) {
            if (!is_string($item) || empty($item)) {
                throw new SignException($key.' must be filled in');
            }
            if ('_timestamp' === $key && !$this->validateTimestamp($item)) {
                throw new SignException('request time out');
            }
        })->all();
    }

    /**
     * 验证时间.
     *
     * @method validateTimestamp
     *
     * @param $timestamp
     *
     * @return bool
     *
     * @author luffyzhao@vip.126.com
     */
    protected function validateTimestamp($timestamp)
    {
        return !empty($timestamp) && Carbon::parse($timestamp)->diffInRealSeconds() > 60;
    }

    /**
     * 获取加签对象
     *
     * @method signObj
     *
     * @param $signType
     *
     * @return Md5Sign|RsaSign
     *
     * @throws SignException
     *
     * @author luffyzhao@vip.126.com
     */
    protected function signObj($signType)
    {
        switch (strtoupper($signType)) {
            case 'MD5':
                $signObj = new Md5Sign();
                break;
            case 'RSA':
                $signObj = new RsaSign();
                break;
            default:
                throw new SignException('sign type must be filled in');
        }

        return $signObj;
    }
}