<?php
declare(strict_types=1);

namespace App\Command;

use DateTimeImmutable;
use App\DataProvider\ExchangeDataProvider;
use App\Enum\UserType;
use App\Enum\OperationType;
use App\Enum\Currency;
use App\ValueObject\Operation;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;


class CalculateCommisionFeeCommand extends Command
{
    protected static $defaultName = 'calculate:commission-fee';

    private $exchangeDataProvider;

    public function __construct(ExchangeDataProvider $exchangeDataProvider)
    {
        $this->exchangeDataProvider = $exchangeDataProvider;

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

        $commissionFees = [];
        $operationLimits = [];
        
        // process the input data
        foreach ($operations as $key => $op) {
            if ($op->getOperationType() === OperationType::DEPOSIT) {
                $commissionFees[$key] = $op->getOperationAmount() * 0.0003;
            } else {
                if ($op->getUserType() === UserType::BUSINESS) {
                    $commissionFees[$key] = $op->getOperationAmount() * 0.005;
                } else {
                    $weekNo = $op->getOperationDate()->format('o-W');
                    if (empty($operationLimits[$op->getUserId()][$weekNo])) {
                        $operationLimits[$op->getUserId()][$weekNo] = [
                            'amount' => 1000,
                            'opsRemaining' => 3,
                        ];
                    }
                    if ($operationLimits[$op->getUserId()][$weekNo]['opsRemaining'] > 0 && $operationLimits[$op->getUserId()][$weekNo]['amount'] > 0) {
                        $operationLimits[$op->getUserId()][$weekNo]['opsRemaining']--;
                        if ($op->getOperationCurrency() !== Currency::EUR) {
                            try {
                                $exchangeRates = $this->exchangeDataProvider->fetchExchangeRates($op->getOperationDate()->format('Y-m-d'), [$op->getOperationCurrency()->value]);
                                $output->writeln(print_r($exchangeRates, true));
                                $rate = $exchangeRates['rates'][$op->getOperationCurrency()->value];
                                $amount = $op->getOperationAmount() / $rate;
                                //delay required to avoid hitting the API rate limit
                                usleep(1000000);
                            } catch (\Exception $e) {
                                $output->writeln('<error>ERROR:</error>Error fetching exchange rates - ' . $e->getMessage());
                                return Command::FAILURE;
                            }
                        } else {
                            $amount = $op->getOperationAmount();
                            $rate = 1;
                        }
                        if ($amount > $operationLimits[$op->getUserId()][$weekNo]['amount']) {
                            $commissionFees[$key] = ($amount - $operationLimits[$op->getUserId()][$weekNo]['amount']) * $rate * 0.003;
                            $operationLimits[$op->getUserId()][$weekNo]['amount'] = 0;
                        } else {
                            $commissionFees[$key] = 0;
                            $operationLimits[$op->getUserId()][$weekNo]['amount'] -= $op->getOperationAmount();
                        }
                    } else {
                        $commissionFees[$key] = $op->getOperationAmount() * 0.003;
                    }
                }
            }
        }

        $output->writeln(print_r($commissionFees, true));
        return Command::SUCCESS;
    }
}