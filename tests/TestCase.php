<?php

namespace Tests;

use App\Support\Tenancy\NetworkContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NetworkContext::clear();
    }
}
