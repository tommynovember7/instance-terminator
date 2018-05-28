<?php

namespace AppBundle\S3;

use Aws\S3\S3Client;
use AppBundle\S3\Exception\S3Exception;

/**
 * Class Client
 *
 * @package AppBundle\S3
 */
class Client
{
    /**
     * @var S3Client
     */
    protected $s3Client;


    /**
     * @var string
     */
    private $bucket;

    /**
     * Client constructor.
     *
     * @param S3Client $s3Client
     */
    public function __construct(S3Client $s3Client)
    {
        $this->s3Client = $s3Client;

        // XXX:
        // Allow you to use built-in PHP stream functions.
        // E.g.  $data = file_get_contents('s3://bucket/key');
        if (!\in_array('s3', stream_get_wrappers(), true)) {
            $this->s3Client->registerStreamWrapper();
        }
    }

    /**
     * @return S3Client
     */
    public function getS3Client(): S3Client
    {
        return $this->s3Client;
    }

    /**
     * @return string
     * @throws S3Exception
     */
    public function getBucket(): string
    {
        if ($this->bucket === null || empty($this->bucket)) {
            throw new S3Exception('S3 Bucket undefined.');
        }

        return $this->bucket;
    }

    /**
     * @param string $bucket
     * @return $this
     */
    public function setBucket($bucket): self
    {
        $this->bucket = $bucket;

        return $this;
    }

    /**
     * @param string $path
     * @return string
     * @throws S3Exception
     */
    public function composeStreamPath($path): string
    {
        return $this->composeS3Uri($this->getBucket(), $path);
    }

    /**
     * @param string $bucket
     * @param string $key
     * @return string
     */
    public function composeS3Uri($bucket, $key): string
    {
        return sprintf('s3://%s/%s', $bucket, ltrim($key, '/'));
    }

    /**
     * @param string      $key
     * @param string|null $bucket
     * @return bool
     * @throws S3Exception
     */
    public function doesObjectExist($key, $bucket = null): bool
    {
        $bucket = $bucket ?? $this->getBucket();
        if (null === $bucket) {
            throw new S3Exception('S3 Bucket undefined.');
        }

        return $this->getS3Client()->doesObjectExist($bucket, ltrim($key, '/'));
    }

    /**
     * @param string $writableFile
     * @param string $readableFile
     * @throws S3Exception
     */
    public function streamCopy($writableFile, $readableFile): void
    {
        $readableStream = fopen($readableFile, 'rb');
        if (!\is_resource($readableStream)) {
            throw new S3Exception(sprintf('Failed to open stream: %s', $readableStream));
        }
        $writableStream = fopen($writableFile, 'wb');
        if (!\is_resource($writableStream)) {
            throw new S3Exception(sprintf('Failed to open stream: %s', $readableStream));
        }
        stream_copy_to_stream($readableStream, $writableStream);
        fclose($readableStream);
        fclose($writableStream);
    }
}
