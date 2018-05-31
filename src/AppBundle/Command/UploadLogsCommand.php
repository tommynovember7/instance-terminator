<?php

namespace AppBundle\Command;

use AppBundle\S3\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class UploadLogsCommand
 *
 * @package AppBundle\Command
 */
class UploadLogsCommand extends ContainerAwareCommand
{
    /**
     * @return void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure() : void
    {
        $this
            ->setName('app:upload-logs')
            ->setDescription('Upload system and application logs to S3.')
            ->addOption('root', 'r', InputOption::VALUE_NONE, 'execute as root')
            ->addOption('explicit', 'x', InputOption::VALUE_NONE, 'output messages');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        chdir(sys_get_temp_dir());
        $destination = $this->composeDestinationPath();
        if (file_exists($destination)) {
            $this->executeCommand(sprintf('rm -rf %s', $destination), $input->getOption('root'));
        }
        $this->executeCommand(sprintf('mkdir %s', $destination), $input->getOption('root'));

        $targetFiles = [
            'system'      => $this->getContainer()->getParameter('upload.targets.system.logs'),
            'application' => $this->getContainer()->getParameter('upload.targets.application.logs'),
        ];

        array_walk(
            $targetFiles,
            function ($items, $type) use ($destination, $input, $output) {
                array_walk(
                    $items,
                    $this->getLogHandler([$type, $destination, $input, $output])
                );
            }
        );

        if (file_exists($destination)) {
            $this->executeCommand(sprintf('rm -rf %s', $destination), $input->getOption('root'));
        }
    }

    /**
     * @param array $arguments
     * @return \Closure
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     */
    private function getLogHandler(array $arguments = []): callable
    {
        [$type, $destination, $input, $output] = $arguments;

        return function ($fileNameBody) use ($type, $destination, $input, $output) {
            $this->executeCommand(sprintf('chmod -R 777 %s', $destination), $input->getOption('root'));
            $sourceFile = sprintf('%s/%s', $this->getBase($type), $this->getFileName($fileNameBody, $type));
            if (!file_exists($sourceFile)) {
                return;
            }

            $partition = sprintf('%s/%s', $destination, $fileNameBody);
            if (!mkdir($partition, 0777) && !is_dir($partition)) {
                return;
            }

            $this->executeCommand(
                sprintf('cp %s* %s/%s/', $sourceFile, $destination, $fileNameBody),
                $input->getOption('root')
            );

            $this->executeCommand(sprintf('chmod -R 777 %s', $destination), $input->getOption('root'));
            $zipArchive = sprintf('%s/%s', $partition, $this->getFileName($fileNameBody, 'zip'));
            $this->createZipArchive($partition, $zipArchive, $this->getFileName($fileNameBody, $type));
            if (!file_exists($zipArchive)) {
                return;
            }

            $s3Client = $this->getS3Client();
            $s3Key = $this->getS3Key($type, $fileNameBody, $this->getFileName($fileNameBody, 'zip'));
            $s3Client->putObject(
                [
                    'Key'           => ltrim($s3Key, '/'),
                    'SourceFile'    => $zipArchive,
                    'ContentSHA256' => $s3Client->getSha256Value($zipArchive),
                ]
            );

            if ($input->getOption('explicit')) {
                $output->writeln('Uploaded: '.$s3Key);
            }
        };
    }

    /**
     * @return string
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    private function composeDestinationPath(): string
    {
        $directoryName = $this->getContainer()->get('app.ec2.client')->getOwnInstanceId();

        return sprintf('%s/%s', sys_get_temp_dir(), trim($directoryName));
    }

    /**
     * @param string $command
     * @param bool   $asRoot
     * @return string
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function executeCommand($command, $asRoot = false): string
    {
        if (!$asRoot) {
            $command = 'sudo '.$command;
        }
        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * @param string $type
     * @return null|string
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function getBase($type): ?string
    {
        $container = $this->getContainer();
        switch ($type) {
            case 'system':
                return $container->getParameter('upload.targets.system.location');
                break;
            case 'application':
                return $container->getParameter('upload.targets.application.location');
                break;
            default:
                return null;
        }
    }

    /**
     * @param string $nameBody
     * @param string $type
     * @return string
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Exception
     * @throws \Exception
     */
    private function getFileName($nameBody, $type): ?string
    {
        switch ($type) {
            case 'zip':
                $dateTime = new \DateTimeImmutable();
                $instanceId = $this->getContainer()->get('app.ec2.client')->getOwnInstanceId();

                return sprintf('%s_%s.zip', $dateTime->format('YmdHis'), trim($instanceId));
                break;
            default:
                return $nameBody;
        }
    }

    /**
     * @param string $currentDir
     * @param string $zipArchive
     * @param string $logFile
     * @return null
     */
    private function createZipArchive($currentDir, $zipArchive, $logFile)
    {
        $zip = new \ZipArchive();
        $zip->open($zipArchive, \ZipArchive::CREATE);
        chdir($currentDir);
        if (file_exists($logFile)) {
            $zip->addFile($logFile);
        }
        $zip->close();

        return file_exists($zipArchive) ? $zipArchive : null;
    }

    /**
     * @param string $type
     * @param string $fileNameBody
     * @param string $fileName
     * @return string
     */
    private function getS3Key($type, $fileNameBody, $fileName): string
    {
        return sprintf(
            '%s/%s/%s/%s/%s',
            $this->getContainer()->getParameter('upload.service_name'),
            $this->getContainer()->getParameter('upload.server_type'),
            ltrim($type),
            ltrim($fileNameBody),
            $fileName
        );
    }

    /**
     * @return Client
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function getS3Client(): Client
    {
        $s3Client = $this->getContainer()->get('app.s3.client');
        $s3Client->setBucket($this->getContainer()->getParameter('aws_s3_bucket_for_logs'));

        return $s3Client;
    }
}
