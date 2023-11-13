<?php
    require_once (__DIR__.'/lib.php');

    if( !isset($_REQUEST['secret']) || !isset($_REQUEST['externalId']) ){
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM bills WHERE `external_id` = ? and `secret` = ?");
    $stmt->execute([$_REQUEST['externalId'],$_REQUEST['secret']]);
    $bill = $stmt->fetch(PDO::FETCH_LAZY);
    if( $bill['id'] > 0 )
    {
        if( $bill['PAYSYSTEM_ID'] > 0 ){
            CRest::call(
                'sale.paysystem.pay.payment', $bill['member_id'], [ "payment_id" => $bill['PAYMENT_ID'], "pay_system_id"=> $bill['PAYSYSTEM_ID'] ]
            );
        }
        else
        {
            $stmt = $db->prepare("SELECT * FROM users WHERE `member_id` = ?");
            $stmt->execute([$bill['member_id']]);
            $user = $stmt->fetch(PDO::FETCH_LAZY);

            if( $user['id'] > 0 && $user['webHookUrl'] )
            {
                $paramurl = str_replace('{{ID}}', $bill['dealid'], $user['webHookUrl']);
                if ($curl = curl_init()) {
                    curl_setopt($curl, CURLOPT_URL,$paramurl);
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                    curl_setopt($curl, CURLOPT_TIMECONDITION, 60);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
                    curl_exec($curl);
                    curl_close($curl);
                }
                //SendTg('383404884', $paramurl);
            }
        }
        $query = "UPDATE `bills` SET `status` = :status WHERE `id` = :id";
        $params = [
            ':id' => $bill['id'],
            ':status' => 'paid'
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }
    exit;
?>