<?php

class Paylabs_ApiRequestor
{

  public static function remoteCall($url, $headers, $body)
  {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLINFO_HEADER_OUT => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      CURLOPT_HTTPHEADER => $headers,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    if ($result === FALSE) {
      throw new Exception('CURL Error: ' . curl_error($curl), curl_errno($curl));
    } else {
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $response = json_decode($result);
      if ($httpcode != 200) {
        $message = 'Paylabs Error (' . $result . '): ';
        throw new Exception($message, $httpcode);
      } else {
        return $response;
      }
    }
  }
}
