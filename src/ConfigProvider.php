<?php

namespace Gems\Pseudonymize;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'pseudonymize' => [
                'fakerSettings' => [
                    'seed' => true,
                    'locale' => 'nl_NL',
                ],
                'fields' => [
                    'gems__respondents' => [
                        'key' => [
                            'grs_id_user',
                        ],
                        'seedField' => 'grs_id_user',
                        'fake' => [
                            'grs_gender' => null,
                            'grs_first_name' => 'firstName',
                            'grs_surname_prefix' => null,

                            'grs_last_name' => 'lastName',
                        ],
                        'generalize' => [
                            'grs_birthday' => [
                                'date' => [
                                    'day' => 15,
                                ],
                            ],
                        ],
                        'empty' => [
                            //'grs_raw_surname_prefix',
                            //'grs_raw_last_name',
                            //'grs_partner_surname_prefix',
                            //'grs_partner_last_name',
                            //'grs_last_name_order',
                            'grs_address_1',
                            'grs_address_2',
                            'grs_zipcode',
                            'grs_city',
                            'grs_phone_1',
                            'grs_phone_2',
                            //'grs_phone_3',
                        ],
                    ],
                    'gems__respondent2org' => [
                        'key' => [
                            'gr2o_patient_nr',
                            'gr2o_id_organization',
                        ],
                        'seedField' => 'gr2o_id_user',
                        'generalize' => [
                            'gr2o_email' => [
                                'email' => [
                                    '{gr2o_patient_nr}',
                                    '@example.test',
                                ],
                            ],
                        ],
                    ],
                    'gems__appointments' => [
                        'key' => [
                            'gap_id_appointment',
                        ],
                        'seedField' => 'gap_id_user',
                        'generalize' => [
                            'gap_admission_time' => [
                                'datetime' => [
                                    'hour' => 9,
                                    'minute' => 0,
                                    'second' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}