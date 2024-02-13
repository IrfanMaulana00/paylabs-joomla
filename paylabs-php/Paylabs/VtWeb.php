<?php

class Paylabs_VtWeb
{

  public static function generateHash($privateKey, $body, $path, $date)
  {
    $privateKey = str_replace('\/', '/', $privateKey);
    $privateKey = str_replace('\r\n', "\r\n", $privateKey);

    if (openssl_pkey_get_private($privateKey) === false) {
      return (object) ['status' => false, 'desc' => 'Private key not valid.'];
    }

    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $shaJson  = strtolower(hash('sha256', $jsonBody));
    $signatureBefore = "POST:" . $path . ":" . $shaJson . ":" . $date;
    $binary_signature = "";

    $algo = OPENSSL_ALGO_SHA256;
    openssl_sign($signatureBefore, $binary_signature, $privateKey, $algo);
    $signature = base64_encode($binary_signature);

    return (object) ['status' => true, 'sign' => $signature];
  }

  public static function createTrascation($url, $body, $sign, $date)
  {

    $headers = array(
      'X-TIMESTAMP:' . $date,
      'X-SIGNATURE:' . $sign,
      'X-PARTNER-ID:' . $body['merchantId'],
      'X-REQUEST-ID:' . $body['requestId'],
      'Content-Type:application/json;charset=utf-8'
    );

    $response = Paylabs_ApiRequestor::remoteCall($url, $headers, $body);

    return $response;
  }

  public static function validateTransaction($publicKey, $signature, $dataToSign, $date)
  {
    $publicKey = str_replace('\/', '/', $publicKey);
    $publicKey = str_replace('\r\n', "\r\n", $publicKey);

    $binary_signature = base64_decode($signature);
    $dataToSign = json_encode(json_decode($dataToSign), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $shaJson  = strtolower(hash('sha256', $dataToSign));
    $signatureAfter = "POST:/index.php:" . $shaJson . ":" . $date;
    $publicKey = openssl_pkey_get_public($publicKey);

    if ($publicKey === false) {
      die("Error loading public key");
    }

    $algo =  OPENSSL_ALGO_SHA256;
    $verificationResult = openssl_verify($signatureAfter, $binary_signature, $publicKey, $algo);

    if ($verificationResult === 1) {
      return true;
    } elseif ($verificationResult === 0) {
      return false;
    } else {
      die("Error while verifying the signature.");
    }
  }
}
