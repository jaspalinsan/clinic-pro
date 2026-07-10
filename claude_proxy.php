<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}

$body=file_get_contents('php://input');
$data=json_decode($body,true);
if(!$data){http_response_code(400);echo json_encode(['error'=>'Invalid JSON']);exit;}

// Read API key from config file (keep outside webroot ideally)
$apiKey=file_exists(__DIR__.'/claude_key.txt')?trim(file_get_contents(__DIR__.'/claude_key.txt')):'';

$ch=curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>$body,
  CURLOPT_HTTPHEADER=>[
    'Content-Type: application/json',
    'x-api-key: '.$apiKey,
    'anthropic-version: 2023-06-01'
  ],
  CURLOPT_TIMEOUT=>30
]);
$result=curl_exec($ch);
$httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
http_response_code($httpCode);
echo $result;
