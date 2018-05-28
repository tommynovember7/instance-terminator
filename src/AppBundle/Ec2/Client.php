<?php

namespace AppBundle\Ec2;

use Aws\Ec2\Ec2Client;
use Aws\Result;

/**
 * Class Client
 *
 * @package AppBundle\Ec2
 */
class Client
{
    /**
     * @var Ec2Client
     */
    private $ec2Client;

    /**
     * @var string
     */
    private $metaAccessHost;

    /**
     * Client constructor.
     *
     * @param Ec2Client $ec2Client
     * @param string    $metaAccessHost
     */
    public function __construct(Ec2Client $ec2Client, $metaAccessHost)
    {
        $this->ec2Client = $ec2Client;
        $this->metaAccessHost = $metaAccessHost;
    }

    /**
     * @return Result
     */
    public function terminateCurrentInstance(): Result
    {
        return $this->ec2Client->terminateInstances(['InstanceIds' => [$this->getOwnInstanceId()]]);
    }

    /**
     * @return string
     */
    public function getOwnInstanceId(): string
    {
        return file_get_contents($this->metaAccessHost.'/instance-id');
    }
}
