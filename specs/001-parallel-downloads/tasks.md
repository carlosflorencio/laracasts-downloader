---
description: "Task list for Parallel Downloads feature implementation"
---

# Tasks: Parallel Downloads

**Input**: Design documents from `/specs/001-parallel-downloads/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: The examples below include test tasks. Tests are MANDATORY per Constitution P2: Testing Standards. Every feature or significant logic change MUST include automated tests.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Install PestPHP dependency `composer require --dev pestphp/pest`
- [x] T002 Initialize Pest with `./vendor/bin/pest --init` and setup `tests/Pest.php`
- [x] T003 [P] Configure basic test settings in `phpunit.xml` if needed

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Create `AsyncDownloader` interface in `App/Contracts/AsyncDownloader.php`
- [x] T005 [P] Create `ConcurrencyManager` helper class (or similar) if separate logic is needed, otherwise ensure `Downloader` layout is ready

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Accelerated Single Episode Download (Priority: P1) 🎯 MVP

**Goal**: Download individual video segments in parallel (Scatter-Gather).

**Independent Test**: Download a single video, verify speed/concurrency logs, and valid file output.

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T006 [P] [US1] Create unit test for `VimeoDownloader::downloadAsync` in `tests/Unit/VimeoDownloaderTest.php` (mock Guzzle responses)
- [x] T007 [P] [US1] Create integration test for segment merging logic in `tests/Integration/MergeTest.php`

### Implementation for User Story 1

- [x] T008 [US1] Modify `App/Vimeo/VimeoDownloader.php` to implement `AsyncDownloader` interface
- [x] T009 [US1] Refactor `VimeoDownloader` to use `GuzzleHttp\Pool` for fetching segments in parallel
- [x] T010 [US1] Ensure `VimeoDownloader` respects `max_concurrent_segments` option
- [x] T011 [US1] verify `mergeSources` still works with new async flow (ensure Promise resolves to path)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Concurrent Episode Bench Downloads (Priority: P1)

**Goal**: Queue multiple episodes and download them in parallel.

**Independent Test**: Queue 5 episodes, verify N start immediately.

### Tests for User Story 2 ⚠️

- [x] T012 [P] [US2] Create unit test for `Downloader::downloadEpisodes` concurrent queue in `tests/Unit/DownloaderTest.php`

### Implementation for User Story 2

- [x] T013 [US2] update `App/Downloader.php` to accept `AsyncDownloader` implementation
- [x] T014 [US2] Refactor `downloadEpisodes` loop to use `GuzzleHttp\Pool` (or `EachPromise`) for episode concurrency

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Resource Management Configuration (Priority: P2)

**Goal**: Configure concurrency limits via CLI flags.

**Independent Test**: Run with flags, check active promise count.

### Tests for User Story 3 ⚠️

- [x] T015 [P] [US3] Add test case for CLI options parsing in `tests/Unit/OptionsTest.php`

### Implementation for User Story 3

- [x] T016 [US3] Update `App/Downloader.php` `setFilters` or `start` method to parse `--concurrency-episodes` and `--concurrency-segments`
- [x] T017 [US3] Pass configuration values down to `VimeoDownloader` and `Downloader` pool config

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T018 [P] Update `README.md` with new concurrency flags documentation
- [x] T019 Run full test suite `vendor/bin/pest` and fix any regressions
- [x] T020 Manual Verification: Run full download cycle with high concurrency

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup
- **User Stories (Phase 3+)**: Depend on Foundational
- **Polish (Final Phase)**: Depends on all stories

### User Story Dependencies

- **US1**: Foundation -> US1
- **US2**: Foundation -> US2 (Ideally US1 logic is used inside US2, but can be mocked)
- **US3**: Foundation -> US3 (Can be done anytime, but needs US1/US2 to actually control something)

### Parallel Opportunities

- T006, T007, T012, T015 (Tests) can be written in parallel
- US1 and US2 implementation can theoretically happen in parallel if `AsyncDownloader` contract is stable

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Setup + Foundational
2. Implement US1 (Parallel Segments) -> HUGE WIN for single file speed
3. Validate
4. Implement US2 (Parallel Episodes) -> Bulk speedup
5. Implement US3 (Config) -> Safety
