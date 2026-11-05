<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Sesi lihat sebagai peserta mencoba menulis data. */
class SpyReadOnlyViolation extends HttpException
{
    public function __construct()
    {
        parent::__construct(403, 'Mode lihat: perubahan tidak dapat disimpan.');
    }
}
