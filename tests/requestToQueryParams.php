<?php

$request = array (
    'leads' =>
        array (
            'update' =>
                array (
                    0 =>
                        array (
                            'id' => '25099973',
                            'name' => '',
                            'status_id' => '34403122',
                            'price' => '1',
                            'responsible_user_id' => '2475916',
                            'last_modified' => '1618831869',
                            'modified_user_id' => '2475916',
                            'created_user_id' => '2475916',
                            'date_create' => '1618831868',
                            'pipeline_id' => '3469660',
                            'tags' =>
                                array (
                                    0 =>
                                        array (
                                            'id' => '1144047',
                                            'name' => 'КАТ В',
                                        ),
                                ),
                            'custom_fields' =>
                                array (
                                    0 =>
                                        array (
                                            'id' => '553079',
                                            'name' => 'Проверено',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => '0',
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'id' => '579199',
                                            'name' => 'Место обучения',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'Железнодорожный',
                                                        ),
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'id' => '579167',
                                            'name' => 'Дата заключения договора',
                                            'values' =>
                                                array (
                                                    0 => '1617235200',
                                                ),
                                        ),
                                    3 =>
                                        array (
                                            'id' => '542329',
                                            'name' => 'Скидка',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'Сертификат-100%',
                                                        ),
                                                ),
                                        ),
                                    4 =>
                                        array (
                                            'id' => '542327',
                                            'name' => 'Откуда узнал',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'Друзья или Знакомые',
                                                        ),
                                                ),
                                        ),
                                    5 =>
                                        array (
                                            'id' => '389857',
                                            'name' => 'Пакет',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'Левые',
                                                        ),
                                                ),
                                        ),
                                    6 =>
                                        array (
                                            'id' => '414085',
                                            'name' => 'Кол-во часов',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => '56',
                                                        ),
                                                ),
                                        ),
                                    7 =>
                                        array (
                                            'id' => '405003',
                                            'name' => 'Категория',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'В',
                                                        ),
                                                ),
                                        ),
                                    8 =>
                                        array (
                                            'id' => '389859',
                                            'name' => 'Коробка',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => 'МКПП',
                                                        ),
                                                ),
                                        ),
                                    9 =>
                                        array (
                                            'id' => '580073',
                                            'name' => 'Группа',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => '28к',
                                                        ),
                                                ),
                                        ),
                                    10 =>
                                        array (
                                            'id' => '552815',
                                            'name' => 'Остаток',
                                            'values' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'value' => '1',
                                                        ),
                                                ),
                                        ),
                                ),
                            'created_at' => '1618831868',
                            'updated_at' => '1618831869',
                        ),
                ),
        ),
    'account' =>
        array (
            'id' => '20156284',
            'subdomain' => 'mailjob',
            '_links' =>
                array (
                    'self' => 'https://mailjob.amocrm.ru',
                ),
        ),
);

$query = http_build_query($request);
print_r($query);
