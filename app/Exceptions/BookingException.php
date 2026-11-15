<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis booking; pesan aman ditampilkan ke pengguna. */
class BookingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'date')
    {
        parent::__construct($message);
    }
}
