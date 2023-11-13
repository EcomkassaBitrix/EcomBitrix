<?php
define('C_REST_CLIENT_ID','');//Application ID
define('C_REST_CLIENT_SECRET','');//Application key

define('C_REST_MYSQL_DBNAME','');//
define('C_REST_MYSQL_USERNAME','');//
define('C_REST_MYSQL_PASSWORD','');//

define('C_REST_FIELD_KASSANAME','URLFORPAYECOMKASSA');//
define('C_REST_FIELD_PAYNAME','Ссылка оплаты EcomKassa');//

try {
    $db = new PDO('mysql:host=localhost;dbname='.C_REST_MYSQL_DBNAME, C_REST_MYSQL_USERNAME, C_REST_MYSQL_PASSWORD);
} catch (PDOException $e) {
    die();
}



// or
//define('C_REST_WEB_HOOK_URL','https://rest-api.bitrix24.com/rest/1/doutwqkjxgc3mgc1/');//url on creat Webhook

//define('C_REST_CURRENT_ENCODING','windows-1251');
//define('C_REST_IGNORE_SSL',true);//turn off validate ssl by curl
//define('C_REST_LOG_TYPE_DUMP',true); //logs save var_export for viewing convenience
//define('C_REST_BLOCK_LOG',true);//turn off default logs
//define('C_REST_LOGS_DIR', __DIR__ .'/logs/'); //directory path to save the log