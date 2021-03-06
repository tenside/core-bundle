<?php

/**
 * This file is part of tenside/core-bundle.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core-bundle
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core-bundle/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core-bundle
 * @filesource
 */

namespace Tenside\CoreBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tenside\Core\Task\Runner;
use Tenside\Core\Util\FunctionAvailabilityCheck;

/**
 * This class executes a queued task in detached mode.
 */
class RunTaskCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('tenside:runtask')
            ->setDescription('Execute a queued task')
            ->setHelp('You most likely do not want to use this from CLI - use the web UI')
            ->addArgument('taskId', InputArgument::REQUIRED, 'The task id of the task to run.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException When another task is already running.
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // If successfully forked, exit now as the parenting process is done.
        if ($this->fork()) {
            $output->writeln('Forked into background.');
            return 0;
        }

        return parent::run($input, $output);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException When an invalid task id has been passed.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $taskList  = $container->get('tenside.tasks');
        $task      = $taskList->getTask($input->getArgument('taskId'));

        if (!$task) {
            throw new \InvalidArgumentException('Task not found: ' . $input->getArgument('taskId'));
        }

        $runner = new Runner($task, $container->get('tenside.taskrun_lock'), $container->get('logger'));

        return $runner->run(
            $container->get('kernel')->getLogDir() . DIRECTORY_SEPARATOR . 'task-' . $task->getId() . '.log'
        ) ? 0 : 1;
    }

    /**
     * Try to fork.
     *
     * The return value determines if the caller shall exit (when forking was successful and it is the forking process)
     * or rather proceed execution (is the fork or unable to fork).
     *
     * True means exit, false means go on in this process.
     *
     * @return bool
     *
     * @throws \RuntimeException When the forking caused an error.
     */
    private function fork()
    {
        /** @var LoggerInterface $logger */
        $logger = $this->getContainer()->get('logger');
        if (!$this->getContainer()->get('tenside.config')->isForkingAvailable()) {
            $logger->warning('Forking disabled by configuration, execution will block until the command has finished.');

            return false;
        } elseif (!FunctionAvailabilityCheck::isFunctionEnabled('pcntl_fork', 'pcntl')) {
            $logger->warning('pcntl_fork() is not available, execution will block until the command has finished.');

            return false;
        } else {
            $pid = pcntl_fork();
            if (-1 === $pid) {
                throw new \RuntimeException('pcntl_fork() returned -1.');
            } elseif (0 !== $pid) {
                // Tell the calling method to exit now.
                $logger->info('Forked process ' . posix_getpid() . ' to pid ' . $pid);
                return true;
            }

            $logger->info('Processing task in forked process with pid ' . posix_getpid());
            return false;
        }
    }
}
