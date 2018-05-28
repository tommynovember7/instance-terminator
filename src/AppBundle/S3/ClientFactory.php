<?php

namespace AppBundle\S3;

use Aws\S3\S3Client;

/**
 * Class ClientFactory
 *
 * @package AppBundle\S3
 */
class ClientFactory
{
    /**
     * @param S3Client $s3Client
     * @return Client
     */
    public static function createS3Client(S3Client $s3Client): Client
    {
        return new Client($s3Client);
    }
}
