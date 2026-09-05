<?php
namespace SRMS\Database;

use PDO;
use PDOException;
use PDOStatement;

class DatabaseConnection {
    protected PDO $pdo;
    protected array $queryLog = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function getQueryLog(): array {
        return $this->queryLog;
    }

    public function clearQueryLog(): void {
        $this->queryLog = [];
    }

    public function prepare(string $sql): PDOStatement {
        return $this->pdo->prepare($sql);
    }

    /**
     * Executes a parameterized query using prepared statements.
     * Prevents SQL injection by ensuring all parameters are bound safely.
     */
    public function execute(string $sql, array $params = []): PDOStatement {
        $this->queryLog[] = ['sql' => $sql, 'params' => $params];
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->handleException($e);
        }
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->execute($sql, $params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function insert(string $table, array $data): int {
        if (empty($data)) {
            throw new ValidationException("Cannot insert empty dataset into $table");
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $key => $value) {
            $params[":$key"] = $value;
        }

        $this->execute($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $conditions): int {
        if (empty($data)) {
            return 0;
        }
        if (empty($conditions)) {
            throw new ValidationException("Update requires conditions to prevent accidental full-table modification");
        }

        $setParts = [];
        $params = [];
        foreach ($data as $col => $val) {
            $setParts[] = "$col = :set_$col";
            $params[":set_$col"] = $val;
        }

        $whereParts = [];
        foreach ($conditions as $col => $val) {
            $whereParts[] = "$col = :where_$col";
            $params[":where_$col"] = $val;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    public function delete(string $table, array $conditions): int {
        if (empty($conditions)) {
            throw new ValidationException("Delete requires conditions to prevent accidental full-table deletion");
        }

        $whereParts = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $whereParts[] = "$col = :where_$col";
            $params[":where_$col"] = $val;
        }

        $sql = sprintf("DELETE FROM %s WHERE %s", $table, implode(' AND ', $whereParts));
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool {
        return $this->pdo->inTransaction();
    }

    public function transaction(callable $callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    protected function handleException(PDOException $e): void {
        $sqlState = (string)$e->getCode();
        $message = $e->getMessage();
        $errorInfo = $e->errorInfo ?? [];
        $vendorCode = (int)($errorInfo[1] ?? 0);

        // Foreign Key Violation
        // MySQL: 1452, 1216, 1217; Postgres: 23503; SQLite: 787
        if ($vendorCode === 1452 || $vendorCode === 1216 || $vendorCode === 1217 || $vendorCode === 787 || stripos($message, 'foreign key') !== false) {
            throw new ForeignKeyConstraintException("Foreign key violation: Referenced parent record does not exist.", 1452, $e);
        }

        // Unique Constraint Violation
        // MySQL: 1062; Postgres: 23505; SQLite: 2067 / 19
        if ($vendorCode === 1062 || $vendorCode === 23505 || $vendorCode === 2067 || $vendorCode === 19 || stripos($message, 'Duplicate entry') !== false || stripos($message, 'UNIQUE constraint') !== false) {
            throw new UniqueConstraintException("Unique constraint violation: Duplicate entry found.", 1062, $e);
        }

        throw new DatabaseException("Database Error: " . $message, (int)$e->getCode(), $e);
    }
}
