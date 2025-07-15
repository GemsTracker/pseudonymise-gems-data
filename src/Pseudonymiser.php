<?php

namespace Gems\Pseudonymise;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Faker\Factory;
use Faker\Generator;
use Gems\Pseudonymise\Log\PseudonymiserLoggerInterface;

class Pseudonymiser
{
    protected readonly string $locale;

    protected array $perRowFilter = ['fake'];

    protected readonly bool $seed;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly array $config,
        protected readonly PseudonymiserLoggerInterface|null $logger,
    )
    {
        $this->locale = $config['pseudonymise']['fakerSettings']['locale'] ?? Factory::DEFAULT_LOCALE;
        $this->seed = $config['pseudonymise']['fakerSettings']['seed'] ?? false;
    }

    public function createFaker(): Generator
    {
        return Factory::create($this->locale);
    }

    protected function createFromFormat(string $format, mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        return DateTimeImmutable::createFromFormat($format, $value);
    }

    protected function filterRow(array $row, array $settings, Generator $faker): array
    {
        if (in_array('empty', $this->perRowFilter) && isset($settings['empty'])) {
            $row = $this->setEmpty($row, $settings['empty']);
        }
        if (in_array('generalize', $this->perRowFilter) && isset($settings['generalize'])) {
            $row = $this->setGeneralizedPerRow($row, $settings['generalize']);
        }
        if (in_array('fake', $this->perRowFilter) && isset($settings['fake'])) {
            $row = $this->setFake($row, $settings['fake'], $faker);
        }

        return $row;
    }

    protected function getFieldsFromSettings(array $settings): array
    {
        $fields = [];
        foreach($settings as $typeName => $typeSettings) {
            if ($typeName !== 'key' && $typeName !== 'seedField' && !in_array($typeName, $this->perRowFilter)) {
                continue;
            }
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

        return array_unique($fields);
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

    protected function hasRowSettings(array $tableSettings): bool
    {
        foreach($tableSettings as $typeName => $typeSettings) {
            if (in_array($typeName, $this->perRowFilter)) {
                return true;
            }
        }
        return false;
    }

    protected function logQuery(QueryBuilder $queryBuilder, string $prefix = ''): void
    {
        $query = $queryBuilder->getSQL();
        if ($queryBuilder->getParameters()) {
            $query .= ' (' . join(', ', $queryBuilder->getParameters()) . ')';
            print_r($queryBuilder->getParameters());
        }

        $this->logger->log(sprintf('%s %s;', $prefix, $query));
    }

    public function process(array $fields, bool $testRun = true): void
    {
        $this->processBulkQueries($fields, $testRun);
        $this->processFields($fields, $testRun);
    }

    public function processBulkQueries(array $fields, bool $testRun = true): void
    {
        foreach($fields as $table => $settings) {
            $start = microtime(true);
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder->update($table);

            if (isset($settings['empty'])) {
                foreach($settings['empty'] as $emptyField) {
                    $queryBuilder->set($emptyField, 'NULL');
                }
            }
            if (isset($settings['generalize'])) {
                $this->addGeneralizeToQuery($settings['generalize'], $queryBuilder);
            }

            if ($testRun === false) {
                $queryBuilder->executeQuery();
            }
            $time = number_format(microtime(true) - $start, 3);

            $prefix = sprintf('[%s] ', $time);

            $this->logQuery($queryBuilder, $prefix);
        }
    }

    public function processFields(array $fields, bool $testRun = true): void
    {
        $faker = $this->createFaker();

        foreach($fields as $table => $settings) {

            if (!$this->hasRowSettings($settings)) {
                continue;
            }


            $fields = $this->getFieldsFromSettings($settings);
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select(...$fields)
                ->from($table);

            // Clone the QueryBuilder for the count query
            $countQueryBuilder = clone $queryBuilder;
            $countQueryBuilder->select('COUNT(*) AS total');

            // Execute the count query
            $countResult = $countQueryBuilder->executeQuery()->fetchAssociative();
            $totalCount = (int) $countResult['total'];

            $resultSet = $queryBuilder->executeQuery();

            $i = 1;
            while ($row = $resultSet->fetchAssociative()) {
                $start = microtime(true);
                if ($this->seed && isset($settings['seedField'], $row[$settings['seedField']])) {
                    $faker->seed($row[$settings['seedField']]);
                }

                $filteredRow = $this->filterRow($row, $settings, $faker);

                $updateQuery = $this->getUpdateQuery($table, $filteredRow, $settings['key']);

                $time = number_format(microtime(true) - $start, 3);
                $prefix = sprintf('[%s/%s] [%s] ', $i, $totalCount, $time);
                if ($testRun === false) {
                    $updateQuery->executeQuery();
                }
                $this->logQuery($updateQuery, $prefix);
                $i++;
            }
        }

        echo sprintf('Finished in %s sec.', (microtime(true) - $start));
    }

    public function setEmpty(array $row, array $emptySettings): array
    {
        foreach($emptySettings as $fieldName) {
            if (isset($row[$fieldName])) {
                $row[$fieldName] = null;
            }
        }

        return $row;
    }

    public function setFake(array $row, array $fakeSettings, Generator $faker): array
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

    protected function addGeneralizeToQuery(array $generalizeSettings, QueryBuilder $queryBuilder): void
    {
        foreach($generalizeSettings as $fieldName => $settings) {
            if (is_array($settings)) {
                if (isset($settings['date'])) {

                    $year = $settings['date']['year'] ?? "DATE_FORMAT($fieldName, '%Y')";
                    $month = $settings['date']['month'] ?? "DATE_FORMAT($fieldName, '%m')";
                    $day = $settings['date']['day'] ?? "DATE_FORMAT($fieldName, '%d')";

                    $update = "CONCAT($year, '-', $month, '-', $day)";
                    $queryBuilder->set($fieldName, $update);

                    continue;
                }

                if (isset($settings['datetime'])) {

                    $year = $settings['datetime']['year'] ?? "DATE_FORMAT($fieldName, '%Y')";
                    $month = $settings['datetime']['month'] ?? "DATE_FORMAT($fieldName, '%m')";
                    $day = $settings['datetime']['day'] ?? "DATE_FORMAT($fieldName, '%d')";

                    $hour = $settings['datetime']['hour'] ?? "DATE_FORMAT($fieldName, '%H')";
                    $minute = $settings['datetime']['minute'] ?? "DATE_FORMAT($fieldName, '%i')";
                    $second = $settings['datetime']['second'] ?? "DATE_FORMAT($fieldName, '%s')";

                    $update = "CONCAT($year, '-', $month, '-', $day, ' ',  $hour, ':', $minute, ':', $second)";
                    $queryBuilder->set($fieldName, $update);

                    continue;
                }

                if (isset($settings['email'])) {
                    $newParts = [];
                    foreach($settings['email'] as $setting) {
                        if (str_starts_with($setting, '{') && str_ends_with($setting, '}')) {
                            $setting = substr($setting, 1, -1);
                            $newParts[] = $setting;
                            continue;
                        }
                        $newParts[] = "'$setting'";
                    }

                    $queryBuilder->set($fieldName, 'CONCAT(' . join(',', $newParts) . ')');
                }
            } else {
                $queryBuilder->set($fieldName, "'$settings'");
            }
        }
    }

    public function setGeneralizedPerRow(array $row, array $generalizeSettings): array
    {
        foreach($generalizeSettings as $fieldName => $settings) {
            if (array_key_exists($fieldName, $row) && ($row[$fieldName] !== null)) {
                if (is_array($settings)) {
                    if (isset($settings['date'])) {
                        $date = $this->createFromFormat('Y-m-d', $row[$fieldName]);
                        $year = $settings['date']['year'] ?? $date->format('Y');
                        $month = $settings['date']['month'] ?? $date->format('m');
                        $day = $settings['date']['day'] ?? $date->format('d');
                        $newDate = $date->setDate($year, $month, $day);
                        $row[$fieldName] = $newDate->format('Y-m-d');
                        continue;
                    }

                    if (isset($settings['datetime'])) {
                        $date = $this->createFromFormat('Y-m-d H:i:s', $row[$fieldName]);

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
