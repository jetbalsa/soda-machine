<?php
require "vendor/autoload.php";
function logtodiscord($message)
{
    $msg = $message;
    $url = "https://discord.com/api/webhooks/xxxx/slack";
    $useragent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
    $payload = 'payload={"username": "SODA", "text": "'.$msg.'"}';
     
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent); //set our user agent
    curl_setopt($ch, CURLOPT_POST, TRUE); //set how many paramaters to post
    curl_setopt($ch, CURLOPT_URL,$url); //set the url we want to use
    curl_setopt($ch, CURLOPT_POSTFIELDS,$payload); 
     
    curl_exec($ch); //execute and get the results
    curl_close($ch);
    echo PHP_EOL.$message.PHP_EOL;
}
