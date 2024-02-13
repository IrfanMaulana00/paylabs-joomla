<?php

class Paylabs_Config
{

  const SANDBOX_BASE_URL = 'https://sit-pay.paylabs.co.id';
  const PRODUCTION_BASE_URL = 'https://pay.paylabs.co.id';

  public static function getBaseUrl($mode)
  {
    return $mode == "sandbox" ?
      Paylabs_Config::SANDBOX_BASE_URL : Paylabs_Config::PRODUCTION_BASE_URL;
  }
}
