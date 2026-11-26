<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function transfer_payment_details(): array
{
    return [
        'bank' => trim((string) (getenv('OMNISTOCK_TRANSFER_BANK') ?: '')),
        'clabe' => trim((string) (getenv('OMNISTOCK_TRANSFER_CLABE') ?: '')),
        'holder' => trim((string) (getenv('OMNISTOCK_TRANSFER_HOLDER') ?: '')),
        'concept' => trim((string) (getenv('OMNISTOCK_TRANSFER_CONCEPT') ?: '')),
        'reference' => trim((string) (getenv('OMNISTOCK_TRANSFER_REFERENCE') ?: '')),
        'authorization' => trim((string) (getenv('OMNISTOCK_TRANSFER_AUTHORIZATION') ?: '')),
    ];
}

function transfer_clabe_is_valid(string $clabe): bool
{
    return preg_match('/^\d{18}$/', $clabe) === 1;
}
