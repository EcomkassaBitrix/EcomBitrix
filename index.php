<?php

    require_once (__DIR__.'/lib.php');

    // put an example below

    if( !isset($_REQUEST['member_id']) ){
        exit;
    }
    $alertText = "";
    //------------------------------------------------------------------------------------------------------------------
    if( $_REQUEST['type'] == 'updateWebHook' ){
        $alertText = "Веб хук обновлен";

        $query = "UPDATE `users` SET `webHookUrl` = :webHookUrl WHERE `member_id` = :member_id";
        $params = [
            ':member_id' => $_REQUEST['member_id'],
            ':webHookUrl' => $_REQUEST['webHookUrl']
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }
    //------------------------------------------------------------------------------------------------------------------

    $stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
    $stmt->execute([$_REQUEST['member_id']]);
    $userData = $stmt->fetch(PDO::FETCH_LAZY);

    //------------------------------------------------------------------------------------------------------------------
    if( $_REQUEST['type'] == 'updateSettings' ){
        $alertText = "Настройки обновлены";

        if( !$_REQUEST['ecomLogin'] || !$_REQUEST['ecomPass'] || !$_REQUEST['ecomKassaId'] || !$_REQUEST['emailDefCheck'] || !$_REQUEST['company_email'] || !$_REQUEST['company_sno'] || !$_REQUEST['vatShipment'] || !$_REQUEST['vatOrder'] || !$_REQUEST['company_inn'] || !$_REQUEST['company_payment_address']
         || !$_REQUEST['payment_method'] || !$_REQUEST['payment_object'] ){
            $alertText = "Не все поля настроек заполнены";
        } else {
            if( $_REQUEST['ecomPass'] && $_REQUEST['ecomPass'] != "***" ){
                $pass = $_REQUEST['ecomPass'];
            } else {
                $pass = $userData['ecomPass'];
            }
            $vatOrder = null;
            if( $_REQUEST['vatOrderCheck'] ){
                $vatOrder = $_REQUEST['vatOrder'];
            }
            $query = "UPDATE `users` SET `ecomLogin` = :ecomLogin, `ecomPass` = :ecomPass, `ecomKassaId` = :ecomKassaId, `emailDefCheck` = :emailDefCheck, `company_email` = :company_email, `company_sno` = :company_sno, `company_inn` = :company_inn, `company_payment_address` = :company_payment_address, `vatShipment` = :vatShipment, `vatOrder` = :vatOrder, `vat100` = :vat100, `payment_method` = :payment_method, `payment_object` = :payment_object WHERE `id` = :id";
            $params = [
                ':id' => $userData['id'],
                ':ecomLogin' => $_REQUEST['ecomLogin'],
                ':ecomPass' => $pass,
                ':ecomKassaId' => $_REQUEST['ecomKassaId'],
                ':emailDefCheck' => $_REQUEST['emailDefCheck'],
                //О компании
                ':company_email' => $_REQUEST['company_email'],
                ':company_sno' => $_REQUEST['company_sno'],
                ':vatShipment' => $_REQUEST['vatShipment'],
                ':vatOrder' => $vatOrder,
                ':company_inn' => $_REQUEST['company_inn'],
                ':company_payment_address' => $_REQUEST['company_payment_address'],
                ':vat100' => $_REQUEST['vat100'],
                ':payment_method' => $_REQUEST['payment_method'],
                ':payment_object' => $_REQUEST['payment_object']
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);

            $stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
            $stmt->execute([$_REQUEST['member_id']]);
            $userData = $stmt->fetch(PDO::FETCH_LAZY);
        }
    }
    $codeHandler = "ecomkassabitrix";
    $login = $userData['ecomLogin'];
    $pass = $userData['ecomPass'];
    $kassaid = $userData['ecomKassaId'];
    $token = $userData['tokenEcomKassa'];
    $emailDefCheck = $userData['emailDefCheck'];
    $companySno = $userData['company_sno'];
    $vatShipment = $userData['vatShipment'];
    $vatOrder = $userData['vatOrder'];
    $vatOrderCheck = 0;
    if( $vatOrder != null ){
        $vatOrderCheck = 1;
    }
    $companyEmail = $userData['company_email'];
    $companyInn = $userData['company_inn'];
    $vat100 = $userData['vat100'];
    $payment_method = $userData['payment_method'];
    $payment_object = $userData['payment_object'];
    $secretCode = $userData['secret_code'];
    $companyPaymentAddress = $userData['company_payment_address'];
    $webHookUrl = $userData['webHookUrl'];
    $buttonUpdatePaySystem = "";
    if( !$kassaid || !$login || !$pass ){
        $buttonUpdatePaySystem = 'hidden';
    }
    //------------------------------------------------------------------------------------------------------------------
    if( $_REQUEST['type'] == 'updatePaySystem' ){
        $alertText = "Платёжные системы синхронизированы с EcomKassa";
        $paySystemEcom = GetPaymentTypes( $token, $kassaid );
        if( isset($paySystemEcom->code ) && $paySystemEcom->code == 4 ){
            $token = GetToken( $login, $pass );
            if( $token == -1 ){
                $alertText = "Неверный логин или пароль EcomKassa";
            }
            else{
                $query = "UPDATE `users` SET `tokenEcomKassa` = :token WHERE `id` = :id";
                $params = [
                    ':id' => $userData['id'],
                    ':token' => $token
                ];
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $paySystemEcom = GetPaymentTypes( $token, $kassaid );
            }

        }
        if( $token != -1 ){
            if( isset( $paySystemEcom->status ) && $paySystemEcom->status == 'fail' ){
                $alertText = ( $paySystemEcom->error->text );
            }
            //Не верный ид магазина
            else if( isset( $paySystemEcom->code ) && $paySystemEcom->code == 22 ){
                $alertText = ( $paySystemEcom->text );
            }
            else
            {
                //------------------------------------------------------------------------------------------------------------------
                $idPersonType = bxGetPersonTypePhis( $_REQUEST['member_id'] );
                $checkHandler = bxCheckPaySystemHandler( $_REQUEST['member_id'], $codeHandler, $secretCode );
                if( $checkHandler > 0 && $idPersonType > 0 ){
                    //---------------------Здесь создаём систему----------------------------
                    $paySystemBitrix = bxGetAllPaySystem( $_REQUEST['member_id'] );
                    foreach ( $paySystemEcom as $value ) {
                        $namePaySys = str_replace('"', '', $value->description);
                        bxSalePaySystemAdd( $_REQUEST['member_id'], $codeHandler, $idPersonType, "Ecom: ".$namePaySys, $value->id, $paySystemBitrix );
                    }
                    //--------------------------------Выключение платёжки при отключении в ecom-------------------------------------
                    foreach ( $paySystemBitrix['result'] as $value ) {
                        if( $value['ACTION_FILE'] == $codeHandler && $value['PERSON_TYPE_ID'] == $idPersonType  ){
                            $findTypePayEcom = false;
                            foreach ( $paySystemEcom as $valueEcom ) {
                                $namePaySys = str_replace('"', '', $valueEcom->description);
                                if( "Ecom: ".$namePaySys == $value['NAME'] )
                                    $findTypePayEcom = true;
                            }
                            if( $findTypePayEcom == false ){
                                CRest::call( "sale.paysystem.update", $_REQUEST['member_id'], [
                                    'id' => $value['ID'],
                                    'fields' => [
                                        "ACTIVE" => 'N', "PERSON_TYPE_ID" => $idPersonType, "BX_REST_HANDLER" => $codeHandler
                                    ]
                                ] );
                            }
                        }
                    }
                }
            }
        }
    }
    //echo(json_encode($jsonDealField = CRest::call( 'crm.userfield.fields',$_REQUEST['member_id'] )));
    //echo(json_encode($jsonDealField = CRest::call( 'crm.userfield.types',$_REQUEST['member_id'] )));
    //echo(json_encode($jsonDealField = CRest::call( 'crm.deal.fields',$_REQUEST['member_id'] )));
?>
<html lang="ru">
<head>
    <title>EcomKassa application</title>
    <style>
        html, body {
            height: 98%;
        }
        html {
            display: table;
            margin: auto;
        }
        body {
            vertical-align: middle;
        }
        input[type='radio'] {
            accent-color: #232323;
        }
    </style>
    <script>
        function ChangeVatOrder() {
            if( document.getElementById('vatOrderCheck').checked  ){
                document.getElementById('vatOrder').style.display = 'table-row';
                document.getElementById('vat100').style.display = 'none';
            }else{
                document.getElementById('vatOrder').style.display = 'none';
                document.getElementById('vat100').style.display = 'table-row';
            }
        }
        window.onload = ChangeVatOrder;
    </script>
</head>
<body style="font-family: 'Open Sans','Helvetica Neue',Helvetica,Arial,sans-serif;margin-top: 0px;padding: 0px;">
    <?
        if( $alertText  ){
            echo("
                <div id='alertText' style='padding:4px;width: 410px;background-color: red;color: white;font-size: 14px;text-align: center;border-radius: 15px;'><h3>$alertText</h3></div>
                <script>
                 setTimeout(function closeAlert() {
                    document.getElementById( 'alertText' ).remove();
                }, 5000);
                </script>
            ");
        }
    ?>
    <div style="height: 460px;">
        <form action='index.php' method="post">
            <table style="font-size: 12px;width:415px;text-align: right;border: 2px solid #b7b7b7;border-radius: 15px; padding: 5px;">
                <tr>
                    <td style="color: #bfbfbf">Основные настройки</td><td></td>
                </tr>
                <tr>
                    <td>Логин EcomKassa</td><td><input type="email" name="ecomLogin" style="width: 200px;text-align: center;" value="<? echo(htmlspecialchars($login, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>Пароль EcomKassa</td><td><input type="password" name="ecomPass" style="width: 200px;text-align: center;" value="<? if( strlen( $pass ) > 0 ){$pass = "***";}echo(htmlspecialchars($pass, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>ИД кассы</td><td><input type="text" name="ecomKassaId" style="width: 200px;text-align: center;" value="<? echo(htmlspecialchars($kassaid, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>Email по умолчанию<br>для чеков</td><td><input type="email" name="emailDefCheck" style="width: 200px;text-align: center;" value="<? echo(htmlspecialchars($emailDefCheck, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>Признак способа расчёта</td><td>
                        <select style="width: 200px;text-align: center;" name="payment_method">
                            <option value="full_prepayment"                 <? echo( ('full_prepayment' == $payment_method) ? 'selected' : '' ) ?> >предоплата 100%</option>
                            <option value="prepayment"                      <? echo( ('prepayment' == $payment_method) ? 'selected' : '' ) ?> >предоплата</option>
                            <option value="advance"                         <? echo( ('advance' == $payment_method) ? 'selected' : '' ) ?>>аванс</option>
                            <option value="full_payment"                    <? echo( ('full_payment' == $payment_method) ? 'selected' : '' ) ?>>полный расчет</option>
                            <option value="partial_payment"                 <? echo( ('partial_payment' == $payment_method) ? 'selected' : '' ) ?>>частичный расчет и кредит</option>
                            <option value="credit"                          <? echo( ('credit' == $payment_method) ? 'selected' : '' ) ?>>передача в кредит</option>
                            <option value="credit_payment"                  <? echo( ('credit_payment' == $payment_method) ? 'selected' : '' ) ?>>оплата кредита</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Признак предмета расчёта (товар)</td><td>
                        <select style="width: 200px;text-align: center;" name="payment_object">
                            <option value="commodity"              <? echo( ('commodity' == $payment_object) ? 'selected' : '' ) ?> >товар</option>
                            <option value="excise"                 <? echo( ('excise' == $payment_object) ? 'selected' : '' ) ?> >подакцизный товар</option>
                            <option value="job"                    <? echo( ('job' == $payment_object) ? 'selected' : '' ) ?> >работа</option>
                            <option value="gambling_bet"           <? echo( ('gambling_bet' == $payment_object) ? 'selected' : '' ) ?> >ставка азартной игры</option>
                            <option value="gambling_prize"         <? echo( ('gambling_prize' == $payment_object) ? 'selected' : '' ) ?> >выигрыш азартной игры</option>
                            <option value="lottery"                <? echo( ('lottery' == $payment_object) ? 'selected' : '' ) ?> >лотерейный билет</option>
                            <option value="lottery_prize"          <? echo( ('lottery_prize' == $payment_object) ? 'selected' : '' ) ?> >выигрыш лотереи</option>
                            <option value="intellectual_activity"  <? echo( ('intellectual_activity' == $payment_object) ? 'selected' : '' ) ?> >предоставление результатов интеллектуальной деятельности</option>
                            <option value="payment"                <? echo( ('payment' == $payment_object) ? 'selected' : '' ) ?> >платёж</option>
                            <option value="agent_commission"       <? echo( ('agent_commission' == $payment_object) ? 'selected' : '' ) ?> >агентское вознаграждение</option>
                            <option value="composite"              <? echo( ('composite' == $payment_object) ? 'selected' : '' ) ?> >составной предмет расчета</option>
                            <option value="another"                <? echo( ('another' == $payment_object) ? 'selected' : '' ) ?> >иной предмет расчета</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Использовать фиксированный НДС на все товары и услуги</td>
                    <td style="text-align: left;">
                        <label><input type="radio" name="vatOrderCheck" id="vatOrderCheck" value="1" <? echo( (1 == $vatOrderCheck) ? 'checked' : '' ) ?> onchange="ChangeVatOrder()"> ДА</label> <label><input type="radio" name="vatOrderCheck" value="0" <? echo( (0 == $vatOrderCheck) ? 'checked' : '' ) ?> onchange="ChangeVatOrder()"> НЕТ</label>
                    </td>
                </tr>
                <tr id="vatOrder" style="display: table-row;">
                    <td>НДС на товары и услуги</td>
                    <td>
                        <select style="width: 200px;text-align: center;" name="vatOrder">
                            <option value="none"                <? echo( ('none' == $vatOrder) ? 'selected' : '' ) ?> >БЕЗ НДС</option>
                            <option value="10"                  <? echo( ('10' == $vatOrder) ? 'selected' : '' ) ?> >10%</option>
                            <option value="18"                  <? echo( ('18' == $vatOrder) ? 'selected' : '' ) ?>>18%</option>
                            <option value="20"                  <? echo( ('20' == $vatOrder) ? 'selected' : '' ) ?>>20%</option>
                            <option value="110"                 <? echo( ('110' == $vatOrder) ? 'selected' : '' ) ?>>10/110%</option>
                            <option value="118"                 <? echo( ('118' == $vatOrder) ? 'selected' : '' ) ?>>18/118%</option>
                            <option value="120"                 <? echo( ('120' == $vatOrder) ? 'selected' : '' ) ?>>20/120%</option>
                            <option value="0"                   <? echo( ('0' == $vatOrder) ? 'selected' : '' ) ?>>0%</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>НДС на доставку</td><td>
                        <select style="width: 200px;text-align: center;" name="vatShipment">
                            <option value="none"                <? echo( ('none' == $vatShipment) ? 'selected' : '' ) ?> >БЕЗ НДС</option>
                            <option value="10"                  <? echo( ('10' == $vatShipment) ? 'selected' : '' ) ?> >10%</option>
                            <option value="18"                  <? echo( ('18' == $vatShipment) ? 'selected' : '' ) ?>>18%</option>
                            <option value="20"                  <? echo( ('20' == $vatShipment) ? 'selected' : '' ) ?>>20%</option>
                            <option value="110"                 <? echo( ('110' == $vatShipment) ? 'selected' : '' ) ?>>10/110%</option>
                            <option value="118"                 <? echo( ('118' == $vatShipment) ? 'selected' : '' ) ?>>18/118%</option>
                            <option value="120"                 <? echo( ('120' == $vatShipment) ? 'selected' : '' ) ?>>20/120%</option>
                            <option value="0"                   <? echo( ('0' == $vatShipment) ? 'selected' : '' ) ?>>0%</option>
                        </select>
                    </td>
                </tr>
                <tr id="vat100" style="display: table-row;">
                    <td>Использовать ставки НДС 10/110 и 20/120</td>
                    <td style="text-align: left;">
                        <label><input type="radio" name="vat100" value="1" <? echo( (1 == $vat100) ? 'checked' : '' ) ?> > ДА</label> <label><input type="radio" name="vat100" value="0" <? echo( (0 == $vat100) ? 'checked' : '' ) ?> > НЕТ</label>
                    </td>
                </tr>
            </table>
            <table style="font-size: 12px;width:415px;text-align: right;border: 2px solid #b7b7b7;border-radius: 15px;margin-top: 5px;padding: 5px;">
                <tr>
                    <td style="color: #bfbfbf">О компании</td><td></td>
                </tr>
                <tr>
                    <td>Email</td><td><input type="email" name="company_email" style="width: 300px;text-align: center;" value="<? echo(htmlspecialchars($companyEmail, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>СНО</td><td>
                        <select style="width: 300px;text-align: center;" name="company_sno">
                            <option value="osn"                 <? echo( ('osn' == $companySno) ? 'selected' : '' ) ?> >общая СН</option>
                            <option value="usn_income"          <? echo( ('usn_income' == $companySno) ? 'selected' : '' ) ?> >упрощенная СН (доходы)</option>
                            <option value="usn_income_outcome"  <? echo( ('usn_income_outcome' == $companySno) ? 'selected' : '' ) ?>>упрощенная СН(доходы минус расходы)</option>
                            <option value="envd"                <? echo( ('envd' == $companySno) ? 'selected' : '' ) ?>>единый налог на вмененный доход</option>
                            <option value="esn"                 <? echo( ('esn' == $companySno) ? 'selected' : '' ) ?>>единый сельскохозяйственный налог</option>
                            <option value="patent"              <? echo( ('patent' == $companySno) ? 'selected' : '' ) ?>>патентная СН</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>ИНН</td><td><input type="text" name="company_inn" style="width: 300px;text-align: center;" value="<? echo(htmlspecialchars($companyInn, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
                <tr>
                    <td>Место продаж</td><td><input type="text" name="company_payment_address" style="width: 300px;text-align: center;" value="<? echo(htmlspecialchars($companyPaymentAddress, ENT_QUOTES, 'UTF-8')); ?>"></td>
                </tr>
            </table>
            <table style="width:400px;text-align: right;margin-top: 5px;">
                <tr>
                    <td><input type="submit" value="ОБНОВИТЬ СПИСОК ПЛАТЁЖНЫХ СИСТЕМ" style="width:300px;padding: 4px;color: white;border: none;font-weight: bold;background-color: rgba(50, 94, 135, 0.87);cursor: pointer;border-radius: 5px;" onclick="document.getElementById('typeAction').value ='updatePaySystem';this.value='Отправляю...'" <? echo($buttonUpdatePaySystem); ?> ></td>
                    <td><input type="submit" value="СОХРАНИТЬ" style="width:100px;padding: 4px;color: white;border: none;font-weight: bold;background-color: rgba(50, 94, 135, 0.87);cursor: pointer;border-radius: 5px;" onclick="document.getElementById('typeAction').value ='updateSettings';this.value='Отправляю...'" ></td>
                </tr>
            </table>
            <input name="member_id" value="<?echo($_REQUEST['member_id']);?>" type="hidden" >
            <input name="type" value="" id="typeAction" type="hidden" >
        </form>
    </div>
    <div style="border: 2px solid #b7b7b7;border-radius: 15px;padding: 5px;">
        <div style="position:relative;width: 400px;font-size: 11px;">
            Адрес вебхука создания ссылки на оплату
            <br>
            <input style="width: 400px;font-size: 10px;padding: 5px;margin-top: 3px;" onClick="this.select();" value = '<? echo("https://ecomkassa-bitrix.mircloud.ru/geturl.php?mid=".$_REQUEST['member_id']."&sec=".substr($secretCode,0,8)."&did=[ИД СДЕЛКИ]&tid=[ИД СПОСОБА ОПЛАТЫ - 103 - сбербанк]");?>' readonly>
        </div>
        <div style="position:relative;width: 400px;margin-top:10px;font-size: 11px;">
            Адрес входящиего вебхука - будет срабатывать при успешной оплате
            <br>
            <form action='index.php' method="post" style="width: 400px;">
                <input style="width: 300px;font-size: 11px;padding: 5px;margin-top: 3px;" name="webHookUrl" value = '<? echo(htmlspecialchars($webHookUrl, ENT_QUOTES, 'UTF-8')); ?>' placeholder="http://example.com/Deal-Id={{ID}}">
                <input name="type" value="updateWebHook" type="hidden" >
                <input name="member_id" value="<?echo($_REQUEST['member_id']);?>" type="hidden" >
                <input style="font-size:12px;margin-left:5px;width: 90px;padding: 4px;color: white;background-color: rgba(50, 94, 135, 0.87);border: none;cursor: pointer;border-radius: 5px;" value="СОХРАНИТЬ" type="submit" >
            </form>
        </div>
    </div>
</body>
</html>