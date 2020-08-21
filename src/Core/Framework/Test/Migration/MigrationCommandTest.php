<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Migration\Command\MigrationCommand;
use Shopware\Core\Framework\Migration\Command\MigrationDestructiveCommand;
use Shopware\Core\Framework\Migration\Exception\MigrateException;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MigrationCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MigrationTestBehaviour;

    protected function tearDown(): void
    {
        $connection = $this->getConnection();

        $connection->createQueryBuilder()
            ->delete('migration')
            ->where("`class` LIKE '%_test_migrations_valid%'")
            ->execute();
    }

    public function getCommand(): MigrationCommand
    {
        return $this->getContainer()->get(MigrationCommand::class);
    }

    public function getDestructiveCommand(): MigrationDestructiveCommand
    {
        return $this->getContainer()->get(MigrationDestructiveCommand::class);
    }

    public function testCommandMigrateNoUntilNoAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $this->expectException(\InvalidArgumentException::class);
        $command->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testCommandMigrateAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getCommand();

        $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getCommand();

        $command->run(new ArrayInput(['until' => PHP_INT_MAX, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandMigrateMigrationException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        try {
            $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]), new BufferedOutput());
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(3, $this->getMigrationCount(true));
    }

    public function testDestructiveCommandMigrateNoUntilNoAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getDestructiveCommand();

        $this->expectException(\InvalidArgumentException::class);
        $command->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testDestructiveCommandMigrateAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getDestructiveCommand();

        $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testDestructiveCommandAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getDestructiveCommand();

        $command->run(new ArrayInput(['until' => PHP_INT_MAX, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandMigrateMigrationDestructive(): void
    {
        static::assertSame(0, $this->getMigrationCount(true, true));

        $command = $this->getCommand();

        try {
            $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]), new BufferedOutput());
        } catch (MigrateException $e) {
            //nth
        }

        $command = $this->getDestructiveCommand();

        try {
            $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]), new BufferedOutput());
        } catch (MigrateException $e) {
            //nth
        }

        static::assertSame(2, $this->getMigrationCount(true, true));
    }

    public function testCommandMigrate(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithoutMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $connection = $this->getConnection();
        $loader = $this->getMockBuilder(MigrationCollectionLoader::class)->disableOriginalConstructor()->getMock();

        $loader->expects(static::once())->method('collect')->willReturn(
            new MigrationCollection(
                new MigrationSource(''),
                new MigrationRuntime($connection, new NullLogger()),
                $connection
            )
        );

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects(static::never())->method('clear');

        $command = new MigrationCommand($loader, $cache);

        $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(0, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithOneMigration(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects(static::once())->method('clear');

        $command = new MigrationCommand($this->getContainer()->get(MigrationCollectionLoader::class), $cache);

        $command->run(new ArrayInput(['--all' => true, '--limit' => 1, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(1, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithTwoMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects(static::once())->method('clear');

        $command = new MigrationCommand($this->getContainer()->get(MigrationCollectionLoader::class), $cache);

        $command->run(new ArrayInput(['--all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount(true));
    }

    private function getConnection(): Connection
    {
        return $this->getContainer()->get(Connection::class);
    }

    private function getMigrationCount(bool $executed = false, bool $destructive = false): int
    {
        $connection = $this->getConnection();

        $query = $connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('migration')
            ->where("`class` LIKE '%_test_migrations_valid%'");

        if ($executed && $destructive) {
            $query->andWhere('`update_destructive` IS NOT NULL');
        } elseif ($executed && !$destructive) {
            $query->andWhere('`update` IS NOT NULL');
        }

        return (int) $query->execute()->fetchColumn();
    }
}
