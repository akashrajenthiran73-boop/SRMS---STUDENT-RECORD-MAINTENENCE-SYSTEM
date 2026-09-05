<?php
namespace SRMS\Database;

use Exception;

class ForeignKeyConstraintException extends DatabaseException {
    protected ?string $table;
    protected ?string $constraint;

    public function __construct(string $message = "Foreign key constraint violation", int $code = 1452, ?Exception $previous = null, ?string $table = null, ?string $constraint = null) {
        parent::__construct($message, $code, $previous);
        $this->table = $table;
        $this->constraint = $constraint;
    }

    public function getTable(): ?string {
        return $this->table;
    }

    public function getConstraint(): ?string {
        return $this->constraint;
    }
}
