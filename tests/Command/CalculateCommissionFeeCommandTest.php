<?php
declare(strict_types=1);

namespace Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Command\CalculateCommissionFeeCommand;
use App\DataProvider\ExchangeDataProvider;


class CalculateCommissionFeeCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private const EXPECTED_OUTPUT_PATH = 'tests/result.txt';

    protected function setUp(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        
        $container = static::getContainer();
        $provider = $this->createMock(ExchangeDataProvider::class);
        $provider->method('fetchExchangeRates')
            ->willReturn([
                'rates' => [
                    'USD' => 1.1497,
                    'JPY' => 129.53,
                ],
            ]);
        $container->set('App\DataProvider\ExchangeDataProvider', $provider);

        $this->commandTester = new CommandTester($application->find('calculate:commission-fee'));
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([
            'inputFile' => 'tests/input.csv'
        ]);

        $output = $this->commandTester->getDisplay();
        $output = str_replace("\n", "\n", $output);
        $expectedOutput = file_get_contents(self::EXPECTED_OUTPUT_PATH);
        $this->assertStringContainsString($expectedOutput, $output);

    }
}