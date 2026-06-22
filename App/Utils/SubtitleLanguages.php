<?php

namespace App\Utils;

/**
 * Decides which subtitle/caption tracks to download based on the
 * SUBTITLE_LANGUAGE env (mirrors AUDIO_LANGUAGE):
 *
 *   - empty   -> only the default/original track (the DEFAULT flag, else
 *                English, else the first track)
 *   - 'all'   -> every available track
 *   - 'en,es' -> those languages when present, otherwise the default track
 *
 * Each track must carry a 'language' key and may carry a 'default' bool.
 */
class SubtitleLanguages
{
    /**
     * @param  array<int, array<string, mixed>>  $tracks
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $tracks): array
    {
        if ($tracks === []) {
            return [];
        }

        $setting = strtolower(trim((string) ($_ENV['SUBTITLE_LANGUAGE'] ?? '')));

        if ($setting === 'all') {
            return $tracks;
        }

        if ($setting !== '') {
            $wanted = array_filter(array_map('trim', explode(',', $setting)));

            $picked = array_values(array_filter(
                $tracks,
                fn (array $track): bool => in_array(strtolower((string) ($track['language'] ?? '')), $wanted, true)
            ));

            // fall back to the default track when none of the requested exist
            if ($picked !== []) {
                return $picked;
            }
        }

        return [self::defaultTrack($tracks)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tracks
     * @return array<string, mixed>
     */
    private static function defaultTrack(array $tracks): array
    {
        foreach ($tracks as $track) {
            if (! empty($track['default'])) {
                return $track;
            }
        }

        foreach ($tracks as $track) {
            if (strtolower((string) ($track['language'] ?? '')) === 'en') {
                return $track;
            }
        }

        return $tracks[0];
    }
}
