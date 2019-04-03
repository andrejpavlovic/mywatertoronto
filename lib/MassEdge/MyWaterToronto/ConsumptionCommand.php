<?php

namespace MassEdge\MyWaterToronto;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;

use Symfony\Component\Config\Definition\Processor;

use GuzzleHttp\Client;

class ConsumptionCommand extends Command
{
    protected static $defaultName = 'consumption';

    protected function configure()
    {
        $this
            ->setDescription('Fetch water consumption info.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Configuration file. (@see config.sample.json)', 'config.json')
            ->addOption('start_date', null, InputOption::VALUE_OPTIONAL, 'Start date of dataset.', (new \DateTime())->modify('-1 month')->format('Y-m-d'))
            ->addOption('end_date', null, InputOption::VALUE_OPTIONAL, 'End date of dataset. [default: up to 1 month from start_date]')
            ->addOption('threshold', null, InputOption::VALUE_OPTIONAL, 'Return only days that exceed given threshold.', 0.45)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $input->getOption('config');
        $consumptionThreshold = $input->getOption('threshold');

        $startDate = new \DateTime($input->getOption('start_date'));

        $endDateStr = $input->getOption('end_date');

        if (null !== $endDateStr) {
            $endDate = new \DateTime($endDateStr);
        } else {
            $endDate = (clone $startDate)->modify('+1 month')->setTime(23, 59, 59);
            $today = (new \DateTime())->setTime(23, 59, 59);
            if ($endDate > $today) $endDate = $today;
        }

        // get config file contents
        $content = @\file_get_contents($configFile);
        if (false === $content) {
            throw new InvalidArgumentException(sprintf('File not found: %s', $configFile));
        }

        // parse config file
        $rawConfig = \json_decode($content, true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf('Failed to parse configuration. (%s)', \json_last_error_msg()));
        }
        
        // validate config file
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration(
            $configuration,
            [$rawConfig]
        );
        
        // configure http client
        $client = new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36',
            ],
        ]);
        
        // login
        $accountNumber = "{$config['account_number']}-{$config['client_number']}";
        $response = $client->request('POST', 'https://secure.toronto.ca/cc_api/svcaccount_v1/WaterAccount/validate', [
            'json' => [
                'API_OP' => 'VALIDATE',
                'ACCOUNT_NUMBER' => $accountNumber,
                'LAST_NAME' => $config['last_name'],
                'POSTAL_CODE' => $config['postal_code'],
                'LAST_PAYMENT_METHOD' => $config['most_recent_method_payment'],
            ],
        ]);
        
        $responseData = json_decode($response->getBody(), true);
        if (empty($responseData['validateResponse']['status']) || $responseData['validateResponse']['status'] !== 'SUCCESS') {
            throw new \RuntimeException(sprintf('Failed to login. (%s)', print_r($responseData, true)));
        }

        $refToken = $responseData['validateResponse']['refToken'];

        // consumption data
        $response = $client->request('GET', 'https://secure.toronto.ca/cc_api/svcaccount_v1/WaterAccount/consumption', [
            'query' => [
                'refToken' => $refToken,
                'json' => \json_encode([
                    'API_OP' => 'CONSUMPTION',
                    'ACCOUNT_NUMBER' => $accountNumber,
                    'MIU_ID' => '2767715-1',
                    'START_DATE' => $startDate->format('Y-m-d'),
                    'END_DATE' => $endDate->format('Y-m-d'),
                    'INTERVAL_TYPE' => 'Day',
                ]),
            ],
        ]);
        $responseData = json_decode($response->getBody(), true);

        if (empty($responseData['resultCode']) || $responseData['resultCode'] != 200) {
            throw new \RuntimeException(sprintf('Failed to fetch data. (response: %s)', print_r($responseData, true)));
        }

        // print_r($responseData);

        // check if any interval usage is over limit
        foreach($responseData['meterList'] as $meter) {
            $intervals = [];
            $thresholdExceeded = false;

            foreach($meter['intervalList'] as $interval) {
                if (!empty($interval['intConsumptionTotal']) && $interval['intConsumptionTotal'] > $consumptionThreshold) {
                    $interval['threshold_exceeded'] = true;
                    $thresholdExceeded = true;
                }

                $intervals[] = $interval;
            }

            if ($thresholdExceeded) {
                $output->writeln(sprintf('<error>Above normal consumption detected. (threshold: %s)</error>', $consumptionThreshold));
                $output->writeln('');

                foreach($intervals as $interval) {
                    $output->writeln(
                        sprintf('%s %s%s',
                            $interval['intStartDate'],
                            !empty($interval['intConsumptionTotal']) ? $interval['intConsumptionTotal'] : '?',
                            !empty($interval['threshold_exceeded']) ? ' <-- EXCEEDED' : ''
                        )
                    );
                }
            }
        }
    }
}
