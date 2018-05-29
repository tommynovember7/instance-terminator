<?php

namespace AppBundle\Command;

use AppBundle\Ec2\Exception\Ec2Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class MonitorCommand
 *
 * @package AppBundle\Command
 */
class MonitorCommand extends ContainerAwareCommand
{
    /**
     * @return void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure() : void
    {
        $this
            ->setName('app:monitor')
            ->setDescription('Check a process and if not exist then tries to terminate the current instance.')
            ->addOption('process-filter', 'p', InputOption::VALUE_REQUIRED, 'It sets `PHP` as default if not specified');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \Exception
     * @throws \Exception
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            chdir(sys_get_temp_dir());
            $targetProcess = $input->getOption('process-filter') ?? 'php';
            $message = $this->extractTargetProcess($this->executeCommand(sprintf('ps aux | grep %s', $targetProcess)));
            $this->getContainer()->get('monolog.logger.process_monitor')->info($message);
        } catch (Ec2Exception $exception) {
            $this->getContainer()->get('monolog.logger.process_monitor')->emergency(
                $exception->getMessage(),
                [$this->getContainer()->getParameter('target_process')]
            );
            $this->executeUploadLogs($output);
            if ($this->getContainer()->getParameter('kernel.environment') === 'prod') {
                $this->getContainer()->get('app.ec2.client')->terminateCurrentInstance();
            }
        }
    }

    /**
     * @param OutputInterface $output
     * @throws \Exception
     * @throws \Exception
     */
    private function executeUploadLogs(OutputInterface $output): void
    {
        $translator = $this->getContainer()->get('translator');
        $command = $this->getApplication()->find('app:upload-logs');
        if (!$command) {
            return;
        }
        $this->getContainer()->get('monolog.logger.process_monitor')->info($translator->trans('backup.start'));
        $result = $command->run(new ArrayInput($this->composeArgumentsForUploadLogs()), $output);
        if ($result) {
            return;
        }
        $this->getContainer()->get('monolog.logger.process_monitor')->info($translator->trans('backup.finished'));
    }

    /**
     * @return array
     */
    private function composeArgumentsForUploadLogs(): array
    {
        $arguments = ['command' => 'app:upload-logs'];
        if ($this->getContainer()->getParameter('kernel.environment') === 'dev') {
            $arguments = array_merge($arguments, ['--root' => true, '--explicit' => true]);
        }

        return $arguments;
    }

    /**
     * @param string $content
     * @return string
     * @throws Ec2Exception
     */
    private function extractTargetProcess($content): string
    {
        $container = $this->getContainer();

        if ($uploadProcess = $this->processExists('#(app:upload-logs(.+)?)#', $content)) {
            return sprintf('%s (%s)', $container->get('translator')->trans('found'), $uploadProcess);
        }

        $pattern = sprintf('#(%s( .+)?)#', $container->getParameter('target_process'));
        if ($process = $this->processExists($pattern, $content)) {
            return sprintf('%s (%s)', $container->get('translator')->trans('found'), $process);
        }

        throw new Ec2Exception($container->get('translator')->trans('not_found'));
    }

    /**
     * @param string $pattern
     * @param string $content
     * @return string|null
     */
    private function processExists($pattern, $content)
    {
        preg_match($pattern, $content, $matched);

        if (isset($matched[1]) && !empty($matched[1])) {
            return $matched[1];
        }

        return null;
    }

    /**
     * @param string $command
     * @return string
     * @throws ProcessFailedException
     */
    private function executeCommand($command): string
    {
        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
