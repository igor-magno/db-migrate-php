<?php

class MigrationController
{
    private $host;
    private $dbport;
    private $dbname;
    private $username;
    private $password;
    private $connection;

    public function __construct()
    {
        echo "\033[34m ________ MIGRATE ________\033[0m\n";

        $this->host = getenv('DATA_BASE_HOST');
        $this->dbport = getenv('DATA_BASE_PORT');
        $this->dbname = getenv('DATA_BASE_NAME');
        $this->username = getenv('DATA_BASE_USER');
        $this->password = getenv('DATA_BASE_PASSWORD');

        try {
            echo "\033[32mConnecting to database...\033[0m\n";
            $this->connection = new \PDO(
                "mysql:host=$this->host;port=$this->dbport;dbname=$this->dbname",
                $this->username,
                $this->password
            );
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            echo "\033[32mConnected to database successfully!\033[0m\n";
        } catch (\PDOException $e) {
            echo "\033[31m" . str_replace("\n", "\n  ", $e->getMessage()) .
                 "\n\n" . str_replace("\n", "\n  ", $e->getTraceAsString()) .
                 "\033[0m\n";
            exit(1);
        } catch (\Throwable $e) {
            echo "\033[31m" . str_replace("\n", "\n  ", $e->getMessage()) .
                 "\n\n" . str_replace("\n", "\n  ", $e->getTraceAsString()) .
                 "\n\n  File: " . $e->getFile() .
                 "\n  Line: " . $e->getLine() .
                 "\033[0m\n";
            exit(1);
        }
    }

    public function migrate($migrationFile = null)
    {
        echo "\033[32mReading migration files...\033[0m\n";
        $migrations = array_diff(scandir('migrations'), ['.', '..']);
        sort($migrations);
        echo "\033[32mMigration files read successfully!\033[0m\n";

        $executedMigrations = $this->getExecutedMigrations();

        foreach ($migrations as $migration) {
            if ($migrationFile !== null && $migration !== $migrationFile) {
                continue;
            }

            if (in_array($migration, $executedMigrations)) {
                continue;
            }

            include_once __DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $migration;
            
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $className = explode('_', $className);
            array_shift($className);
            $className = array_map('ucfirst', $className);
            $className = implode('', $className);

            $migrationObject = new $className($this->connection);

            echo "\033[33mExecuting migration: $migration\033[0m\n";
            $migrationObject->up();
            $this->markMigrationAsExecuted($migration);
            echo "\033[32mMigration executed successfully!\033[0m\n";
        }
        
        echo "\033[32mMigration completed successfully!\033[0m\n";
    }

    public function rollback($migrationFile = null)
    {
        echo "\033[32mReading migration files...\033[0m\n";
        $migrations = array_diff(scandir('migrations'), ['.', '..']);
        rsort($migrations);
        echo "\033[32mMigration files read successfully!\033[0m\n";

        $executedMigrations = $this->getExecutedMigrations();

        foreach ($migrations as $migration) {
            if ($migrationFile !== null && $migration !== $migrationFile) {
                continue;
            }

            if (!in_array($migration, $executedMigrations)) {
                continue;
            }

            include_once __DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $migration;
            
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $className = explode('_', $className);
            array_shift($className);
            $className = array_map('ucfirst', $className);
            $className = implode('', $className);

            $migrationObject = new $className($this->connection);

            echo "\033[33mRolling back migration: $migration\033[0m\n";
            $migrationObject->down();
            $this->removeExecutedMigration($migration);
            echo "\033[32mRollback executed successfully!\033[0m\n";
        }
        
        echo "\033[32mRollback completed successfully!\033[0m\n";
    }

    public function status()
    {
        $executedMigrations = $this->getExecutedMigrations();

        echo "\033[34m ======= Migration Status =======\033[0m\n";
        echo "\033[34m Executed Migrations:\033[0m\n";

        foreach ($executedMigrations as $migration) {
            echo "- $migration\n";
        }

        echo "\n";
        echo "\033[34m Pending Migrations:\033[0m\n";

        $migrations = array_diff(scandir('migrations'), ['.', '..']);
        sort($migrations);

        foreach ($migrations as $migration) {
            if (!in_array($migration, $executedMigrations)) {
                echo "- $migration\n";
            }
        }

        echo "\n";
        echo "\033[34m ==============================\033[0m\n";
    }

    public function run($command, $migrationFile = null)
    {
        switch ($command) {
            case 'run':
                $this->migrate($migrationFile);
                break;
            case 'rollback':
                $this->rollback($migrationFile);
                break;
            case 'status':
                $this->status();
                break;
            default:
                $this->migrate();
                break;
        }
    }

    private function getExecutedMigrations(): array
    {
        try {
            $stmt = $this->connection->prepare("SELECT migration FROM migrations");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $th) {
            $exception_code_for_base_table_or_view_not_found = '42S02';
            if($th->getCode() == $exception_code_for_base_table_or_view_not_found) {
                $this->connection->exec("
                    CREATE TABLE migrations (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL
                    )
                ");
                return [];
            } else {
                throw $th;
            }
        }
    }

    private function markMigrationAsExecuted(string $migration)
    {
        $stmt = $this->connection->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$migration]);
    }

    private function removeExecutedMigration(string $migration)
    {
        $stmt = $this->connection->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
}

$controller = new MigrationController();
$command = $argv[1] ?? '';
$migrationFile = $argv[2] ?? null;
$controller->run($command, $migrationFile);
