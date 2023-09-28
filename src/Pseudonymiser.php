<?php

namespace Gems\Pseudonymize;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Faker\Factory;
use Faker\Generator;

class Pseudonymiser
{
    protected readonly string $locale;

    protected readonly bool $seed;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly array $config,
    )
    {
        $this->locale = $config['pseudonymize']['fakerSettings']['locale'] ?? Factory::DEFAULT_LOCALE;
        $this->seed = $config['pseudonymize']['fakerSettings']['seed'] ?? false;
    }

    protected function filterRow(array $row, array $settings, Generator $faker): array
    {
        if (isset($settings['empty'])) {
            $row = $this->setEmpty($row, $settings['empty']);
        }
        if (isset($settings['generalize'])) {
            $row = $this->setGeneralized($row, $settings['generalize']);
        }
        if (isset($settings['fake'])) {
            $row = $this->setFake($row, $settings['fake'], $faker);
        }

        return $row;
    }
    public function emptyFields(array $fields): void
    {
        foreach($fields as $table => $settings) {
            if (isset($settings['empty'])) {
                $queryBuilder = $this->connection->createQueryBuilder();
                $queryBuilder->update($table);
                foreach($settings['empty'] as $fieldName) {
                    $queryBuilder->set($fieldName, null);
                }
                $queryBuilder->executeQuery();
            }
        }
    }

    protected function createFaker(): Generator
    {
        return Factory::create($this->locale);
    }

    protected function getFieldsFromSettings(array $settings): array
    {
        $fields = [];
        foreach($settings as $typeSettings) {
            if (is_string($typeSettings)) {
                $fields[] = $typeSettings;
                continue;
            }
            if (is_int(key($typeSettings))) {
                $fields = array_merge($fields, $typeSettings);
                continue;
            }
            $fields = array_merge($fields, array_keys($typeSettings));
        }

        return $fields;
    }

    protected function getUpdateQuery(string $tableName, array $row, array $keys): QueryBuilder
    {
        $querybuilder = $this->connection->createQueryBuilder();
        $querybuilder->update($tableName);
        foreach($row as $key => $value) {
            $querybuilder->set($key, $querybuilder->createNamedParameter($value));
        }

        $first = true;
        foreach($keys as $key) {
            if (!isset($row[$key])) {
                throw new \Exception(sprintf('Key %s not found in row', $key));
            }
            if ($first) {
                $querybuilder->where($key . ' = ' . $querybuilder->createNamedParameter($row[$key]));
                $first = false;
                continue;
            }
            $querybuilder->andWhere($key . ' = ' . $querybuilder->createNamedParameter($row[$key]));
        }

        return $querybuilder;
    }

    public function processFields(array $fields): void
    {

        $start = microtime(true);

        $faker = $this->createFaker();

        foreach($fields as $table => $settings) {
            $fields = $this->getFieldsFromSettings($settings);
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select(...$fields)
                ->from($table);

            $resultSet = $queryBuilder->executeQuery();

            while ($row = $resultSet->fetchAssociative()) {

                if ($this->seed && isset($settings['seedField'], $row[$settings['seedField']])) {
                    $faker->seed($row[$settings['seedField']]);
                }

                $filteredRow = $this->filterRow($row, $settings, $faker);

                $updateQuery = $this->getUpdateQuery($table, $filteredRow, $settings['key']);

                print_r($row);
                print_r($filteredRow);
                echo $updateQuery->getSql() . "\n";
                print_r($updateQuery->getParameters());
            }
        }

        echo sprintf('Finished in %s sec.', (microtime(true) - $start));
    }

    protected function setEmpty(array $row, array $emptySettings): array
    {
        foreach($emptySettings as $fieldName) {
            if (isset($row[$fieldName])) {
                $row[$fieldName] = null;
            }
        }

        return $row;
    }

    protected function setFake(array $row, array $fakeSettings, Generator $faker): array
    {
        foreach($fakeSettings as $fieldName => $methodName) {
            /*if ($methodName !== null && !method_exists($faker, $methodName)) {
                throw new \Exception(sprintf('Faker does not have method %s', $methodName));
            }*/
            if ($methodName === null) {
                continue;
            }
            if ($methodName === 'firstName' && isset($row['grs_gender'])) {
                $gender = match($row['grs_gender']) {
                    'M' => 'male',
                    'F' => 'female',
                    default => null,
                };
                $firstName = $faker->firstName($gender);
                $row[$fieldName] = $firstName;
                if (array_key_exists('grs_initials_name', $fakeSettings)) {
                    $row['grs_initials_name'] = $firstName[0] . '.';
                }
                continue;
            }

            if ($methodName === 'lastName')  {
                $lastName = $faker->lastName();

                $commonPrefixes = ['de', 'van', 'van de', 'van der'];

                $row['grs_surname_prefix'] = null;

                foreach($commonPrefixes as $prefix) {
                    if (str_starts_with($lastName, $prefix . ' ')) {
                        $lastName = str_replace($prefix . ' ', '', $lastName);
                        $row['grs_surname_prefix'] = $prefix;
                    }
                }
                $row[$fieldName] = $lastName;
                continue;
            }

            $row[$fieldName] = $faker->$methodName;
        }

        return $row;
    }

    protected function setGeneralized(array $row, array $generalizeSettings): array
    {
        foreach($generalizeSettings as $fieldName => $settings) {
            if ($row[$fieldName] !== null) {
                if (is_array($settings)) {
                    if (isset($settings['date'])) {
                        $date = DateTimeImmutable::createFromFormat('Y-m-d', $row[$fieldName]);
                        $year = $settings['date']['year'] ?? $date->format('Y');
                        $month = $settings['date']['month'] ?? $date->format('m');
                        $day = $settings['date']['day'] ?? $date->format('d');
                        $newDate = $date->setDate($year, $month, $day);
                        $row[$fieldName] = $newDate->format('Y-m-d');
                        continue;
                    }

                    if (isset($settings['datetime'])) {
                        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row[$fieldName]);

                        $year = $settings['datetime']['year'] ?? $date->format('Y');
                        $month = $settings['datetime']['month'] ?? $date->format('m');
                        $day = $settings['datetime']['day'] ?? $date->format('d');
                        $newDate = $date->setDate($year, $month, $day);

                        $hour = $settings['datetime']['hour'] ?? $date->format('H');
                        $minute = $settings['datetime']['minute'] ?? $date->format('i');
                        $seconds = $settings['datetime']['second'] ?? $date->format('s');
                        $newDate = $newDate->setTime($hour, $minute, $seconds);

                        $row[$fieldName] = $newDate->format('Y-m-d H:i:s');
                        continue;
                    }

                    if (isset($settings['email'])) {
                        $newParts = [];
                        foreach($settings['email'] as $setting) {
                            if (str_starts_with($setting, '{') && str_ends_with($setting, '}') && isset($row[substr($setting, 1, -1)])) {
                                $setting = $row[substr($setting, 1, -1)];
                            }
                            $newParts[] = $setting;
                        }
                        $row[$fieldName] = join('', $newParts);
                    }
                } else {
                    $row[$fieldName] = $settings;
                }
            }
        }

        return $row;
    }
}