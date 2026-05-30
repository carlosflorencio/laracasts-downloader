<?php

namespace App\Mux;

class MuxDownloader
{

    public function download($muxPlaybackId, $muxToken, string $filepath): bool
    {
        return $this->downloadFile($muxPlaybackId, $muxToken, $filepath);
    }

    private function downloadFile ($muxPlaybackId, $muxToken, $outputPath)
    {
        $code = 0;
        $output = [];

        if (PHP_OS === 'WINNT') {
            $command = "ffmpeg -i \"https://stream.mux.com/$muxPlaybackId.m3u8?token=$muxToken\" -c  copy -strict -2 \"$outputPath\" 2> nul";
        } else {
            $command = "ffmpeg -i 'https://stream.mux.com/$muxPlaybackId.m3u8?token=$muxToken' -c copy -strict -2 '$outputPath' >/dev/null 2>&1";
        }

        exec($command, $output, $code);

        if ($code == 0) {
            return true;
        }

        return false;
    }
}
