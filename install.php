<?php
require_once (__DIR__.'/crest.php');


$result = CRest::installApp();

if ($result['rest_only'] === false) {
    ?>
    <head>
        <script src="//api.bitrix24.com/api/v1/"></script>
        <?php if ($result['install'] == true): ?>
            <script>
                BX24.init(function(){
                    BX24.installFinish();
                });
            </script>
        <?php endif; ?>
    </head>
    <body>
        <?php if ($result['install'] == true): ?>
            installation has been finished
        <?php else: ?>
            <?php echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>"; ?>
            installation error
        <?php endif; ?>
    </body>
    <?php
}

// Регистрация точек встраивания
if ($result['install'] == true) {
    // Регистрация обработчика пользовательского типа поля
    $handlerUrl = 'https://office.elearningrb.ru/apps/crm_field_time/app.php';
    $type = 'phone_data';
    $propCode = 'PHONE_DATA'; // max length with prefix UF_CRM_ 20 char

    // Добавление пользовательского типа поля
    $addTypeResult = CRest::call(
        'userfieldtype.add',
        [
            'USER_TYPE_ID' => $type,
            'HANDLER' => $handlerUrl,
            'TITLE' => 'custom type title',
            'DESCRIPTION' => 'custom description ' . $type
        ]
    );

    // Проверка и добавление пользовательского поля в лид
    if (empty($addTypeResult['error'])) {
        $addFieldResult = CRest::call(
            'crm.lead.userfield.add',
            [
                'fields' => [
                    'USER_TYPE_ID' => $type,
                    'FIELD_NAME' => $propCode,
                    'XML_ID' => $propCode,
                    'MANDATORY' => 'N',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'EDIT_FORM_LABEL' => 'My string',
                    'LIST_COLUMN_LABEL' => 'My string description',
                    'SETTINGS' => []
                ]
            ]
        );
        // Можно добавить обработку ошибок или логирование при необходимости
    }
}