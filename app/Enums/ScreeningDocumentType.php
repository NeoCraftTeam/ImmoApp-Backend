<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScreeningDocumentType: string implements HasLabel
{
    case IdCard = 'id_card';
    case Passport = 'passport';
    case SalarySlip = 'salary_slip';
    case EmployerLetter = 'employer_letter';
    case BankStatement = 'bank_statement';
    case TaxNotice = 'tax_notice';
    case ProofOfAddress = 'proof_of_address';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdCard => 'Carte d\'identité',
            self::Passport => 'Passeport',
            self::SalarySlip => 'Bulletin de salaire',
            self::EmployerLetter => 'Attestation employeur',
            self::BankStatement => 'Relevé bancaire',
            self::TaxNotice => 'Avis d\'imposition',
            self::ProofOfAddress => 'Justificatif de domicile',
            self::Other => 'Autre',
        };
    }
}
