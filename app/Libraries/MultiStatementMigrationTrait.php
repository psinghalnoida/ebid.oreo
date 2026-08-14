<?php

namespace App\Libraries;

/**
 * MySQL migration support: CodeIgniter's MySQLi driver executes queries via
 * plain mysqli::query(), which -- unlike the Postgres driver's pg_query()
 * (used throughout this codebase's original migrations) -- does NOT support
 * multiple ';'-separated statements in a single call. Every migration in
 * this project was originally written as one $this->db->query(<<<SQL ... SQL)
 * heredoc containing several statements (CREATE TABLE + CREATE INDEX, etc.),
 * which worked fine against Postgres but throws a syntax error against
 * MySQL. execMulti() splits the heredoc on statement-terminating semicolons
 * and runs each one as its own $this->db->query() call, so the migration
 * body itself doesn't need to change shape.
 *
 * Verified empirically against a real local MySQL 8 server: a single
 * mysqli::query() call with two ';'-separated CREATE TABLE statements fails
 * with a syntax error; CI4's MySQLi\Connection::execute() calls
 * $this->connID->query(), not multi_query(), and never sets
 * MYSQLI_CLIENT_MULTI_STATEMENTS on connect.
 *
 * The splitter is comment- and string-literal-aware (see splitStatements())
 * -- a naive explode(';', $sql) broke on this project's own `-- ...` design-
 * decision comments, several of which contain a literal semicolon in a
 * sentence.
 */
trait MultiStatementMigrationTrait
{
    /**
     * Split $sql on statement-terminating semicolons and run each
     * statement as its own query.
     */
    protected function execMulti(string $sql): void
    {
        foreach ($this->splitStatements($sql) as $statement) {
            $this->db->query($statement);
        }
    }

    /**
     * A plain explode(';', $sql) is NOT safe here: this project's migration
     * comments (`-- ...` line comments explaining a design decision) are
     * full sentences that sometimes contain a literal semicolon, which a
     * naive split would treat as a statement terminator and truncate mid-
     * comment -- producing invalid SQL. This scans character-by-character
     * and only splits on a ';' that is outside a '--' line comment and
     * outside a single-quoted string literal (the only two contexts in
     * this schema's DDL where a ';' can legitimately appear without ending
     * a statement).
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inComment  = false;
        $inString   = false;
        $length     = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inComment) {
                $current .= $char;
                if ($char === "\n") {
                    $inComment = false;
                }
                continue;
            }

            if ($inString) {
                $current .= $char;
                if ($char === "'") {
                    // MySQL/standard SQL escapes a quote inside a string by
                    // doubling it (''); a doubled quote does not end the
                    // string.
                    if (($sql[$i + 1] ?? '') === "'") {
                        $current .= "'";
                        $i++;
                    } else {
                        $inString = false;
                    }
                }
                continue;
            }

            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $inComment = true;
                $current  .= $char;
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed . ';';
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed . ';';
        }

        return $statements;
    }
}
