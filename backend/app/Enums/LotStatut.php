<?php

namespace App\Enums;

enum LotStatut: string
{
    case Enregistre = 'enregistre';
    case Certifie   = 'certifie';
    case Revoque    = 'revoque';
}
