<?php


namespace App\Commands;


use App\Command;
use Swoole\Client;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CronListCommand extends Command
{
    protected function configure()
    {
        $this->setName('cron/list')
            ->setAliases([
                'cron'
            ])
            ->setDescription('List all crons');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
        if (!$client->connect('127.0.0.1', 9088)) {
            $output->writeln('<error>Connect to task scheduler server failed</error>');
            return 1;
        }
        if (!$client->send(json_encode(['action' => 'cron/list']))) {
            $output->writeln('<error>Send command to task scheduler server failed</error>');
            return 1;
        }
        $data = $client->recv();
        $array = json_decode($data, true);
        $client->close();
        unset($client);

        $table = new Table($output);
        $table->setHeaders([
            'Cron', 'Command', 'Next Run At'
        ]);
        foreach ($array as $index => $cron) {
            $table->setRow($index, [
                $cron['cron'],
                $cron['command'],
                date('Y-m-d H:i:s', $cron['nextRunAt']),
            ]);
        }
        $table->render();
    }


}
