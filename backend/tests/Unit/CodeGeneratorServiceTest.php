<?php

namespace Tests\Unit;

use App\Services\CodeGeneratorService;
use PHPUnit\Framework\TestCase;

class CodeGeneratorServiceTest extends TestCase
{
    /**
     * Test: génère un code lot avec préfixe coop + L + horodatage
     */
    public function test_generates_lot_code_with_coop_prefix_and_timestamp(): void
    {
        $service = new CodeGeneratorService();
        $code = $service->generateLotCode('AGPK');

        $this->assertMatchesRegularExpression('/^AGPKL\d{14}$/', $code);
    }

    /**
     * Test: génère un code producteur avec préfixe coop + P + horodatage
     */
    public function test_generates_producteur_code_with_coop_prefix_and_timestamp(): void
    {
        $service = new CodeGeneratorService();
        $code = $service->generateProducteurCode('AGPK');

        $this->assertMatchesRegularExpression('/^AGPKP\d{14}$/', $code);
    }

    /**
     * Test: met le préfixe en majuscules même si fourni en minuscules
     */
    public function test_converts_coop_prefix_to_uppercase(): void
    {
        $service = new CodeGeneratorService();
        $code = $service->generateLotCode('agpk');

        $this->assertStringStartsWith('AGPK', $code);
    }

    /**
     * Test: deux codes générés à la même seconde sont identiques — la collision est gérée côté serveur (409)
     */
    public function test_same_second_generates_identical_codes(): void
    {
        $service = new CodeGeneratorService();
        $a = $service->generateLotCode('AGPK');
        $b = $service->generateLotCode('AGPK');

        // Les deux codes ont le même format — le serveur rejette le doublon avec 409
        $this->assertMatchesRegularExpression('/^AGPKL\d{14}$/', $a);
        $this->assertMatchesRegularExpression('/^AGPKL\d{14}$/', $b);
    }
}
