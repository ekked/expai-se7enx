<?php /* #?ini charset="utf-8"?

# The job types of expai in the job queue (extension expqueue), and the concurrency of the lanes of the built-in
# providers. See doc/lanes.md and doc/budgets.md. Override in settings/override/expqueue.ini.append.php.

# One request to a model on a lane (expAiQueue::request()). The class comes from the producer
# (expai.ini [LanePolicy] PurposeClass[]); the resource is <provider>:<lane>.
[JobType_expai.request]
Handler=expAiRequestHandler
Class=editorial
MaxAttempts=5
Timeout=300
RetryBase=30
MaxBackoff=1800
Description=One request to a model on a lane (standard, flex, fast or batch)

# Collects the waiting requests of one model and batch lane into a provider batch.
[JobType_expai.batch.submit]
Handler=expAiBatchSubmitHandler
Class=system
MaxAttempts=6
Timeout=300
RetryBase=60
MaxBackoff=1800
Description=Submits the waiting requests of a batch lane as one provider batch

# Polls one provider batch until it ended, then hands its results to the requests.
[JobType_expai.batch.poll]
Handler=expAiBatchPollHandler
Class=system
MaxAttempts=20
Timeout=600
RetryBase=60
MaxBackoff=3600
Description=Polls a provider batch and hands its results to the waiting requests

# Imports a slice of the objects of a content package as drafts.
[JobType_expai.package.import]
Handler=expAiPackageImportHandler
Class=bulk
MaxAttempts=3
Timeout=600
RetryBase=30
MaxBackoff=600
Description=Imports objects of a content package as drafts

# The concurrency of each lane: jobs of the resource <provider>:<lane> at once across all workers. A 429 or a
# retry-after halves it for a while (Adaptive); the remaining-requests headers lower it (LowWater).
[QueueResource_anthropic:standard]
MaxConcurrent=4

[QueueResource_anthropic:fast]
MaxConcurrent=2

# Requests of a batch lane only wait in the queue; one submit or poll at a time per provider calls the batch API.
[QueueResource_anthropic:batch]
MaxConcurrent=50
Adaptive=disabled

[QueueResource_anthropic:batch-api]
MaxConcurrent=1

[QueueResource_openai:flex]
MaxConcurrent=4

[QueueResource_openrouter:flex]
MaxConcurrent=4

*/ ?>
