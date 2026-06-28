<?php

namespace App\Enums;

enum CertificatStatut: string
{
    case Certifie = 'certifie';
    case Revoque  = 'revoque';
}
