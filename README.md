# Queue System

A lightweight PHP queue system built from scratch as a learning project
to understand how queue systems work, from job storage and scheduling to worker execution.

## Features

- Multiple storage drivers: **InMemory**, **SQLite**, **Redis**
- Delayed jobs for scheduled execution
- Named queues for workload isolation
- Priority-based processing (higher priority first, FIFO within the same priority)
- Atomic job claiming to prevent duplicate processing across multiple workers
- Configurable retry attempts and job timeouts
- Visibility timeouts for recovering jobs from failed workers
- Job execution timeouts via `pcntl_alarm`
- Daemon worker with graceful shutdown support
- Failed job handling with retry support
- **57 tests**, **120 assertions** run identically across all drivers
