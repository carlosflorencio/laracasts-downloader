# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.3 CLI (Custom `start.php` entry point)
**Primary Dependencies**: 
- `guzzlehttp/guzzle`: HTTP Client
- `league/flysystem`: File storage
- `symfony/dom-crawler` & `css-selector`: Parsing
- `devster/ubench`: Benchmarking
**Storage**: Local filesystem (via Flysystem)
**Testing**: [NEEDS CLARIFICATION: Currently no test suite. Proposed: Pest or PHPUnit?]
**Target Platform**: CLI (Windows/Linux/macOS) - Docker supported
**Project Type**: CLI Application
**Performance Goals**: Maximize download throughput via parallelization (Segments + Episodes)
**Constraints**: 
- **NO FFmpeg** (User requirement: "It should not use FFmpeg").
- Must be performant.
**Scale/Scope**: Features: Parallel Segment Downloader, Parallel Episode Queue.

## Constitution Check
 
- **P1 Code Quality**: PASS (Existing code follows standards).
- **P2 Testing Standards**: **FAIL**. No test suite exists. **ACTION**: Must initialize testing framework (PestPHP recommended for Laravel-like DX) and add tests for new parallel logic.
- **P3 UX**: PASS (Parallelism improves UX).
- **P4 Performance**: PASS (This feature explicitly addresses P4).

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

[Gates passed with remediation plan for P2]

## Proposed Changes

### Core Infrastructure
#### [MODIFY] [composer.json](file:///G:/Other/laracasts-downloader/composer.json)
- Add `pestphp/pest` to `require-dev`.
- Update dependencies if needed.

#### [NEW] [tests/](file:///G:/Other/laracasts-downloader/tests)
- Initialize Pest test suite (`Pest.php`, `TestCase.php`).

#### [NEW] [App/Contracts/AsyncDownloader.php](file:///G:/Other/laracasts-downloader/App/Contracts/AsyncDownloader.php)
- Define `downloadAsync` interface.

### Download Logic
#### [MODIFY] [App/Downloader.php](file:///G:/Other/laracasts-downloader/App/Downloader.php)
- Refactor `downloadEpisodes` to usage of `GuzzleHttp\Pool`.
- Implement `Option 2: Concurrent Episode Queue`.

#### [MODIFY] [App/Vimeo/VimeoDownloader.php](file:///G:/Other/laracasts-downloader/App/Vimeo/VimeoDownloader.php)
- Implement `AsyncDownloader` interface.
- Refactor `download` to `downloadAsync`.
- Implement segment parallelization using `GuzzleHttp\Pool`.

#### [MODIFY] [App/Http/Resolver.php](file:///G:/Other/laracasts-downloader/App/Http/Resolver.php)
- Update `downloadEpisode` to return Promise or await result.

## Verification Plan

### Automated Tests
- Run `vendor/bin/pest`.
- Test `ParallelDownloader` logic with mocked Guzzle responses.

### Manual Verification
- Run `php start.php ... --concurrency-episodes=3`.
- Observe output logs for interleaved download progress.
- Verify downloaded files integrity (play video).
