<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Identifiants stables du jeu synthétique ORG-A / ORG-B.
 * Les garder alignés avec backend/README.md.
 */
final class DemoCatalog
{
    public const ORG_A = '11111111-1111-4111-8111-111111111111';
    public const ORG_B = '22222222-2222-4222-8222-222222222222';

    public const G1 = '11111111-1111-4111-8111-aaaaaaaaaaa1';
    public const G2 = '11111111-1111-4111-8111-aaaaaaaaaaa2';
    public const G3 = '11111111-1111-4111-8111-aaaaaaaaaaa3';
    public const A1 = '11111111-1111-4111-8111-aaaaaaaaaa11';
    public const A2 = '11111111-1111-4111-8111-aaaaaaaaaa12';
    public const P1 = '11111111-1111-4111-8111-aaaaaaaaaa21';
    public const P2 = '11111111-1111-4111-8111-aaaaaaaaaa22';
    public const R1 = '11111111-1111-4111-8111-aaaaaaaaaa31';
    public const R2 = '11111111-1111-4111-8111-aaaaaaaaaa32';
    public const F1 = '11111111-1111-4111-8111-aaaaaaaaaa41';
    public const F2 = '11111111-1111-4111-8111-aaaaaaaaaa42';
    public const ADMIN = '11111111-1111-4111-8111-aaaaaaaaaa99';
    public const G1B = '22222222-2222-4222-8222-aaaaaaaaaaa1';

    public const OWNER_P1 = '11111111-1111-4111-8111-bbbbbbbbbb01';
    public const OWNER_P2 = '11111111-1111-4111-8111-bbbbbbbbbb02';
    public const OWNER_P1_B = '22222222-2222-4222-8222-bbbbbbbbbb01';

    public const H1 = '11111111-1111-4111-8111-cccccccccc01';
    public const H2 = '11111111-1111-4111-8111-cccccccccc02';
    public const H3 = '11111111-1111-4111-8111-cccccccccc03';
    public const HB = '22222222-2222-4222-8222-cccccccccc01';

    public const CHECKLIST = '11111111-1111-4111-8111-dddddddddd01';
    public const CHECKLIST_V1 = '11111111-1111-4111-8111-dddddddddd11';
    public const CHECKLIST_V2 = '11111111-1111-4111-8111-dddddddddd12';

    public const TARIFF_H1 = '11111111-1111-4111-8111-eeeeeeeeee01';
    public const TARIFF_H2 = '11111111-1111-4111-8111-eeeeeeeeee02';
    public const TARIFF_H3 = '11111111-1111-4111-8111-eeeeeeeeee03';

    public const MISSION = '11111111-1111-4111-8111-ffffffff0001';
    public const DEVICE_A1 = '11111111-1111-4111-8111-ffffffff0002';
    public const ASSIGN_A1 = '11111111-1111-4111-8111-ffffffff0003';

    public const LEASE_B1 = '11111111-1111-4111-8111-121212121201';
    public const LEASE_B2 = '11111111-1111-4111-8111-121212121202';

    public const BENEFICIARY_P1 = '11111111-1111-4111-8111-343434343401';
    public const COMMISSION_F1 = '11111111-1111-4111-8111-343434343402';

    public const PASSWORD = 'veille-dev';
    public const DEV_OTP = '000000';
    public const A1_PHONE = '+261320000001';
}
