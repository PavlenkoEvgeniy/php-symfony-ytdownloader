# YT Downloader

A personal service that downloads videos by URL, tracks the work as tasks in the database, and notifies the requester through Telegram.

## Language

### Tasks

**Download Task**:
A row in the database representing one requested download. The task is the record of work, not the work itself.
_Avoid_: job, message (a message is only the trigger for a task)

**Task Status**:
The lifecycle of a task: `queued` → `processing` → `success` | `error`.

**Processing**:
The status meaning "the worker has picked this task up". It is not a guarantee that the download is still running: if the worker dies mid-task, the task stays `processing` until it is redone.
_Avoid_: running, in progress

### Queue

**Queue**:
The database view of tasks waiting to be worked on, counted by task status (`queued`). Not a broker queue: the messaging transport is an implementation detail behind this term.
_Avoid_: RabbitMQ, broker queue, exchange

**Worker**:
The supervisor-driven background process that consumes messages and performs downloads.