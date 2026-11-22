<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\DemoCatalog;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\Membership;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:demo', description: 'Jeu synthétique idempotent ORG-A / ORG-B')]
final class SeedDemoCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = new Database($this->connection);
        $password = password_hash(DemoCatalog::PASSWORD, PASSWORD_DEFAULT);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Indian/Antananarivo')));
        $start = $now->setTime(9, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $end = $now->setTime(11, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP');

        $this->connection->transactional(function () use ($db, $password, $start, $end): void {
            $this->organization($db, DemoCatalog::ORG_A, 'ORG-A');
            $this->organization($db, DemoCatalog::ORG_B, 'ORG-B');
            $this->account($db, DemoCatalog::G1, 'G1 Préparateur', 'g1@org-a.test', null, $password);
            $this->account($db, DemoCatalog::G2, 'G2 Approbateur', 'g2@org-a.test', null, $password);
            $this->account($db, DemoCatalog::G3, 'G3 Opérations', 'g3@org-a.test', null, $password);
            $this->account($db, DemoCatalog::A1, 'A1 Agent', null, DemoCatalog::A1_PHONE, null);
            $this->account($db, DemoCatalog::A2, 'A2 Agent', null, '+261320000002', null);
            $this->account($db, DemoCatalog::P1, 'P1 Propriétaire', 'p1@org-a.test', null, $password);
            $this->account($db, DemoCatalog::P2, 'P2 Propriétaire', 'p2@org-a.test', null, $password);
            $this->account($db, DemoCatalog::R1, 'R1 Locataire', 'r1@org-a.test', null, $password);
            $this->account($db, DemoCatalog::R2, 'R2 Locataire', 'r2@org-a.test', null, $password);
            $this->account($db, DemoCatalog::F1, 'F1 Apporteur', null, '+261330000001', null);
            $this->account($db, DemoCatalog::F2, 'F2 Apporteur', null, '+261340000001', null);
            $this->account($db, DemoCatalog::ADMIN, 'Admin plateforme', 'admin@haythi.test', null, $password);
            $this->account($db, DemoCatalog::G1B, 'G1 ORG-B', 'g1@org-b.test', null, $password);

            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::G1, ['manager'], ['operations', 'prepare_payout', 'finance_prepare']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::G2, ['manager'], ['operations', 'approve_payout', 'finance_approve']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::G3, ['manager'], ['operations']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::A1, ['agent'], ['fieldwork']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::A2, ['agent'], ['fieldwork']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::P1, ['owner'], ['owner_read']);
            $this->membership($db, DemoCatalog::ORG_B, DemoCatalog::P1, ['owner'], ['owner_read']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::P2, ['owner'], ['owner_read']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::R1, ['renter'], ['renter_read']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::R2, ['renter'], ['renter_read']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::F1, ['referrer'], ['referrer_read']);
            $this->membership($db, DemoCatalog::ORG_A, DemoCatalog::F2, ['referrer'], ['referrer_read']);
            $this->membership($db, DemoCatalog::ORG_B, DemoCatalog::G1B, ['manager'], ['operations', 'prepare_payout', 'finance_prepare']);

            $this->owner($db, DemoCatalog::ORG_A, DemoCatalog::OWNER_P1, DemoCatalog::P1, 'P1');
            $this->owner($db, DemoCatalog::ORG_A, DemoCatalog::OWNER_P2, DemoCatalog::P2, 'P2');
            $this->owner($db, DemoCatalog::ORG_B, DemoCatalog::OWNER_P1_B, DemoCatalog::P1, 'P1');

            $this->property($db, DemoCatalog::ORG_A, DemoCatalog::H1, DemoCatalog::OWNER_P1, '12 rue de la Recette, logement H1', 'Démo', 'H1');
            $this->property($db, DemoCatalog::ORG_A, DemoCatalog::H2, DemoCatalog::OWNER_P1, 'Quartier démo, logement H2', 'Démo', 'H2');
            $this->property($db, DemoCatalog::ORG_A, DemoCatalog::H3, DemoCatalog::OWNER_P2, 'Lotissement démo, logement H3', 'Démo', 'H3');
            $this->property($db, DemoCatalog::ORG_B, DemoCatalog::HB, DemoCatalog::OWNER_P1_B, 'Logement test ORG-B', 'Démo B', 'HB');

            $this->ownership($db, DemoCatalog::ORG_A, '11111111-1111-4111-8111-abababab0001', DemoCatalog::H1, DemoCatalog::OWNER_P1);
            $this->ownership($db, DemoCatalog::ORG_A, '11111111-1111-4111-8111-abababab0002', DemoCatalog::H2, DemoCatalog::OWNER_P1);
            $this->ownership($db, DemoCatalog::ORG_A, '11111111-1111-4111-8111-abababab0003', DemoCatalog::H3, DemoCatalog::OWNER_P2);
            $this->ownership($db, DemoCatalog::ORG_B, '22222222-2222-4222-8222-abababab0001', DemoCatalog::HB, DemoCatalog::OWNER_P1_B);

            $db->execute(
                'INSERT INTO checklist_template (id, organization_id, name, type, status)
                 VALUES (:id, :org, :name, \'visit\', \'active\')
                 ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, status = \'active\', updated_at = now()',
                ['id' => DemoCatalog::CHECKLIST, 'org' => DemoCatalog::ORG_A, 'name' => 'Checklist standard'],
            );
            $this->checklistVersion($db, DemoCatalog::CHECKLIST_V1, 1, 'Photographier le seuil.');
            $this->checklistVersion($db, DemoCatalog::CHECKLIST_V2, 2, 'Photographier le seuil, consigne v2.');

            $this->tariff($db, DemoCatalog::TARIFF_H1, DemoCatalog::H1, '2000', 'EUR');
            $this->tariff($db, DemoCatalog::TARIFF_H2, DemoCatalog::H2, '50000', 'MGA');
            $this->tariff($db, DemoCatalog::TARIFF_H3, DemoCatalog::H3, '10000', 'MGA');

            $db->execute(
                'INSERT INTO device (id, organization_id, account_id, device_identifier)
                 VALUES (:id, :org, :account, :identifier)
                 ON CONFLICT (organization_id, device_identifier)
                 DO UPDATE SET account_id = EXCLUDED.account_id, revoked_at = NULL, updated_at = now()',
                [
                    'id' => DemoCatalog::DEVICE_A1,
                    'org' => DemoCatalog::ORG_A,
                    'account' => DemoCatalog::A1,
                    'identifier' => 'demo-android-a1',
                ],
            );

            $tariff = json_encode(['type' => 'visit', 'amount_minor' => '50000', 'currency' => 'MGA'], JSON_THROW_ON_ERROR);
            $instructions = json_encode(['note' => 'Visite de démonstration'], JSON_THROW_ON_ERROR);
            $db->execute(
                'INSERT INTO mission (
                    id, organization_id, property_id, type, scheduled_start, scheduled_end, status,
                    assignment_revision, checklist_version_id, tariff_snapshot, instructions_snapshot
                 ) VALUES (
                    :id, :org, :property, \'visit\', :start, :end, \'assigned\',
                    1, :checklist, CAST(:tariff AS jsonb), CAST(:instructions AS jsonb)
                 )
                 ON CONFLICT (id) DO UPDATE SET scheduled_start = EXCLUDED.scheduled_start, scheduled_end = EXCLUDED.scheduled_end, updated_at = now()',
                [
                    'id' => DemoCatalog::MISSION,
                    'org' => DemoCatalog::ORG_A,
                    'property' => DemoCatalog::H2,
                    'start' => $start,
                    'end' => $end,
                    'checklist' => DemoCatalog::CHECKLIST_V1,
                    'tariff' => $tariff,
                    'instructions' => $instructions,
                ],
            );
            $db->execute(
                'INSERT INTO assignment_history (id, organization_id, mission_id, agent_id, revision, valid_from, reason)
                 VALUES (:id, :org, :mission, :agent, 1, :start, :reason)
                 ON CONFLICT (organization_id, mission_id, revision) DO NOTHING',
                [
                    'id' => DemoCatalog::ASSIGN_A1,
                    'org' => DemoCatalog::ORG_A,
                    'mission' => DemoCatalog::MISSION,
                    'agent' => DemoCatalog::A1,
                    'start' => $start,
                    'reason' => 'Affectation initiale de démonstration',
                ],
            );

            $this->lease($db, DemoCatalog::LEASE_B1, DemoCatalog::H2, 'B1', '1000000', '100000', '500000', 1000, '2026-11-01', null, DemoCatalog::R1, 'P1', ['label' => 'Bail de démonstration H2']);
            $this->lease($db, DemoCatalog::LEASE_B2, DemoCatalog::H3, 'B2', '310000', '0', '0', 0, '2026-10-16', 5, DemoCatalog::R2, 'P2', [
                'prorata_first_minor' => '160000',
                'day_count' => '16/31',
            ]);
            $this->rent($db, '11111111-1111-4111-8111-565656560101', DemoCatalog::LEASE_B1, '2026-11-01', '2026-12-01', '1000000', '100000');
            $this->rent($db, '11111111-1111-4111-8111-565656560102', DemoCatalog::LEASE_B1, '2026-12-01', '2027-01-01', '1000000', '100000');
            $this->rent($db, '11111111-1111-4111-8111-565656560103', DemoCatalog::LEASE_B1, '2027-01-01', '2027-02-01', '1000000', '100000');
            $this->rent($db, '11111111-1111-4111-8111-565656560201', DemoCatalog::LEASE_B2, '2026-10-16', '2026-11-05', '160000', '0');
            $this->rent($db, '11111111-1111-4111-8111-565656560202', DemoCatalog::LEASE_B2, '2026-11-05', '2026-12-05', '310000', '0');

            $db->execute(
                'INSERT INTO beneficiary (
                    id, organization_id, owner_id, label, currency, coordinates, status, approved_by, approved_at
                 ) VALUES (
                    :id, :org, :owner, :label, \'MGA\', CAST(:coords AS jsonb), \'approved\', :approver, now()
                 )
                 ON CONFLICT (id) DO UPDATE SET status = \'approved\', approved_by = EXCLUDED.approved_by, currency = \'MGA\'',
                [
                    'id' => DemoCatalog::BENEFICIARY_P1,
                    'org' => DemoCatalog::ORG_A,
                    'owner' => DemoCatalog::OWNER_P1,
                    'label' => 'Bénéficiaire P1',
                    'coords' => json_encode(['channel' => 'simulated', 'label' => 'Compte test'], JSON_THROW_ON_ERROR),
                    'approver' => DemoCatalog::G2,
                ],
            );
            $db->execute(
                'INSERT INTO commission (
                    id, organization_id, lease_id, beneficiary_account_id, beneficiary_label,
                    currency, base_minor, amount_minor, status, earned_at
                 ) VALUES (
                    :id, :org, :lease, :account, \'F1\', \'MGA\', 1000000, 60000, \'earned\', now()
                 )
                 ON CONFLICT (id) DO NOTHING',
                [
                    'id' => DemoCatalog::COMMISSION_F1,
                    'org' => DemoCatalog::ORG_A,
                    'lease' => DemoCatalog::LEASE_B1,
                    'account' => DemoCatalog::F1,
                ],
            );
        });

        $output->writeln('Jeu de démonstration chargé (ORG-A / ORG-B).');
        $output->writeln('Mot de passe des comptes e-mail : veille-dev');
        $output->writeln('OTP de développement : 000000 uniquement si APP_ENV=dev');
        $output->writeln('ORG-A '.$this->id(DemoCatalog::ORG_A));
        $output->writeln('Mission du jour '.$this->id(DemoCatalog::MISSION).' appareil '.$this->id(DemoCatalog::DEVICE_A1));
        $output->writeln('Bail B1 '.$this->id(DemoCatalog::LEASE_B1));

        return Command::SUCCESS;
    }

    private function id(string $value): string
    {
        return $value;
    }

    private function organization(Database $db, string $id, string $name): void
    {
        $db->execute(
            'INSERT INTO organization (id, name, status, timezone)
             VALUES (:id, :name, \'active\', \'Indian/Antananarivo\')
             ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, status = \'active\', updated_at = now()',
            ['id' => $id, 'name' => $name],
        );
    }

    private function account(Database $db, string $id, string $name, ?string $email, ?string $phone, ?string $password): void
    {
        $db->execute(
            'INSERT INTO account (id, display_name, email_normalized, phone_normalized, email_verified_at, phone_verified_at, password_hash, status)
             VALUES (
                :id, :name,
                NULLIF(:email, \'\'),
                NULLIF(:phone, \'\'),
                NULLIF(:email_verified, \'\')::timestamptz,
                NULLIF(:phone_verified, \'\')::timestamptz,
                NULLIF(:password, \'\'),
                \'active\'
             )
             ON CONFLICT (id) DO UPDATE SET
                display_name = EXCLUDED.display_name,
                email_normalized = EXCLUDED.email_normalized,
                phone_normalized = EXCLUDED.phone_normalized,
                password_hash = EXCLUDED.password_hash,
                status = \'active\',
                updated_at = now()',
            [
                'id' => $id,
                'name' => $name,
                'email' => $email ?? '',
                'phone' => $phone ?? '',
                'email_verified' => $email === null ? '' : 'now',
                'phone_verified' => $phone === null ? '' : 'now',
                'password' => $password ?? '',
            ],
        );
    }

    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    private function membership(Database $db, string $org, string $account, array $roles, array $permissions): void
    {
        $db->execute(
            'INSERT INTO membership (id, organization_id, account_id, roles, permissions, status)
             VALUES (:id, :org, :account, CAST(:roles AS text[]), CAST(:perms AS text[]), \'active\')
             ON CONFLICT (organization_id, account_id) DO UPDATE
             SET roles = EXCLUDED.roles, permissions = EXCLUDED.permissions, status = \'active\', updated_at = now()',
            [
                'id' => $this->membershipId($org, $account),
                'org' => $org,
                'account' => $account,
                'roles' => Membership::toPgArray($roles),
                'perms' => Membership::toPgArray($permissions),
            ],
        );
    }

    private function membershipId(string $org, string $account): string
    {
        $raw = substr(hash('sha256', $org.'|'.$account), 0, 32);
        $hex = substr($raw, 0, 12).'4'.substr($raw, 13, 3).'8'.substr($raw, 17, 3).substr($raw, 20, 12);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function owner(Database $db, string $org, string $id, string $account, string $ref): void
    {
        $db->execute(
            'INSERT INTO owner (id, organization_id, account_id, identity)
             VALUES (:id, :org, :account, CAST(:identity AS jsonb))
             ON CONFLICT (id) DO UPDATE SET account_id = EXCLUDED.account_id, updated_at = now()',
            [
                'id' => $id,
                'org' => $org,
                'account' => $account,
                'identity' => json_encode(['ref' => $ref], JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function property(Database $db, string $org, string $id, string $owner, string $address, string $neighborhood, string $code): void
    {
        $db->execute(
            'INSERT INTO property (id, organization_id, current_owner_id, address, neighborhood, timezone, status, characteristics)
             VALUES (:id, :org, :owner, :address, :neighborhood, \'Indian/Antananarivo\', \'active\', CAST(:chars AS jsonb))
             ON CONFLICT (id) DO UPDATE SET address = EXCLUDED.address, current_owner_id = EXCLUDED.current_owner_id, updated_at = now()',
            [
                'id' => $id,
                'org' => $org,
                'owner' => $owner,
                'address' => $address,
                'neighborhood' => $neighborhood,
                'chars' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function ownership(Database $db, string $org, string $id, string $property, string $owner): void
    {
        $db->execute(
            'INSERT INTO property_ownership (id, organization_id, property_id, owner_id, valid_from, reason)
             VALUES (:id, :org, :property, :owner, DATE \'2026-01-01\', \'Jeu de démonstration\')
             ON CONFLICT (id) DO NOTHING',
            ['id' => $id, 'org' => $org, 'property' => $property, 'owner' => $owner],
        );
    }

    private function checklistVersion(Database $db, string $id, int $number, string $entryInstruction): void
    {
        $db->execute(
            'INSERT INTO checklist_version (id, organization_id, template_id, number, published_at, min_photos, status)
             VALUES (:id, :org, :template, :number, now(), 6, \'published\')
             ON CONFLICT (id) DO UPDATE SET min_photos = 6, status = \'published\', published_at = COALESCE(checklist_version.published_at, now())',
            [
                'id' => $id,
                'org' => DemoCatalog::ORG_A,
                'template' => DemoCatalog::CHECKLIST,
                'number' => $number,
            ],
        );
        $items = [
            ['entree', 'Entrée', $entryInstruction, ['photo_required' => true, 'not_applicable' => 'forbidden']],
            ['sejour', 'Séjour', 'Contrôler la pièce.', []],
            ['cuisine', 'Cuisine', 'Contrôler la pièce.', []],
            ['salle_eau', 'Salle d’eau', 'Point obligatoire.', ['not_applicable' => 'forbidden']],
            ['chambre', 'Chambre', 'Contrôler la pièce.', []],
            ['fenetres', 'Fenêtres', 'Contrôler les ouvrants.', []],
            ['sol', 'Sol', 'Contrôler l’état.', []],
            ['electricite', 'Électricité', 'Non applicable seulement avec motif.', ['not_applicable' => 'reason_required']],
            ['compteurs', 'Compteurs', 'Relever si accessibles.', []],
            ['sortie', 'Sortie', 'Contrôler la fermeture.', []],
        ];
        $n = 1;
        foreach ($items as [$key, $label, $instruction, $constraints]) {
            $itemId = sprintf('11111111-1111-4111-8111-d1d1d1d1%02d%02d', $number, $n);
            $db->execute(
                'INSERT INTO checklist_item (id, organization_id, version_id, stable_item_key, sort_order, label, instruction, constraints)
                 VALUES (:id, :org, :version, :key, :sort, :label, :instruction, CAST(:constraints AS jsonb))
                 ON CONFLICT (organization_id, version_id, stable_item_key)
                 DO UPDATE SET label = EXCLUDED.label, instruction = EXCLUDED.instruction, constraints = EXCLUDED.constraints, sort_order = EXCLUDED.sort_order',
                [
                    'id' => $itemId,
                    'org' => DemoCatalog::ORG_A,
                    'version' => $id,
                    'key' => $key,
                    'sort' => $n,
                    'label' => $label,
                    'instruction' => $instruction,
                    'constraints' => json_encode($constraints, JSON_THROW_ON_ERROR),
                ],
            );
            ++$n;
        }
    }

    private function tariff(Database $db, string $id, string $property, string $amount, string $currency): void
    {
        $db->execute(
            'INSERT INTO tariff_version (id, organization_id, property_id, type, amount_minor, currency, valid_from)
             VALUES (:id, :org, :property, \'visit\', :amount, :currency, DATE \'2026-01-01\')
             ON CONFLICT (id) DO UPDATE SET amount_minor = EXCLUDED.amount_minor, currency = EXCLUDED.currency',
            [
                'id' => $id,
                'org' => DemoCatalog::ORG_A,
                'property' => $property,
                'amount' => $amount,
                'currency' => $currency,
            ],
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function lease(
        Database $db,
        string $id,
        string $property,
        string $label,
        string $rent,
        string $charges,
        string $deposit,
        int $feeBps,
        string $start,
        ?int $dueDay,
        string $renter,
        string $ownerRef,
        array $parameters,
    ): void {
        $db->execute(
            'INSERT INTO lease (
                id, organization_id, property_id, label, status, currency, rent_minor, charges_minor, deposit_minor,
                management_fee_bps, start_date, due_day, renter_account_id, owner_snapshot, parameters
             ) VALUES (
                :id, :org, :property, :label, \'active\', \'MGA\', :rent, :charges, :deposit,
                :fee, :start, NULLIF(:due, \'\')::integer, :renter, CAST(:owner AS jsonb), CAST(:parameters AS jsonb)
             )
             ON CONFLICT (id) DO UPDATE SET
                rent_minor = EXCLUDED.rent_minor,
                charges_minor = EXCLUDED.charges_minor,
                deposit_minor = EXCLUDED.deposit_minor,
                management_fee_bps = EXCLUDED.management_fee_bps,
                currency = \'MGA\',
                updated_at = now()',
            [
                'id' => $id,
                'org' => DemoCatalog::ORG_A,
                'property' => $property,
                'label' => $label,
                'rent' => $rent,
                'charges' => $charges,
                'deposit' => $deposit,
                'fee' => $feeBps,
                'start' => $start,
                'due' => $dueDay === null ? '' : (string) $dueDay,
                'renter' => $renter,
                'owner' => json_encode(['ref' => $ownerRef], JSON_THROW_ON_ERROR),
                'parameters' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function rent(
        Database $db,
        string $id,
        string $lease,
        string $start,
        string $end,
        string $rent,
        string $charges,
    ): void {
        $db->execute(
            'INSERT INTO rent_schedule (
                id, organization_id, lease_id, period_start, period_end, rent_minor, charges_minor, currency, status
             ) VALUES (
                :id, :org, :lease, :start, :end, :rent, :charges, \'MGA\', \'due\'
             )
             ON CONFLICT (organization_id, lease_id, period_start) DO NOTHING',
            [
                'id' => $id,
                'org' => DemoCatalog::ORG_A,
                'lease' => $lease,
                'start' => $start,
                'end' => $end,
                'rent' => $rent,
                'charges' => $charges,
            ],
        );
    }
}
