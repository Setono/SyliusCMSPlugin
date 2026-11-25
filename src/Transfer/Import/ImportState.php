<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * Where an import started in the admin is: the Messenger worker checks the uploaded zip with a dry run, the admin user
 * confirms the plan, and the worker imports it. Not to be confused with ImportStatus, how an import, or its dry run,
 * ended. See "Transfer" in docs/spec/admin.md and "Who runs it" in docs/spec/transfer.md
 */
enum ImportState: string
{
    /** Uploaded: the worker runs its dry run */
    case Checking = 'checking';

    /** Its dry run would apply: it waits for the admin user to confirm it */
    case Checked = 'checked';

    /** Confirmed: the worker imports it */
    case Importing = 'importing';

    /** It has a result: it applied, or it refused, in its dry run or when it ran */
    case Finished = 'finished';

    /** An admin user cancelled it before confirming it, or it waited too long for that */
    case Abandoned = 'abandoned';

    /**
     * The worker kept failing to check or import it with an unexpected error, until Messenger gave up retrying, or it
     * stopped while checking or importing it, as on a fatal error, so it isn't run again
     */
    case Failed = 'failed';
}
