<?php

namespace PrestaFlow\Library\Utils;

interface OutputStates
{
    public const FAIL = 'fail';
    public const SKIP = 'skip';
    public const SKIPPED = 'skipped';
    public const RUNS = 'pending';
    public const PASS = 'pass';
    public const TODO = 'todo';
    public const DEBUG = 'debug';

    public const OUTPUT_FULL = 'full';
    public const OUTPUT_COMPACT = 'compact';
    public const OUTPUT_JSON = 'json';
}
