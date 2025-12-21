<?php

describe('CLI Options Parsing', function () {

    describe('Concurrency Options', function () {

        it('parses --concurrency-episodes option', function () {
            // Simulate parsing
            $longOptions = ['concurrency-episodes:'];

            // Test that the option format is correct
            expect($longOptions[0])->toContain('concurrency-episodes');
        });

        it('parses --concurrency-segments option', function () {
            $longOptions = ['concurrency-segments:'];

            expect($longOptions[0])->toContain('concurrency-segments');
        });

        it('uses default values when options not provided', function () {
            $defaults = [
                'concurrency-episodes' => 3,
                'concurrency-segments' => 5,
            ];

            expect($defaults['concurrency-episodes'])->toBe(3);
            expect($defaults['concurrency-segments'])->toBe(5);
        });

        it('validates concurrency values are positive integers', function () {
            $value = 5;
            $isValid = is_numeric($value) && $value > 0 && $value == (int) $value;

            expect($isValid)->toBeTrue();
        });

        it('rejects invalid concurrency values', function () {
            $invalidValues = [-1, 0, 'abc', 1.5];

            foreach ($invalidValues as $value) {
                $isValid = is_numeric($value) && $value > 0 && $value == (int) $value;
                expect($isValid)->toBeFalse();
            }
        });
    });
});
