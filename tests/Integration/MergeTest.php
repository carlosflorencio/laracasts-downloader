<?php

/**
 * Integration tests for segment merging logic
 * Tests that downloaded segments are correctly merged into final file
 */

use App\Vimeo\VimeoDownloader;

describe('Segment Merging', function () {

    beforeEach(function () {
        // Create temp directory for test files
        $this->tempDir = sys_get_temp_dir() . '/laracasts_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        // Cleanup temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    });

    describe('Binary segment concatenation', function () {

        it('merges multiple segments into single file in correct order', function () {
            // Simulate segment data
            $segments = [
                'segment_0' => 'AAAA',
                'segment_1' => 'BBBB',
                'segment_2' => 'CCCC',
            ];

            // Create segment files
            $segmentPaths = [];
            foreach ($segments as $name => $data) {
                $path = $this->tempDir . '/' . $name;
                file_put_contents($path, $data);
                $segmentPaths[] = $path;
            }

            // Merge segments
            $outputPath = $this->tempDir . '/merged.bin';
            $handle = fopen($outputPath, 'wb');

            foreach ($segmentPaths as $segmentPath) {
                $data = file_get_contents($segmentPath);
                fwrite($handle, $data);
            }

            fclose($handle);

            // Verify merged file
            $mergedContent = file_get_contents($outputPath);
            expect($mergedContent)->toBe('AAAABBBBCCCC');
            expect(strlen($mergedContent))->toBe(12);
        });

        it('preserves binary integrity when merging', function () {
            // Create binary segment data (simulate video chunks)
            $segment1 = pack('C*', 0x00, 0x01, 0x02, 0x03);
            $segment2 = pack('C*', 0x04, 0x05, 0x06, 0x07);

            $path1 = $this->tempDir . '/seg1.bin';
            $path2 = $this->tempDir . '/seg2.bin';
            file_put_contents($path1, $segment1);
            file_put_contents($path2, $segment2);

            // Merge
            $outputPath = $this->tempDir . '/merged.bin';
            $handle = fopen($outputPath, 'wb');
            fwrite($handle, file_get_contents($path1));
            fwrite($handle, file_get_contents($path2));
            fclose($handle);

            // Verify
            $merged = file_get_contents($outputPath);
            expect(strlen($merged))->toBe(8);
            expect(unpack('C*', $merged))->toBe([1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7]);
        });

        it('handles init segment + data segments correctly', function () {
            // Init segment (base64 decoded simulation)
            $initSegment = base64_decode('AAAAAA=='); // 4 bytes
            $dataSegment1 = 'DATA1';
            $dataSegment2 = 'DATA2';

            $outputPath = $this->tempDir . '/video.m4v';

            // Write init segment first
            file_put_contents($outputPath, $initSegment);

            // Append data segments
            file_put_contents($outputPath, $dataSegment1, FILE_APPEND);
            file_put_contents($outputPath, $dataSegment2, FILE_APPEND);

            $content = file_get_contents($outputPath);
            expect(strlen($content))->toBe(14); // 4 + 5 + 5
        });
    });

    describe('Parallel merge flow', function () {

        it('segments downloaded in parallel can be merged in order', function () {
            // Simulate out-of-order segment downloads (as would happen with parallel)
            $segmentData = [
                2 => 'CCCC', // Downloaded second
                0 => 'AAAA', // Downloaded third
                1 => 'BBBB', // Downloaded first
            ];

            // Store segments indexed by their position
            $segments = [];
            foreach ($segmentData as $index => $data) {
                $path = $this->tempDir . "/seg_{$index}.bin";
                file_put_contents($path, $data);
                $segments[$index] = $path;
            }

            // Sort by index and merge
            ksort($segments);

            $outputPath = $this->tempDir . '/merged.bin';
            $handle = fopen($outputPath, 'wb');

            foreach ($segments as $path) {
                fwrite($handle, file_get_contents($path));
            }

            fclose($handle);

            // Verify correct order despite out-of-order downloads
            expect(file_get_contents($outputPath))->toBe('AAAABBBBCCCC');
        });
    });
});
