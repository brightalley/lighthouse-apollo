<?php

namespace BrightAlley\LighthouseApollo\Commands;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\Connectors\RedisConnector;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class SubmitTracing extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lighthouse-apollo:submit-tracing';

    /**
     * The console command description.
     */
    protected $description = 'Send the pending tracing results from Redis or the database to Apollo Studio.';

    private Config $config;

    private RedisConnector $redisConnector;

    /**
     * Create a new console command instance.
     *
     * @param Config $config
     * @param RedisConnector $redisConnector
     */
    public function __construct(Config $config, RedisConnector $redisConnector)
    {
        parent::__construct();

        $this->config = $config;
        $this->redisConnector = $redisConnector;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sendTracingMode = $this->config->get(
            'lighthouse-apollo.send_tracing_mode',
        );
        switch ($sendTracingMode) {
            case 'sync':
                $this->output->writeln(
                    'Send tracing mode is set to "sync", nothing to do.',
                );
                return 0;
            case 'redis':
                return $this->handleFromRedis();
            default:
                $this->output->error(
                    'Tracing mode "' . $sendTracingMode . '" is not supported.',
                );
                return 1;
        }
    }

    private function handleFromRedis(): int
    {
        while (true) {
            $tracings = $this->redisConnector->getPending();
            if (count($tracings) === 0) {
                $this->output->warning('No pending tracings on Redis.');

                return 0;
            }

            $this->output->writeln(
                'Sending ' . count($tracings) . ' tracing(s) to Apollo Studio',
            );

            try {
                (new SendTracingToApollo($this->config, $tracings))->send();
            } catch (Exception $e) {
                $this->error('An error occurred submitting tracings:');
                $this->error($e->getMessage());

                // If the traces are not considered too old, put them back to retry later.
                if (strpos($e->getMessage(), 'skewed timestamp') === false) {
                    // Put the tracings back on the queue.
                    $this->redisConnector->putMany($tracings);
                }

                return 1;
            }
        }

        $this->output->writeln('All done!');
        return 0;
    }
}
