<?php
declare(strict_types=1);

namespace App\Command;

use DateTimeImmutable;
use App\DataProvider\ExchangeDataProvider;
use App\Enum\UserType;
use App\Enum\OperationType;
use App\Enum\Currency;
use App\Features\FeeCalculator\BaseCalculator;
use App\Features\FeeCalculator\DepositFeeCalculator;
use App\Features\FeeCalculator\WithdrawalBusinessCalculator;
use App\Features\FeeCalculator\WithdrawalPrivateCalculator;
use App\ValueObject\Operation;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(name: 'calculate:commission-fee')]
class CalculateCommisionFeeCommand extends Command
{
    public function __construct(private ExchangeDataProvider $exchangeDataProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Calculates the commission fee.')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'The path to the input file.');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputFile = $input->getArgument('inputFile');
        $operations = [];

        // validate and read the input file
        if (!file_exists($inputFile) || !is_readable($inputFile)) {
            $output->writeln('<error>ERROR:</error> Input file does not exist or is not readable.');
            return Command::FAILURE;
        }

        try {
            $csv = Reader::createFromPath($inputFile, 'r');
            $records = $csv->getRecords();

            foreach ($records as $record) {
                $operations[] = new Operation(
                    new DateTimeImmutable($record[0]),
                    (int)$record[1],
                    UserType::from($record[2]),
                    OperationType::from($record[3]),
                    (float)$record[4],
                    Currency::from($record[5])
                );
            }
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR:</error>Error reading the CSV file - ' . $e->getMessage());
            return Command::FAILURE;
        }

        $commissionFee = 0;
        $operationLimits = [];
        
        // process the input data
        foreach ($operations as $key => $op) {
            $amount = $op->getOperationAmount();
            $currency = $op->getOperationCurrency();
        
            if ($op->getOperationCurrency() !== Currency::JPY) {
                $amount *= 100;
            }
            if ($op->getOperationType() === OperationType::DEPOSIT) {
                $calc = new DepositFeeCalculator();
                $commissionFee = $calc->calculate($amount, $currency, $op->getUserType());
            } else {
                if ($op->getUserType() === UserType::BUSINESS) {
                    $calc = new WithdrawalBusinessCalculator();
                    $commissionFee = $calc->calculate($amount, $currency, $op->getUserType());
                } else {
                    $calc = new WithdrawalPrivateCalculator();
                    $weekNo = $op->getOperationDate()->format('o-W');
                    if (empty($operationLimits[$op->getUserId()][$weekNo])) {
                        $operationLimits[$op->getUserId()][$weekNo] = [
                            'amount' => 100000,
                            'opsRemaining' => 3,
                        ];
                    }
                    if ($operationLimits[$op->getUserId()][$weekNo]['opsRemaining'] > 0 && $operationLimits[$op->getUserId()][$weekNo]['amount'] > 0) {
                        $operationLimits[$op->getUserId()][$weekNo]['opsRemaining']--;
                        if ($op->getOperationCurrency() !== Currency::EUR) {
                            try {
                                $exchangeRates = $this->exchangeDataProvider->fetchExchangeRates($op->getOperationDate()->format('Y-m-d'), [$op->getOperationCurrency()->value]);
                                $rate = $exchangeRates['rates'][$op->getOperationCurrency()->value] /  ($op->getOperationCurrency() === Currency::JPY ? 100 : 1);
                                $amount = $amount / $rate;
                                //delay required to avoid hitting the API rate limit
                                usleep(1000000);
                            } catch (\Exception $e) {
                                $output->writeln('<error>ERROR:</error>Error fetching exchange rates - ' . $e->getMessage());
                                return Command::FAILURE;
                            }
                        } else {
                            $rate = 1;
                        }
                        if ($amount > $operationLimits[$op->getUserId()][$weekNo]['amount']) {
                            $amount = ($amount - $operationLimits[$op->getUserId()][$weekNo]['amount']) * $rate;
                            $operationLimits[$op->getUserId()][$weekNo]['amount'] = 0;
                        } else {
                            $operationLimits[$op->getUserId()][$weekNo]['amount'] -= $amount;
                            $amount = 0;
                        }
                    }
                }
            }
            $commissionFee = $calc->calculate($amount, $currency, $op->getUserType());
            if ($op->getOperationCurrency() !== Currency::JPY) {
                $output->write(number_format($commissionFee / 100, 2, '.', '') . PHP_EOL);
            } else {
                $output->write(number_format($commissionFee, 0, '.', '') . PHP_EOL);
            }
        }

        return Command::SUCCESS;
    }
}