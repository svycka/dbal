<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\MySQL;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function preg_match;
use function stripos;
use function version_compare;

/**
 * Abstract base implementation of the {@link Driver} interface for MySQL based drivers.
 */
abstract class AbstractMySQLDriver implements VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws DBALException
     */
    public function createDatabasePlatformForVersion(string $version): AbstractPlatform
    {
        $mariadb = stripos($version, 'mariadb') !== false;
        if ($mariadb && version_compare($this->getMariaDbMysqlVersionNumber($version), '10.2.7', '>=')) {
            return new MariaDb1027Platform();
        }

        if (! $mariadb) {
            $oracleMysqlVersion = $this->getOracleMysqlVersionNumber($version);
            if (version_compare($oracleMysqlVersion, '8', '>=')) {
                return new MySQL80Platform();
            }

            if (version_compare($oracleMysqlVersion, '5.7.9', '>=')) {
                return new MySQL57Platform();
            }
        }

        return $this->getDatabasePlatform();
    }

    /**
     * Get a normalized 'version number' from the server string
     * returned by Oracle MySQL servers.
     *
     * @param string $versionString Version string returned by the driver, i.e. '5.7.10'
     *
     * @throws DBALException
     */
    private function getOracleMysqlVersionNumber(string $versionString): string
    {
        if (
            preg_match(
                '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/',
                $versionString,
                $versionParts
            ) === 0
        ) {
            throw InvalidPlatformVersion::new(
                $versionString,
                '<major_version>.<minor_version>.<patch_version>'
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? null;

        if ($majorVersion === '5' && $minorVersion === '7' && $patchVersion === null) {
            $patchVersion = '9';
        }

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
    }

    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     *
     * @throws DBALException
     */
    private function getMariaDbMysqlVersionNumber(string $versionString): string
    {
        if (
            preg_match(
                '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
                $versionString,
                $versionParts
            ) === 0
        ) {
            throw InvalidPlatformVersion::new(
                $versionString,
                '^(?:5\.5\.5-)?(mariadb-)?<major_version>.<minor_version>.<patch_version>'
            );
        }

        return $versionParts['major'] . '.' . $versionParts['minor'] . '.' . $versionParts['patch'];
    }

    /**
     * {@inheritdoc}
     *
     * @return MySqlPlatform
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new MySqlPlatform();
    }

    /**
     * {@inheritdoc}
     *
     * @return MySqlSchemaManager
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return new MySqlSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new MySQL\ExceptionConverter();
    }
}
