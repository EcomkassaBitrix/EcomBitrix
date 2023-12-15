<?php
//define('C_REST_CLIENT_ID','local.6549e8077cb383.12820727');//Application ID
//define('C_REST_CLIENT_SECRET','cp9a4Smv237bTAu64N9AG6UUOcokbYJeT9gQyIgATn9IUHm0Pq');//Application key
define('C_REST_CLIENT_ID','app.657ad879d77408.57395847');//Application ID
define('C_REST_CLIENT_SECRET','xzLeOmfBmWh3bUfivOHIg4Ec4l9cmeOkxAUDssA7DBigtSAjSg');//Application key

define('C_REST_MYSQL_DBNAME','ecomkassa');//
define('C_REST_MYSQL_USERNAME','root');//
define('C_REST_MYSQL_PASSWORD','YEXprx62868');//
define('C_REST_MYSQL_HOST','node165261-ecomkassa-bitrix.mircloud.ru');//

define('C_REST_FIELD_KASSANAME','URLFORPAYECOMKASSA');//
define('C_REST_FIELD_PAYNAME','Ссылка оплаты EcomKassa');//

define('C_REST_BLOCK_LOG',true);//

try {
    $db = new PDO('mysql:host='.C_REST_MYSQL_HOST.';dbname='.C_REST_MYSQL_DBNAME, C_REST_MYSQL_USERNAME, C_REST_MYSQL_PASSWORD);
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