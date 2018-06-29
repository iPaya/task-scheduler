<?php


namespace App\Commands;


use App\Base62;
use App\Command;
use App\Sdk\Api\Cron;
use App\Sdk\Api\Token;
use App\Sdk\Client;
use App\TableCache;
use Cron\CronExpression;
use Psr\SimpleCache\CacheInterface;
use Swoole\Channel;
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    /**
     * @var OutputInterface
     */
    public $output;
    /**
     * @var CacheInterface
     */
    public $cache;
    /**
     * @var Client
     */
    public $sdkClient;
    /**
     * @var Channel
     */
    public $queue;
    /**
     * @var \Swoole\Table
     */
    public $workers;
    /**
     * @var \Swoole\Table
     */
    public $tasks;

    public function onStart(Server $server)
    {
        $app = $this->getApplication();
        $stats = $server->stats();
        $table = new Table($this->output);
        $table
            ->setRows([
                ['<comment>Version</comment>', $app->getVersion()],
                ['<comment>Start Time</comment>', date('Y-m-d H:i:s', $stats['start_time'])]
            ]);
        $table->render();

        $this->output->writeln('<info>Started.</info>');
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        if ($workerId == 0) {
            // 读取远程计划任务
            $this->updateCron();

            // 定期检查是否有任务需要执行,有任务则加入到队列
            $server->tick(1000, function () use ($server) {
                $this->output->writeln('[' . date('Y-m-d H:i:s') . "] Checking cron commands", Output::VERBOSITY_DEBUG);
                $now = time();
                foreach ($this->tasks as $task) {
                    $cron = $task['cron'];
                    $command = $task['command'];

                    $cronExpression = CronExpression::factory($cron);
                    $nextRunAt = $cronExpression->getNextRunDate()->getTimestamp();
                    if ($nextRunAt == ($now + 1)) {
                        $this->log("Push new commend '{$command}' to queue");
                        $this->queue->push($command);
                    }
                }
            });

        }
    }

    public function updateCron()
    {
        $this->output->writeln('Checking cron version', Output::VERBOSITY_DEBUG);
        $api = new Cron($this->sdkClient);
        $version = $api->version();
        $localVersion = $this->cache->get('cron.version', 0);
        $this->output->writeln("Local cron version <comment>{$localVersion}</comment>", Output::VERBOSITY_DEBUG);
        $this->output->writeln("Remote cron version <comment>{$version}</comment>", Output::VERBOSITY_DEBUG);
        if ($localVersion != $version) {
            $crons = $api->lists();
            foreach ($crons as $cron) {
                $this->tasks->set($cron['id'], [
                    'cron' => $cron['cron'],
                    'command' => $cron['command']
                ]);
            }
            $this->cache->set('cron.version', $version);
            $this->output->writeln('Update cron to version <comment>#' . $version . '</comment>');
        }
    }

    public function onReceive(Server $server, int $fd, $reactorId, string $data)
    {
        $this->output->writeln("Receive: " . $data, Output::VERBOSITY_DEBUG);
        $array = json_decode($data, true);
        $output = $this->output;

        $action = $array['action'] ?? 'unknown';
        switch ($action) {
            case 'register':
                $token = $array['data']['token'] ?? null;
                if ($token == null) {
                    $server->send($fd, json_encode([
                        'action' => 'register/fail',
                        'data' => ['errorMessage' => '必须填写 token']
                    ]));
                    $server->close($fd);
                    return;
                }
                if (!$this->validateToken($token)) {
                    $server->send($fd, json_encode([
                        'action' => 'register/fail',
                        'data' => ['errorMessage' => 'Token 认证错误'],
                    ]));
                    $server->close($fd);
                    return;
                }

                $this->workers->set($fd, [
                    'fd' => $fd,
                    'name' => Base62::encodeString(uniqid()),
                    'register_time' => time(),
                ]);
                $server->send($fd, json_encode([
                    'action' => 'register/success',
                ]));
                break;
            case 'cron/list':
                $this->handleActionCronList($array['data'] ?? null, $server, $fd);
                break;
            case 'unknown':
            default:
                $output->writeln("<error>Unknown action \"{$action}\"</error>");
        }

    }

    /**
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token)
    {
        $api = new Token($this->sdkClient);
        return $api->validate($token);
    }

    /**
     * 处理动作： 下次计划任务
     *
     * @param array|null $data
     * @param Server $server
     * @param int $fd
     */
    public function handleActionCronList(array $data = null, Server $server, int $fd)
    {
        $crons = [];
        foreach ($this->tasks as $task) {
            $cron = $task['cron'];
            $command = $task['command'];

            $cronExpression = CronExpression::factory($cron);
            $crons[] = [
                'cron' => $cron,
                'command' => $command,
                'nextRunAt' => $cronExpression->getNextRunDate()->getTimestamp(),
            ];
        }
        $server->send($fd, json_encode($crons));
        $server->close($fd);
    }

    public function onClose(Server $server, int $fd)
    {
        $this->workers->del($fd);
    }

    protected function configure()
    {
        $this->setName('start')
            ->setDescription('Start a task scheduler server.')
            ->addArgument('worker-num', InputArgument::OPTIONAL, "Worker number", 1);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initCache();

        $cache = $this->cache;
        $this->sdkClient = new Client($cache->get('appKey'), $cache->get('appSecret'), $cache->get('apiUrl'));
        $this->queue = $this->createQueue();
        $this->initWorkers();
        $this->initTasks();

        $this->output = $output;
        $workerNum = $input->getArgument('worker-num');
        $server = $this->createServer($workerNum);
        $server->start();
    }

    public function initCache()
    {
        $cache = new TableCache();
        $this->cache = $cache;

        $cache->set('appKey', getenv('APP_KEY'));
        $cache->set('appSecret', getenv('APP_SECRET'));
        $cache->set('apiUrl', getenv('API_URL'));

        $cache->set('cron.version', 0);
    }

    /**
     * @return Channel
     */
    public function createQueue()
    {
        $queue = new Channel(1024 * 1024 * 32);
        return $queue;
    }

    public function initWorkers()
    {
        $table = new \Swoole\Table(1024);
        $table->column('fd', \Swoole\Table::TYPE_INT, 8);
        $table->column('name', \Swoole\Table::TYPE_STRING, 100);
        $table->column('register_time', \Swoole\Table::TYPE_INT, 8);

        $table->create();
        $this->workers = $table;
    }

    public function initTasks()
    {
        $table = new \Swoole\Table(1024);
        $table->column('cron', \Swoole\Table::TYPE_STRING, 100);
        $table->column('command', \Swoole\Table::TYPE_STRING, 200);
        $table->create();

        $this->tasks = $table;
    }

    /**
     * @param int $workerNum
     * @return Server
     */
    public function createServer(int $workerNum)
    {
        $server = new Server('0.0.0.0', 9088);
        $server->set([
            'worker_num' => $workerNum,
        ]);
        $server->on('start', [$this, 'onStart']);
        $server->on('workerStart', [$this, 'onWorkerStart']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

        // 对任务队列进行调度: 调度方式为顺序调度
        $server->addProcess($this->createSchedulerProcess($server));
        $server->addProcess($this->createCronUpdaterProcess($server, 10));
        return $server;
    }

    /**
     * 创建队列任务调度器
     *
     * @param Server $server
     * @return Process
     */
    public function createSchedulerProcess(Server $server)
    {
        $process = new Process(function (Process $process) use ($server) {
            $process->name('task-scheduler:queue-scheduler');
            while (true) {
                foreach ($this->workers as $worker) {
                    $fd = $worker['fd'];
                    $name = $worker['name'];
                    $command = $this->queue->pop();
                    if ($command !== false) {
                        if (!$server->send($fd, json_encode([
                            'action' => 'run',
                            'data' => [
                                'command' => $command
                            ],
                        ]))) {
                            $this->output->writeln("Failed to push <comment>'{$command}'</comment> to worker #{$name}.");
                        } else {
                            $this->output->writeln("Pushed <comment>'{$command}'</comment> to worker #{$name}.");
                        }
                    }
                }
            }
        }, true, false);
        return $process;
    }

    /**
     * 创建计划任务更新处理器
     *
     * 用于定时检查计划任务是否有改变
     *
     * @param Server $server
     * @param int $interval 时间间隔，单位: 秒
     * @return Process
     */
    public function createCronUpdaterProcess(Server $server, int $interval)
    {
        $process = new Process(function (Process $process) use ($server, $interval) {
            $process->name('task-scheduler:cron-updater');
            Timer::tick(1000 * $interval, function () {
                $this->updateCron();
            });
        }, true, false);

        return $process;
    }
}
