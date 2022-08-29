<?php
require 'Medoo.php';

// Using Medoo namespace.
use Medoo\Medoo;

// Connect the database.
$database = new Medoo([
    	'type' => 'sqlite',
    	'database' => '../database.db'
]);
$MACPREFIX = "FC:CF:62";

$vminfo = $database->select("types", "*");
foreach($vminfo as $vm){
    $database->insert("queue", [
        "serverip" => mt_rand(10,250) . "." . mt_rand(10,250) . "." . mt_rand(10,250). "." . mt_rand(10,250),
        "type" => $vm["id"],
        "toraddr" => "jamie3vkiwibfiwucd6vxijskbhpjdyajmzeor4mc4i7yopvpo4p7cyd.onion",
        "username" => "login",
        "password" =>  mt_rand(1000,9999).mt_rand(1000,9999).mt_rand(1000,9999),
        "uuid" => trim(file_get_contents("/proc/sys/kernel/random/uuid")),
        "timestamp" => time(),
        "macaddr" => strtoupper(implode(':', str_split(substr(sha1(random_bytes(5)), 0, 12), 2))),
        "ipaddr" => mt_rand(10,250) . "." . mt_rand(10,250) . "." . mt_rand(10,250). "." . mt_rand(10,250)
    ]);
}