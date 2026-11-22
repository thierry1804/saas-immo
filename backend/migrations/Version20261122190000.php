<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma V1 : étend tranche-1 (anomalies, location, factures, paiements, journal, reversements).';
    }

    public function up(Schema $schema): void
    {
        $root = dirname(__DIR__, 2);
        if (!$this->tableExists('organization')) {
            $this->runFile($root.'/docs/l00/schema/tranche-1.sql');
            $this->runFile($root.'/docs/l00/schema/rls-tranche-1.sql');
        }
        $this->runFile(__DIR__.'/sql/v1-extend.sql');
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'async_operation',
            'document_sequence',
            'notification',
            'commission',
            'payout_batch',
            'beneficiary',
            'ledger_entry',
            'payment',
            'payment_attempt',
            'credit_note',
            'invoice',
            'rent_schedule',
            'lease',
            'candidature',
            'viewing_request',
            'listing',
            'work_order',
            'quote',
            'anomaly',
            'sync_action_receipt',
            'auth_throttle',
            'auth_challenge',
        ];
        foreach ($tables as $table) {
            $this->addSql(sprintf('DROP TABLE IF EXISTS %s CASCADE', $table));
        }
        $this->addSql('ALTER TABLE visit_fact DROP COLUMN IF EXISTS payload_hash');
        $this->addSql('ALTER TABLE account DROP COLUMN IF EXISTS failed_attempts');
        $this->addSql('ALTER TABLE account DROP COLUMN IF EXISTS locked_until');
    }

    private function tableExists(string $name): bool
    {
        $found = $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :name",
            ['name' => $name],
        );

        return $found !== false && $found !== null;
    }

    private function runFile(string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('SQL introuvable : '.$path);
        }
        foreach ($this->split($sql) as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function split(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $length = strlen($sql);
        $i = 0;
        $dollar = null;
        while ($i < $length) {
            if ($dollar !== null) {
                if (substr($sql, $i, strlen($dollar)) === $dollar) {
                    $buffer .= $dollar;
                    $i += strlen($dollar);
                    $dollar = null;
                    continue;
                }
                $buffer .= $sql[$i];
                ++$i;
                continue;
            }
            if ($sql[$i] === '$' && preg_match('/^\$[A-Za-z0-9_]*\$/', substr($sql, $i), $match) === 1) {
                $dollar = $match[0];
                $buffer .= $dollar;
                $i += strlen($dollar);
                continue;
            }
            if ($sql[$i] === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            if ($sql[$i] === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $parts[] = $statement;
                }
                $buffer = '';
                ++$i;
                continue;
            }
            $buffer .= $sql[$i];
            ++$i;
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $parts[] = $statement;
        }

        return $parts;
    }
}
