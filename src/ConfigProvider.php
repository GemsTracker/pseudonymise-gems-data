<?php

namespace Gems\Pseudonymise;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'pseudonymise' => [
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
                            'grs_first_name' => 'test',
                            'grs_surname_prefix' => null,
                            'grs_initials_name' => 'T.',
                            'grs_last_name' => 'TEST'
                        ],
                        'empty' => [
                            'grs_address_1',
                            'grs_address_2',
                            'grs_zipcode',
                            'grs_city',
                            'grs_phone_1',
                            'grs_phone_2',
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
                                    '@',
                                    '{gr2o_id_organization}',
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