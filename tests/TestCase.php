<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dashboard dan halaman data menghubungi WebAPI BPS bila BPS_API_KEY terisi. Tes tidak boleh memakai
        // kunci dari .env (permintaan sungguhan); tes BPS mengisi kunci uji sendiri dan memalsukan API.
        config(['services.bps.key' => null]);
    }
}
