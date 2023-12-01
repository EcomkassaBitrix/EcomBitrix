<?php
    require_once (__DIR__.'/lib.php');

    if( !isset($_REQUEST['mid']) && !isset($_REQUEST['did']) ){
        exit;
    }
    $memberId = $_REQUEST['mid'];
    $dealId = $_REQUEST['did'];//DEAL ID
    $tpsid = $_REQUEST['tid'];//Type Pay System
    $sec = $_REQUEST['sec'];//Type Pay System

    bxLogDeal( $memberId, $dealId, 'Ecom: Запрос получения ссылки на оплату по вебхуку ид платёжной системы '.$tpsid );
    //------------------------------------------------------------------------------------------------------------------
    $stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
    $stmt->execute([$memberId]);
    $userData = $stmt->fetch(PDO::FETCH_LAZY);
    if( !$userData['id'] > 0 ){
        exit;
    }

    $login = $userData['ecomLogin'];
    $pass = $userData['ecomPass'];
    $payment_object = $userData['payment_object'];
    $kassaid = round( $userData['ecomKassaId'] );
    $token = $userData['tokenEcomKassa'];
    $secret = $userData['secret_code'];

    if( substr($secret, 0, 8) != $sec ){
        exit;
    }

    $paymentMethodDef = $userData['payment_method'];
    $vat100 = $userData['vat100'];
    $emailDefCheck = $userData['emailDefCheck'];
    $vatValueShipment = $userData['vatShipment'];
    $vatValueOrder = $userData['vatOrder'];
    if( $vatValueOrder != 'none' && $vatValueOrder != null ){//none - без ндс
        $vatValueOrder = "vat".$vatValueOrder;
    }
    $companyArray = array(
        "email" => $userData['company_email'],
        "sno" => $userData['company_sno'],
        "inn" => $userData['company_inn'],
        "payment_address" => $userData['company_payment_address']
    );
    //------------------------------------------------------------------------------------------------------------------
    $getContactId = CRest::call(
        'crm.deal.contact.items.get',$memberId,
        [
            'id' =>  $dealId
        ]
    );
    foreach ( $getContactId['result'] as $contactid ) {
        $findEmail = false;
        if( isset( $contactid['CONTACT_ID'] ) && $contactid['CONTACT_ID'] > 0 ){
            $contactValue = CRest::call(
                'crm.contact.get',$memberId,
                [
                    'id' => $contactid['CONTACT_ID']
                ]
            );
            foreach ( $contactValue['result']['EMAIL'] as $emailValue ) {
                if ( isset($emailValue['VALUE']) && strlen($emailValue['VALUE']) > 0 ) {

                    $emailDefCheck = $emailValue['VALUE'];
                    $findEmail = true;
                    break;
                }
            }
        }
        if( $findEmail == true ){
            break;
        }
    }
    //------------------------------------------------------------------------------------------------------------------
    if (!filter_var($emailDefCheck, FILTER_VALIDATE_EMAIL)) {
        bxLogDeal( $memberId, $dealId, 'Отсутсвует E-mail для чеков офд' );
        exit;
    }
    //------------------------------------------------------------------------------------------------------------------
    $valueBasket = CRest::call(
        'crm.deal.productrows.get',$memberId,
        [
            'id' => $dealId
        ]
    );
    if( !isset( $valueBasket['result'] ) || count( $valueBasket ) < 1 ){
        bxLogDeal( $memberId, $dealId, 'Нету элементов в корзине' );
        exit;
    }
    //------------------------------------------------------------------------------------------------------------------
    $totalPaySum = 0;
    $arrayItems = array();
    if( $valueBasket ){
        $arrayItems = array();
        foreach ( $valueBasket['result'] as $valueOrder ) {

            $paymentObject = $payment_object;
            if( $valueOrder['TYPE'] == 7 ){
                $paymentObject = "service";
            }
            $valueVat = "none";
            if( $valueOrder['TAX_RATE'] !== null ){
                //Налог включён
                //значение 0.1, 0.2, 0, null - без НДС
                $valueVat = "vat".( $valueOrder['TAX_RATE'] );
            }
            if( $vat100 == 1 && $valueOrder['TAX_RATE'] > 0 ){
                $valueVat = "vat".( 100 + $valueOrder['TAX_RATE'] );
            }
            if( $vatValueOrder != null ){
                $valueVat = $vatValueOrder;
            }
            $arrayObj = array(
                "name" => $valueOrder['PRODUCT_NAME'],
                "price" => $valueOrder['PRICE'],
                "quantity" => $valueOrder['QUANTITY'],
                "sum" =>  (ceil( ($valueOrder['QUANTITY'] * $valueOrder['PRICE']) * 100 )) / 100,
                "measurement_unit" => $valueOrder['MEASURE_NAME'],
                "payment_method" => $paymentMethodDef,
                "payment_object" => $paymentObject,
                "vat" => [
                    "type" => $valueVat
                ]
            );
            array_push($arrayItems, $arrayObj);
            $totalPaySum = $totalPaySum + (ceil( ($valueOrder['QUANTITY'] * $valueOrder['PRICE']) * 100 )) / 100;
        }
    }
    //-------------------------------------------Перевыпуск просроченного токена--------------------------------------------
    $externalId = format_uuidv4(random_bytes(16));
    $secret = md5( rand(1,10000000) );
    $urlPay = GetPayUrl( $token, $kassaid, $tpsid, $emailDefCheck, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );
    if( isset($urlPay->error->code) && $urlPay->error->code == 11 ){
        $token = GetToken( $login, $pass );
        if( $token == -1 ){
            bxLogDeal( $memberId, $dealId, 'Неверный логин или пароль EcomKassa' );
            exit;
        }
        $query = "UPDATE `users` SET `tokenEcomKassa` = :token WHERE `id` = :id";
        $params = [
            ':id' => $userData['id'],
            ':token' => $token
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $urlPay = GetPayUrl( $token, $kassaid, $tpsid, $emailDefCheck, $totalPaySum, $arrayItems, $companyArray, $externalId, $secret );
    }
    //------------------------------------------------------------------------------------------------------------------
    if( isset( $urlPay->code )  ){
        bxLogDeal( $memberId, $dealId, $urlPay->code.' '.$urlPay->text );
    }
    else if( !$urlPay->error == null ){
        bxLogDeal( $memberId, $dealId, $urlPay->error->code.' '.$urlPay->error->text );
    }
    else {
        //----------------------------------------------------------------------------------------------------------------------
        $query = "INSERT INTO `bills`(`member_id`, `external_id`, `url`, `secret`,`dealid`) VALUES (:memberid,:externalid,:url,:secret,:dealid)";
        $params = [
            ':memberid' => $memberId,
            ':externalid' => $externalId,
            ':url' => $urlPay->invoice_payload->link,
            ':secret' => $secret,
            ':dealid' => $dealId
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        bxUpdateDealField( $memberId, $urlPay->invoice_payload->link, $dealId );
    }
    exit;
?>