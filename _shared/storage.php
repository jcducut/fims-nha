<?php

/**
 * Centralized S3 / MinIO storage setup for NHA FIMS.
 *
 * Include this from each module's app/global.php.
 * Requires:
 *   - _shared/load-env.php already included (provides env())
 *   - vendor/autoload.php already included (provides AWS SDK)
 */

if (defined('FIMS_STORAGE_LOADED')) {
    return;
}
define('FIMS_STORAGE_LOADED', true);

use Aws\S3\S3Client;

$s3client = new S3Client([
    'version'                 => 'latest',
    'region'                  => env('DO_SPACES_REGION', 'sgp1'),
    'endpoint'                => env('DO_SPACES_ENDPOINT', 'https://sgp1.digitaloceanspaces.com'),
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => env('DO_SPACES_KEY', ''),
        'secret' => env('DO_SPACES_SECRET', ''),
    ],
    'http' => ['verify' => false],
]);

$s3client->registerStreamWrapper();

if (!function_exists('GetSignedUrl')) {
    /**
     * Generate a pre-signed URL for a private S3 / MinIO object.
     *
     * AWS SigV4 signs the Host header itself (X-Amz-SignedHeaders=host) — a
     * URL presigned against one host can't just be string-rewritten to
     * another afterward, it comes back "SignatureDoesNotMatch". So when
     * DO_SPACES_PUBLIC_ENDPOINT is set (local MinIO dev setups, where the
     * Docker-internal endpoint $s3client talks to for uploads, e.g.
     * "http://minio:9000", differs from the host-mapped port a browser can
     * actually reach, e.g. "http://localhost:9020"), this signs against a
     * second client built with that public endpoint instead — same
     * credentials/bucket, just a different (and browser-reachable) host
     * baked into the signature from the start. In production, where the
     * same endpoint is reachable both ways (real DigitalOcean Spaces), leave
     * DO_SPACES_PUBLIC_ENDPOINT unset and this is exactly the original
     * single-client behavior.
     *
     * @param string $objectKey  S3 object key (e.g. "NHA-FIMS/docs/report.pdf")
     * @param string $expiration Relative expiry accepted by strtotime() (default: +48 hours)
     * @return string            The pre-signed URL
     */
    function GetSignedUrl(string $objectKey, string $expiration = '+48 hours'): string
    {
        global $s3client;

        $publicEndpoint = env('DO_SPACES_PUBLIC_ENDPOINT', '');
        static $publicS3client = null;
        if ($publicEndpoint !== '') {
            if ($publicS3client === null) {
                $publicS3client = new S3Client([
                    'version'                 => 'latest',
                    'region'                  => env('DO_SPACES_REGION', 'sgp1'),
                    'endpoint'                => $publicEndpoint,
                    'use_path_style_endpoint' => true,
                    'credentials' => [
                        'key'    => env('DO_SPACES_KEY', ''),
                        'secret' => env('DO_SPACES_SECRET', ''),
                    ],
                    'http' => ['verify' => false],
                ]);
            }
            $client = $publicS3client;
        } else {
            $client = $s3client;
        }

        $cmd     = $client->getCommand('GetObject', [
            'Bucket' => env('DO_SPACES_BUCKET', ''),
            'Key'    => $objectKey,
        ]);
        $request = $client->createPresignedRequest($cmd, $expiration);

        return (string) $request->getUri();
    }
}
