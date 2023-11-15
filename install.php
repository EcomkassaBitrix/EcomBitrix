<?php
require_once (__DIR__.'/crest.php');
echo('1');
$stmt = $db->prepare("SELECT * FROM users WHERE `id` = ?");
$id = 1;
$stmt->execute([$id]);
$userData = $stmt->fetch(PDO::FETCH_LAZY);
echo($userData['unix_install']);
exit;
$result = CRest::installApp();
if($result['rest_only'] === false):?>
    <head>
        <script src="//api.bitrix24.com/api/v1/"></script>
        <?php if($result['install'] == true):?>
            <script>
                BX24.init(function(){
                    BX24.installFinish();
                });
            </script>
        <?php endif;?>
    </head>
    <body>
    <?php if($result['install'] == true):?>
        installation has been finished
    <?php else:?>
        installation error
    <?php endif;?>
    </body>
<?php endif;