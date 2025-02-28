<?php

/**
 *  临时跳过签名用的验证器
 *  by:yunke
 *  email:phpworld@qq.com
 *  time:20210505
 */

namespace Drupal\yunke_pay\Certificate;

use WechatPay\GuzzleMiddleware\Validator;
use Psr\Http\Message\ResponseInterface;


class NoopValidator implements Validator
{
  public function validate(ResponseInterface $response)
  {
    return true;
  }
}
