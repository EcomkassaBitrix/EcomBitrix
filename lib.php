<?php
require_once (__DIR__.'/crest.php');
require_once (__DIR__.'/settings.php');


function bxSettingsPaySystemHandler( $memberId, $secretCode ){
    return
        ['SETTINGS' => [ 											// Настройки обработчика
            'CURRENCY' => ['RUB'], 								// Список валют, которые поддерживает обработчик
            'CHECKOUT_DATA' => [									// Настройки формы
                'ACTION_URI' => 'https://ecomkassa-bitrix.mircloud.ru/pay.php', 	// URL, на который будет отправляться форма
                'METHOD' => 'POST', 								// Метод отправки формы
                'FIELDS' => [
                    'EMAIL' => [
                        'CODE' => [
                            'NAME' => 'Email для чека',
                            'TYPE' => 'STRING',
                        ],
                        'VISIBLE' => 'Y'
                    ],
                    'ORDER_ID' => [
                        'CODE' => 'ORDER_ID'
                    ],
                    'USER_EMAIL' => [
                        'CODE' => 'USER_EMAIL'
                    ],
                    'PAYMENT_ID' => [
                        'CODE' => 'PAYMENT_ID'
                    ],
                    'MEMBER_ID' => [
                        'CODE' => 'MEMBER_ID'
                    ],
                    'ECOM_SECRET' => [
                        'CODE' => 'ECOM_SECRET'
                    ],
                    'TYPE_PAYSYSTEM' => [
                        'CODE' => 'TYPE_PAYSYSTEM'
                    ]
                ]
            ],
            'CODES' => [
                'ORDER_ID' => [
                    'NAME' => 'Номер заказа',
                    'DESCRIPTION' => 'Номер заказа',
                    'SORT' => 100,
                    'DEFAULT' => [
                        'PROVIDER_KEY' => 'ORDER',
                        'PROVIDER_VALUE' => 'ID'
                    ]
                ],
                'USER_EMAIL' => [
                    'NAME' => 'Email пользователя',
                    'DESCRIPTION' => 'Email пользователя',
                    'SORT' => 100,
                    'DEFAULT' => [
                        'PROVIDER_KEY' => 'USER',
                        'PROVIDER_VALUE' => 'EMAIL'
                    ]
                ],
                'PAYMENT_ID' => [
                    'NAME' => 'Номер платежа',
                    'DESCRIPTION' => 'Номер платежа',
                    'SORT' => 200,
                    'GROUP' => 'PAYMENT',
                    'DEFAULT' => [
                        'PROVIDER_KEY' => 'PAYMENT',
                        'PROVIDER_VALUE' => 'ID'
                    ]
                ],
                'MEMBER_ID' => [
                    'SORT' => 300,
                    'DEFAULT' => [
                        'PROVIDER_KEY' => 'VALUE',
                        'PROVIDER_VALUE' => $memberId
                    ]
                ],
                'ECOM_SECRET' => [
                    'SORT' => 400,
                    'DEFAULT' => [
                        'PROVIDER_KEY' => 'VALUE',
                        'PROVIDER_VALUE' => $secretCode
                    ]
                ],
                'TYPE_PAYSYSTEM' => [
                    'SORT' => 500,
                    'NAME' => 'ID type pay system',
                    'DESCRIPTION' => 'ID type pay system',
                    'INPUT' => [
                        'TYPE' => 'STRING'
                    ]
                ]
            ]
        ]
    ];
}
function getPaySystemIcon( $paySystemId ){
    global $db;
    $returnCode = 'iVBORw0KGgoAAAANSUhEUgAAASQAAACmCAID...';
    $stmt = $db->prepare("SELECT * FROM logo WHERE `id` = ?");
    $stmt->execute([$paySystemId]);
    $logoid = $stmt->fetch(PDO::FETCH_LAZY);
    if( $logoid['id'] > 0 ) {
        $returnCode = $logoid['codeBase'];
    }
    return $returnCode;
}
function SendLog( $message ){
    global $db;
    if( strlen($message) > 0 ){
        $query = "INSERT INTO `logs` ( `logtxt`, `unix` ) VALUES (:logtxt,:unix)";
        $params = [
            ':logtxt' => $message,
            ':unix' => time()
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }
}
/*
 * Функции bitrix
 */
/*
 *  Получаем ид физического лица в системе
 */
function bxLogDeal( $memberId, $dealId, $textLog ){
    CRest::call(
        "crm.timeline.comment.add",$memberId,
        [
            'fields'=> [
                "ENTITY_ID"=> $dealId,
                "ENTITY_TYPE"=> "deal",
                "COMMENT"=> $textLog
            ]
        ]
    );
}
function bxGetAllPaySystem( $memberId ){
    $salePaysystemList = CRest::call( "sale.paysystem.list", $memberId );
    if( !isset( $salePaysystemList['result'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав");
        exit;
    }
    return $salePaysystemList;
}
function bxSalePaySystemAdd( $memberId, $codeHandler, $personTypeId, $namePay, $paySystemId, $dataPaySystem ){
    $paysystemCheck = false;
    $sendActiveId = -1;
    foreach ( $dataPaySystem['result'] as $value ) {
        if( $value['ACTION_FILE'] == $codeHandler && $value['PERSON_TYPE_ID'] == $personTypeId && $value['NAME'] == $namePay ){
            $paysystemCheck = true;
            if( $value['ACTIVE'] != "Y" ){
                $sendActiveId = $value['ID'];
            }
        }
    }
    if( $paysystemCheck == false ){
        return [
                'NAME' => $namePay,                    // Название платежной системы
                'PERSON_TYPE_ID' => $personTypeId,                             // ID типа плательщика
                'ACTIVE' => 'Y',                                            // Флаг активности платежной системы
                'BX_REST_HANDLER' => $codeHandler,                    // Код обработчика в системе
                'ENTITY_REGISTRY_TYPE' => 'ORDER', // Код обработчика
                'LOGOTYPE' => getPaySystemIcon( $paySystemId ),
                'SETTINGS' => [
                    'TYPE_PAYSYSTEM' => [
                        'TYPE' => 'VALUE',
                        'VALUE' => "$paySystemId"
                    ]
                ]

            ];
        /*
        if( !isset( $salePaysystemAdd['result'] ) ){
            echo("Техническая ошибка, возможно недостаточно прав, необходимы права 'paysystem' или иная ошибка -  свяжитесь с издателем приложения");
            exit;
        }*/
    }
    else if( $sendActiveId > 0 ){
        CRest::call( "sale.paysystem.update", $memberId, [
            'id' => $sendActiveId,
            'fields' => [
                "ACTIVE" => 'Y', "PERSON_TYPE_ID" => $personTypeId, "BX_REST_HANDLER" => $codeHandler
            ]
        ] );
    }
    return 1;
}
function bxGetPersonTypePhis( $memberId ){
    $idPersonType = -1;
    $salePersonTypeList = CRest::call( "sale.persontype.list", $memberId );
    if( !isset( $salePersonTypeList['result'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав");
        exit;
    }
    if( !isset( $salePersonTypeList['result']['personTypes'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав");
        exit;
    }
    foreach ($salePersonTypeList['result']['personTypes'] as $value) {
        if( $value['code'] == "CRM_CONTACT" || $value['active'] == "Y" ){
            $idPersonType = $value['id'];
            break;
        }
    }
    return $idPersonType;
}
/*
 * ПРоверяем и создаём хендлер
 */

function bxCheckPaySystemHandler( $memberId, $codeHandler, $secretCode ){
    $paysystemHandlerCheck = false;
    $paysystemHandlerID = -1;


 // Код удаление платежных систем и хендлеров
    /*$salePaysystemList = CRest::call( "sale.paysystem.list", $memberId );
    if( !isset( $salePaysystemList['result'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав, необходимы права 'paysystem' или иная ошибка -  свяжитесь с издателем приложения");
        exit;
    }
    foreach ($salePaysystemList['result'] as $value) {

        if( $value['ACTION_FILE'] == $codeHandler ) {
            CRest::call("sale.paysystem.delete", $memberId, ['id' => $value['ID']]);
        }
    }
    $salePaySystemHandler = CRest::call( "sale.paysystem.handler.delete", $memberId, [ 'id' => 6 ] );


    $salePaySystemHandler = CRest::call( "sale.paysystem.handler.list", $memberId );
    echo json_encode($salePaySystemHandler);
    exit;*/
    $salePaySystemHandler = CRest::call( "sale.paysystem.handler.list", $memberId );

    if( !isset( $salePaySystemHandler['result'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав, необходимы права 'paysystem' или иная ошибка -  свяжитесь с издателем приложения");
        exit;
    }
    foreach ($salePaySystemHandler['result'] as $value) {
        if( $value['CODE'] == $codeHandler ){
            $paysystemHandlerCheck = true;
            $paysystemHandlerID = $value['ID'];
        }
    }
    if( $paysystemHandlerCheck == false ){
        $handlerObject = bxSettingsPaySystemHandler( $memberId, $secretCode );
        $salePaysystemHandler = CRest::call(
            "sale.paysystem.handler.add", $memberId,
            [
                'NAME' => 'Обработчик.EcomKassa', 							// Название обработчика
                'SORT' => 100, 											// Сортировка
                'CODE' => $codeHandler, 							// Уникальный код обработчика в системе
                'SETTINGS' => $handlerObject['SETTINGS']
            ]
        );
    } else {
        //Обновления данных в хендлере ( на случай их изменения )
        $handlerObject = bxSettingsPaySystemHandler( $memberId, $secretCode );
        $salePaysystemHandler = CRest::call(
            "sale.paysystem.handler.update", $memberId,
            [
                'id' => $paysystemHandlerID,
                'fields' => [
                    'SETTINGS' => $handlerObject['SETTINGS']
                ]
            ]
        );
    }
    if( !isset( $salePaysystemHandler['result'] ) ){
        echo("Техническая ошибка, возможно недостаточно прав, необходимы права 'paysystem' или иная ошибка -  свяжитесь с издателем приложения");
        exit;
    }
    return 1;
}
//----------------------------------------------------------------------------------------------------------------------
function bxSalePaymentItemBasketList( $memberId, $paymentId ){
    $result = CRest::call( "sale.paymentItemBasket.list", $memberId,
        [
            'filter' => [
                'paymentId' => $paymentId
            ]
        ]
    );
    if( isset( $result['error'] ) )return -1;
    return $result;
}
//----------------------------------------------------------------------------------------------------------------------
function bxSalepaymentItemShipmentList( $memberId, $paymentId ){
    $result = CRest::call( "sale.paymentItemShipment.list", $memberId,
        [
            'filter' => [
                'paymentId' => $paymentId
            ]
        ]
    );
    if( isset( $result['error'] ) )return -1;
    return $result;
}
//----------------------------------------------------------------------------------------------------------------------
function bxSaleOrderGet( $memberId, $orderId ){
    $result = CRest::call( "sale.order.get", $memberId, [ 'id' => $orderId ] );
    if( isset( $result['error'] ) )return -1;
    return $result;
}
//----------------------------------------------------------------------------------------------------------------------
function bxCatalogProductServiceGet( $memberId, $serviceId ){
    $result = CRest::call( "catalog.product.service.get", $memberId, [ 'id' => $serviceId ] );
    if( isset( $result['error'] ) )return -1;
    return $result;
}
//----------------------------------------------------------------------------------------------------------------------
function bxUpdateDealField( $memberid, $url, $dealid )
{
    $jsonDealField = CRest::call( 'crm.deal.fields',$memberid );
    if( !isset($jsonDealField['result'][C_REST_FIELD_KASSANAME]))
    {
        CRest::call(
            'crm.deal.userfield.add',$memberid,
            [
                'fields' => [
                    "FIELD_NAME" =>  C_REST_FIELD_KASSANAME,
                    "EDIT_FORM_LABEL" =>  C_REST_FIELD_PAYNAME,
                    /*"listLabel" =>  C_REST_FIELD_PAYNAME,
                    "formLabel" =>  C_REST_FIELD_PAYNAME,
                    "filterLabel" =>  C_REST_FIELD_PAYNAME,
                    "title" =>  C_REST_FIELD_PAYNAME,
                    "type" =>  "string",*/
                    "USER_TYPE_ID" =>  "string"//url
                ]
            ]
        );
    }
    CRest::call(
        'crm.deal.update',$memberid,[
            'id' => $dealid,
            'fields' => [
                'UF_CRM_'.C_REST_FIELD_KASSANAME => $url
            ]
        ]

    );
}
//----------------------------------------------------------------------------------------------------------------------
function format_uuidv4($data)
{
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
//----------------------------------------------------------------------------------------------------------------------
function GetPayUrl( $token, $kassaid, $paymentsType, $email, $totalSumm, $arrayItems, $companyArray, $externalId, $secret ){

    $arrayTypePay = -1;
    $paramurl = "https://app.ecomkassa.ru/fiscalorder/v2/$kassaid/sell?token=$token";
    if ($curl = curl_init()) {
        curl_setopt($curl, CURLOPT_URL,$paramurl);
        curl_setopt($curl, CURLOPT_POST, true);
        $jayParsedAry = [
            "external_id" => $externalId,
            "receipt" => [
                "client" => [
                    "email" => $email
                ],
                "prePaid" => true,
                "company" => $companyArray,
                "items" => $arrayItems,
                "payments" => [
                    [
                        "type" => (int)$paymentsType,
                        "sum" => (float)$totalSumm
                    ]
                ],
                "total" => (float)$totalSumm
            ],
            "service" => [
                "callback_url" => "https://ecomkassa-bitrix.mircloud.ru/callback.php?secret=$secret&externalId=$externalId"
            ],
            "timestamp" => date('d.m.y H:i:s')
        ];
        SendLog(json_encode($jayParsedAry));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode( $jayParsedAry ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_TIMECONDITION, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        $out = curl_exec($curl);
        SendLog('ANS ' . $out);
        $outJson = json_decode( $out );

        curl_close($curl);
        $arrayTypePay = $outJson;
    }
    return $arrayTypePay;
}
//----------------------------------------------------------------------------------------------------------------------
function GetPaymentTypes( $token, $kassaid ){
    $arrayTypePay = -1;
    $paramurl = "https://app.ecomkassa.ru/fiscalorder/v2/$kassaid/paymentTypes?token=$token";
    if ($curl = curl_init()) {
        curl_setopt($curl, CURLOPT_URL,$paramurl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_TIMECONDITION, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        $out = curl_exec($curl);
        $outJson = json_decode( $out );
        SendLog($out);
        SendLog($paramurl);
        curl_close($curl);
        $arrayTypePay = $outJson;
    }
    return $arrayTypePay;
}
//----------------------------------------------------------------------------------------------------------------------
function GetToken( $loginUser, $passUser ){
    $tokenResult = -1;
    $paramurl = "https://app.ecomkassa.ru/fiscalorder/v2/getToken?login=$loginUser&pass=$passUser";
    if ($curl = curl_init()) {
        curl_setopt($curl, CURLOPT_URL,$paramurl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_TIMECONDITION, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        $out = curl_exec($curl);
        $outJson = json_decode( $out );
        curl_close($curl);
        if( isset( $outJson->token ) ){
            $tokenResult = $outJson->token;
        }
    }
    return $tokenResult;
}
?>