<#
.SYNOPSIS
Embeds .chapters.txt ffmetadata sidecars into their matching .mp4 files.

.DESCRIPTION
For every NN-title.mp4 with a NN-title.chapters.txt next to it, remuxes the
video in place with the chapter markers embedded (stream copy, no re-encoding,
takes seconds per file). Videos that already contain chapters are skipped, so
the script is safe to re-run. Requires ffmpeg and ffprobe on PATH.

.PARAMETER Path
Folder to process. Defaults to the current directory.

.PARAMETER Recurse
Also process all subfolders (e.g. run once on the whole series library).

.EXAMPLE
cd "E:\library\series\some-series"
& "E:\path\to\laracasts-downloader\commands\merge-chapters.ps1"

.EXAMPLE
& .\commands\merge-chapters.ps1 -Path "E:\library\series" -Recurse
#>
param(
    [string]$Path = '.',
    [switch]$Recurse
)

$videos = Get-ChildItem -LiteralPath $Path -Filter *.mp4 -File -Recurse:$Recurse

$merged = 0
$skipped = 0
$noSidecar = 0
$failed = 0

foreach ($video in $videos) {
    $sidecar = $video.FullName -replace '\.mp4$', '.chapters.txt'

    if (-not (Test-Path -LiteralPath $sidecar)) {
        $noSidecar++
        continue
    }

    $existing = & ffprobe -v error -show_chapters -of csv $video.FullName 2>$null

    if ($existing) {
        Write-Host "skip (already has chapters): $($video.Name)"
        $skipped++
        continue
    }

    $temp = "$($video.FullName).tmp.mp4"

    $output = & ffmpeg -y -hide_banner -loglevel error -i $video.FullName -f ffmetadata -i $sidecar -map_chapters 1 -c copy $temp 2>&1

    if ($LASTEXITCODE -eq 0) {
        Move-Item -LiteralPath $temp -Destination $video.FullName -Force
        Write-Host "merged: $($video.Name)"
        $merged++
    }
    else {
        Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue
        Write-Warning "failed: $($video.Name)`n$($output -join "`n")"
        $failed++
    }
}

Write-Host ""
Write-Host "Done. $merged merged, $skipped already had chapters, $noSidecar without sidecar, $failed failed."
