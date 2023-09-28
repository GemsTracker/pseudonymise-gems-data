<?php

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'pseudonymize' => [
                'fake' => [
                    'grs_first_name',
                    'grs_surname_prefix',
                    'grs_last_name',
                    'gr2o_email',
                ],
                'generalize' => [
                    'grs_birthday',
                ],
                'empty' => [
                    'grs_address_1',
                    'grs_address_2',
                    'grs_zipcode',


                ],
            ],
        ];
    }
}