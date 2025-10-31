<?php

require_once (__DIR__.'/lib.php');

// Логируем входящий запрос
$requestData = $_REQUEST;
$memberID = $_REQUEST['MEMBER_ID'] ?? 'unknown';
$paymentID = $_REQUEST['PAYMENT_ID'] ?? null;
$orderID = $_REQUEST['ORDER_ID'] ?? null;

logRequest($memberID, $paymentID, $orderID, $requestData, null, 'started');

if( !isset($_REQUEST['MEMBER_ID']) ){
    logError('unknown', 'MISSING_MEMBER_ID', 'MEMBER_ID не передан', $_REQUEST);
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
$stmt->execute([$_REQUEST['MEMBER_ID']]);
$userData = $stmt->fetch(PDO::FETCH_LAZY);

$secretcode = $userData['secret_code'];
if( $secretcode != $_REQUEST['ECOM_SECRET'] ){
    logError($memberID, 'INVALID_SECRET', 'Неверный секретный код', [
        'expected' => substr($secretcode, 0, 5) . '***',
        'received' => substr($_REQUEST['ECOM_SECRET'], 0, 5) . '***'
    ]);
    exit;
}

$emailCheckDef = $_REQUEST['EMAIL'];
if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
    $emailCheckDef = $_REQUEST['USER_EMAIL'];
}
if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
    $userData['emailDefCheck'];
}
if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
    $result = [ 'PAYMENT_ERRORS' => [  'Не валидный E-mail для печати чеков ОФД' ] ];
    logError($memberID, 'INVALID_EMAIL', 'Не валидный email', ['email' => $emailCheckDef]);
    logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
    header('Content-Type:application/json; charset=UTF-8');
    echo json_encode($result);
    exit;
}

$login = $userData['ecomLogin'];
$pass = $userData['ecomPass'];
$payment_object = $userData['payment_object'];
$kassaid = round( $userData['ecomKassaId'] );
$token = $userData['tokenEcomKassa'];

$paymentMethodDef = $userData['payment_method'];
$vat100 = $userData['vat100'];
$vatValueShipment = $userData['vatShipment'];
if( $vatValueShipment != 'none' ){
    $vatValueShipment = "vat".$vatValueShipment;
}
$vatValueOrder = $userData['vatOrder'];
if( $vatValueOrder != 'none' && $vatValueOrder != null ){
    $vatValueOrder = "vat".$vatValueOrder;
}
$companyArray = array(
    "email" => $userData['company_email'],
    "sno" => $userData['company_sno'],
    "inn" => $userData['company_inn'],
    "payment_address" => $userData['company_payment_address']
);

$bxPayToShip = bxSalepaymentItemShipmentList( $_REQUEST['MEMBER_ID'], $_REQUEST['PAYMENT_ID'] );
$bxPayToBasket = bxSalePaymentItemBasketList( $_REQUEST['MEMBER_ID'], $_REQUEST['PAYMENT_ID'] );
$saleOrderGet = bxSaleOrderGet( $_REQUEST['MEMBER_ID'], $_REQUEST['ORDER_ID'] );
SendLog( json_encode($_REQUEST));
$typePaySystem = $_REQUEST['TYPE_PAYSYSTEM'];

$totalPaySum = 0;
$arrayItems = array();
if( $bxPayToBasket && $saleOrderGet && $bxPayToShip ){
    $arrayItems = array();
    foreach ( $bxPayToBasket['result']['paymentItemsBasket'] as $valuePayToBasket ) {
        foreach ( $saleOrderGet['result']['order']['basketItems'] as $valueOrder ) {
            if( $valueOrder['id'] == $valuePayToBasket['basketId'] ){
                $paymentObject = $payment_object;
                if( $valueOrder['type'] == 2 ){
                    $paymentObject = "service";
                }
                $valueVat = "none";
                if( $valueOrder['vatIncluded'] == "N" && $valueOrder['vatRate'] !== null ){
                    $valueOrder['price'] = $valueOrder['price'] * 100;
                    $valueOrder['price'] = round( $valueOrder['price'] * $valueOrder['vatRate'] + $valueOrder['price'] );
                    $valueOrder['price'] = $valueOrder['price'] / 100;
                }
                if( $valueOrder['vatRate'] !== null ){
                    $valueVat = "vat".( $valueOrder['vatRate']*100 );
                }
                if( $vat100 == 1 && $valueOrder['vatRate'] > 0 ){
                    $valueVat = "vat".( 100 + $valueOrder['vatRate']*100 );
                }
                if( $vatValueOrder != null ){
                    $valueVat = $vatValueOrder;
                }
                $arrayObj = array(
                        "name" => $valueOrder['name'],
                        "price" => $valueOrder['price'],
                        "quantity" => $valuePayToBasket['quantity'],
                        "sum" => (ceil( ($valuePayToBasket['quantity'] * $valueOrder['price']) * 100 )) / 100,
                        "measurement_unit" => $valueOrder['measureName'],
                        "payment_method" => $paymentMethodDef,
                        "payment_object" => $paymentObject,
                        "vat" => [
                            "type" => $valueVat
                        ]
                );
                array_push($arrayItems, $arrayObj);
                $totalPaySum = $totalPaySum + (ceil( ($valuePayToBasket['quantity'] * $valueOrder['price']) * 100 )) / 100;
            }
        }
    }
    foreach ( $bxPayToShip['result']['paymentItemsShipment'] as $valuePayToShipment ) {
        foreach ( $saleOrderGet['result']['order']['shipments'] as $valueOrder ) {
            if( $valueOrder['id'] == $valuePayToShipment['shipmentId'] ){
                $paymentObject = "service";
                $arrayObj = array(
                    "name" => $valueOrder['deliveryName'],
                    "price" => $valueOrder['priceDelivery'],
                    "quantity" => 1,
                    "sum" => $valueOrder['priceDelivery'],
                    "payment_method" => $paymentMethodDef,
                    "payment_object" => $paymentObject,
                    "vat" => [
                        "type" => $vatValueShipment
                    ]
                );
                array_push($arrayItems, $arrayObj);
                $totalPaySum = $totalPaySum + $valueOrder['priceDelivery'];
            }
        }
    }
}

$externalId = format_uuidv4(random_bytes(16));
$secret = md5( rand(1,10000000) );

if( !((int)$typePaySystem > 1) ){
    $paySystemEcom = GetPaymentTypes( $token, $kassaid );
    $paySystemBitrix = bxGetAllPaySystem( $_REQUEST['MEMBER_ID'] );

    if( isset($paySystemEcom->code ) && $paySystemEcom->code == 4 ){
        logError($memberID, 'TOKEN_EXPIRED', 'Токен истек, получаем новый', ['kassa_id' => $kassaid]);
        $token = GetToken( $login, $pass );
        if( $token == -1 ){
            $result = [ 'PAYMENT_ERRORS' => [  "Неверный логин или пароль EcomKassa" ] ];
            logError($memberID, 'AUTH_FAILED', 'Неверный логин/пароль EcomKassa', ['login' => $login]);
            logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
            header('Content-Type:application/json; charset=UTF-8');
            echo json_encode($result);
            exit;
        }
        $query = "UPDATE `users` SET `tokenEcomKassa` = :token WHERE `id` = :id";
        $params = [
            ':id' => $userData['id'],
            ':token' => $token
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $paySystemEcom = GetPaymentTypes( $token, $kassaid );
    }
    foreach ( $paySystemBitrix['result'] as $value ) {
        if( $value['ID'] == $_REQUEST['BX_SYSTEM_PARAMS']['PAYSYSTEM_ID'] )
        {
            foreach ( $paySystemEcom as $valueEcom ) {
                $namePaySys = str_replace('"', '', $valueEcom->description);
                if( "Екомкасса: ".$namePaySys == $value['NAME'] ) {
                    $typePaySystem = $valueEcom->id;
                    $resultUpdateSystem = CRest::call(
                        "sale.paysystem.settings.update", $_REQUEST['MEMBER_ID'],
                        [
                            'id' => $value['ID'] ,
                            'PERSON_TYPE_ID' => $value['PERSON_TYPE_ID'],
                            'SETTINGS' => [
                                'TYPE_PAYSYSTEM' => [
                                    'TYPE' => 'VALUE',
                                    'VALUE' => "".$valueEcom->id
                                ]
                            ]
                        ]
                    );
                }
            }
        }
    }
}

if( !((int)$typePaySystem > 1) ){
    $result = [ 'PAYMENT_ERRORS' => [  "Неверный способ оплаты" ] ];
    logError($memberID, 'INVALID_PAYMENT_TYPE', 'Неверный способ оплаты', ['type' => $typePaySystem]);
    logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
    header('Content-Type:application/json; charset=UTF-8');
    echo json_encode($result);
    exit;
}

$urlPay = GetPayUrl( $token, $kassaid, $typePaySystem, $emailCheckDef, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );

if( isset($urlPay->error->code) && $urlPay->error->code == 11 ){
    logError($memberID, 'TOKEN_EXPIRED_RETRY', 'Токен истек при получении URL, повторная попытка', ['kassa_id' => $kassaid]);
    $token = GetToken( $login, $pass );
    if( $token == -1 ){
        $result = [ 'PAYMENT_ERRORS' => [  "Неверный логин или пароль EcomKassa" ] ];
        logError($memberID, 'AUTH_FAILED_RETRY', 'Неверный логин/пароль при повторе', ['login' => $login]);
        logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
        header('Content-Type:application/json; charset=UTF-8');
        echo json_encode($result);
        exit;
    }
    $query = "UPDATE `users` SET `tokenEcomKassa` = :token WHERE `id` = :id";
    $params = [
        ':id' => $userData['id'],
        ':token' => $token
    ];
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $urlPay = GetPayUrl( $token, $kassaid, $typePaySystem, $emailCheckDef, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );
}

if( isset( $urlPay->code )  ){
    $result = [ 'PAYMENT_ERRORS' => [  $urlPay->code, $urlPay->text ] ];
    logError($memberID, 'ECOM_ERROR', 'Ошибка от EcomKassa', ['code' => $urlPay->code, 'text' => $urlPay->text]);
    logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
}
else if( !$urlPay->error == null ){
    $result = [ 'PAYMENT_ERRORS' => [  $urlPay->error->code, $urlPay->error->text ] ];
    logError($memberID, 'ECOM_ERROR', 'Ошибка от EcomKassa', ['code' => $urlPay->error->code, 'text' => $urlPay->error->text]);
    logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'error');
}
else {
    $query = "INSERT INTO `bills`(`member_id`, `external_id`, `url`, `secret`, `PAYMENT_ID`, `ORDER_ID`, `PAYSYSTEM_ID`,`RETURN_URL`) VALUES (:memberid,:externalid,:url,:secret,:PAYMENT_ID,:ORDER_ID,:PAYSYSTEM_ID,:RETURN_URL)";
    $params = [
        ':memberid' => $_REQUEST['MEMBER_ID'],
        ':externalid' => $externalId,
        ':url' => $urlPay->invoice_payload->link,
        ':secret' => $secret,
        ':PAYMENT_ID' => $_REQUEST['PAYMENT_ID'],
        ':ORDER_ID' => $_REQUEST['ORDER_ID'],
        ':PAYSYSTEM_ID' => $_X_SYSTEM_PARAMS']['PAYSYSTEM_ID'],
        ':RETURN_URL' => $_REQUEST['BRETURN_URL']
    ];
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $result = [
        'PAYMENT_URL' => $urlPay->invoice_payload->link,
        'PAYMENT_ID' => $_REQUEST['BX_SYSTEM_PARAMS']['PAYMENT_ID'],
    ];
    
    // Логируем успешный результат
    logRequest($memberID, $paymentID, $orderID, $requestData, $result, 'success');
}

header('Content-Type:application/json; charset=UTF-8');
echo json_encode($result);
exit;
