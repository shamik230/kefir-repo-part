<?php

namespace Marketplace\Tokens\Jobs;

use Log;

class ResizerMp4
{
    public function resize($data)
    {
        Log::info('ResizerMp4 ' . var_export($data['file'], true));
        $inputFile = $data['file'];

        if (!file_exists($inputFile)) {
            Log::info('ResizerMp4@Error: Input file does not exist: ' . var_export($inputFile, true));
        }

        $outputFile = str_replace(".mp4", ".webp", $inputFile);

        $command = sprintf(
            'ffmpeg -i %s -vf "scale=370:370" -c:v libwebp -q:v 90 %s',
            escapeshellarg($inputFile),
            escapeshellarg($outputFile)
        );

        Log::info('Executing command: ' . $command);

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('ResizerMp4@Error: Command failed with output: ' . implode("\n", $output));
        }

        Log::info('Successfully processed file: ' . $outputFile);

        return $outputFile;
    }
}
