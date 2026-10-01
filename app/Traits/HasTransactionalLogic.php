<?php

namespace App\Traits;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

trait HasTransactionalLogic
{
    /**
     * Execute a callback inside a database transaction with automatic retry on deadlocks.
     *
     * @template T
     * @param Closure(): T $callback
     * @param int $attempts Number of times to retry if a deadlock occurs
     * @return T
     * @throws Throwable
     */
    protected function executeTransaction(Closure $callback, int $attempts = 3)
    {
        return DB::transaction($callback, $attempts);
    }
}
