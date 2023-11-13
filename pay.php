<?php

    require_once (__DIR__.'/lib.php');

    if( !isset($_REQUEST['MEMBER_ID']) ){
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
    $stmt->execute([$_REQUEST['MEMBER_ID']]);
    //$stmt->exec("SET NAMES = utf8");
    $userData = $stmt->fetch(PDO::FETCH_LAZY);

    $secretcode = $userData['secret_code'];
    if( $secretcode != $_REQUEST['ECOM_SECRET'] ){exit;}

    $emailCheckDef = $_REQUEST['EMAIL'];
    if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
        $emailCheckDef = $_REQUEST['USER_EMAIL'];
    }
    if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
        $emailCheckDef = $userData['emailDefCheck'];
    }
    if (!filter_var($emailCheckDef, FILTER_VALIDATE_EMAIL)) {
        //не валидный email
        $result = [ 'PAYMENT_ERRORS' => [  'Не валидный E-mail для печати чеков ОФД' ] ];
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
    $companyArray = array(
        "email" => $userData['company_email'],
        "sno" => $userData['company_sno'],
        "inn" => $userData['company_inn'],
        "payment_address" => $userData['company_payment_address']
    );
/*
 * {"ORDER_ID":"7","PAYMENT_ID":"15","MEMBER_ID":"3ab87379e5b8443827afb7547dd82416","TYPE_PAYSYSTEM":"106","EMAIL":"nect04@yandex.ru","BX_SYSTEM_PARAMS":{"RETURN_URL":"https:\/\/b24-kxt12n.bitrix24shop.ru\/oformleniezakaza\/?orderId=7&access=1aca4280b90298cad9c132961bce8c01&paymentId=15&user_lang=ru","PAYSYSTEM_ID":"39","PAYMENT_ID":"15","SUM":"1700","CURRENCY":"RUB","EXTERNAL_PAYMENT_ID":"15"}}
 */

    $bxPayToShip = bxSalepaymentItemShipmentList( $_REQUEST['MEMBER_ID'], $_REQUEST['PAYMENT_ID'] );
    $bxPayToBasket = bxSalePaymentItemBasketList( $_REQUEST['MEMBER_ID'], $_REQUEST['PAYMENT_ID'] );
    $saleOrderGet = bxSaleOrderGet( $_REQUEST['MEMBER_ID'], $_REQUEST['ORDER_ID'] );
    SendTg('383404884', json_encode($_REQUEST));
    /*SendTg('383404884', json_encode($bxPayToShip));
    SendTg('383404884', json_encode($bxPayToBasket));
    SendTg('383404884', json_encode($saleOrderGet));*/
    //----------------------------------------------------------------------------------------------------------------------
    $totalPaySum = 0;
    $arrayItems = array();
    if( $bxPayToBasket && $saleOrderGet && $bxPayToShip ){
        $arrayItems = array();
        foreach ( $bxPayToBasket['result']['paymentItemsBasket'] as $valuePayToBasket ) {
            foreach ( $saleOrderGet['result']['order']['basketItems'] as $valueOrder ) {
                if( $valueOrder['id'] == $valuePayToBasket['basketId'] ){
                    $paymentObject = $payment_object;
                    //if( bxCatalogProductServiceGet( $_REQUEST['MEMBER_ID'], $valueOrder['productId'] ) ){
                    if( $valueOrder['type'] == 2 ){
                        $paymentObject = "service";
                    }
                    //SendTg('383404884', json_encode($valueOrder));
                    $valueVat = "none";
                    if( $valueOrder['vatIncluded'] == "N" && $valueOrder['vatRate'] !== null ){//N - не включён
                        $valueOrder['price'] = $valueOrder['price'] * 100;
                        $valueOrder['price'] = round( $valueOrder['price'] * $valueOrder['vatRate'] + $valueOrder['price'] );
                        $valueOrder['price'] = $valueOrder['price'] / 100;
                    }
                    if( $valueOrder['vatRate'] !== null ){
                        //Налог включён
                        //значение 0.1, 0.2, 0, null - без НДС
                        $valueVat = "vat".( $valueOrder['vatRate']*100 );
                    }
                    if( $vat100 == 1 && $valueOrder['vatRate'] > 0 ){
                        $valueVat = "vat".( 100 + $valueOrder['vatRate']*100 );
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
                    //SendTg('383404884', json_encode($arrayObj));
                }
            }
        }
        foreach ( $bxPayToShip['result']['paymentItemsShipment'] as $valuePayToShipment ) {
            foreach ( $saleOrderGet['result']['order']['shipments'] as $valueOrder ) {
                if( $valueOrder['id'] == $valuePayToShipment['shipmentId'] ){
                    //SendTg('383404884', json_encode($valueOrder));
                    //SendTg('383404884', json_encode($saleOrderGet));
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
    //SendTg('383404884', json_encode($arrayItems));
    //-------------------------------------------Перевыпуск просроченного токена--------------------------------------------
    $externalId = format_uuidv4(random_bytes(16));
    $secret = md5( rand(1,10000000) );
    $urlPay = GetPayUrl( $token, $kassaid, $_REQUEST['TYPE_PAYSYSTEM'], $emailCheckDef, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );
    if( isset($urlPay->error->code) && $urlPay->error->code == 11 ){
        $token = GetToken( $login, $pass );
        if( $token == -1 ){
            $result = [ 'PAYMENT_ERRORS' => [  "Неверный логин или пароль EcomKassa" ] ];
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
        $urlPay = GetPayUrl( $token, $kassaid, $_REQUEST['TYPE_PAYSYSTEM'], $emailCheckDef, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );
    }
    //----------------------------------------------------------------------------------------------------------------------
    if( isset( $urlPay->code )  ){
        $result = [ 'PAYMENT_ERRORS' => [  $urlPay->code, $urlPay->text ] ];
    }
    else if( !$urlPay->error == null ){
        $result = [ 'PAYMENT_ERRORS' => [  $urlPay->error->code, $urlPay->error->text ] ];
    }
    else {
        //----------------------------------------------------------------------------------------------------------------------
        $query = "INSERT INTO `bills`(`member_id`, `external_id`, `url`, `secret`, `PAYMENT_ID`, `ORDER_ID`, `PAYSYSTEM_ID`,`RETURN_URL`) VALUES (:memberid,:externalid,:url,:secret,:PAYMENT_ID,:ORDER_ID,:PAYSYSTEM_ID,:RETURN_URL)";
        $params = [
            ':memberid' => $_REQUEST['MEMBER_ID'],
            ':externalid' => $externalId,
            ':url' => $urlPay->invoice_payload->link,
            ':secret' => $secret,
            ':PAYMENT_ID' => $_REQUEST['PAYMENT_ID'],
            ':ORDER_ID' => $_REQUEST['ORDER_ID'],
            ':PAYSYSTEM_ID' => $_REQUEST['BX_SYSTEM_PARAMS']['PAYSYSTEM_ID'],
            ':RETURN_URL' => $_REQUEST['BX_SYSTEM_PARAMS']['RETURN_URL']
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        //------------------------------------------------------------------------------------------------------------------
        $result = [
            'PAYMENT_URL' => $urlPay->invoice_payload->link, // url страницы оплаты
            'PAYMENT_ID' => $_REQUEST['BX_SYSTEM_PARAMS']['PAYMENT_ID'], // идентификатор оплаты
        ];
    }
    header('Content-Type:application/json; charset=UTF-8');
    echo json_encode($result);
    exit;