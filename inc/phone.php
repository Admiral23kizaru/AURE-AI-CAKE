<?php
declare(strict_types=1);
function normalize_ph_phone(string $phone): ?string { $n=preg_replace('/\D+/','',$phone); if(str_starts_with($n,'63'))$n='0'.substr($n,2); if(preg_match('/^09\d{9}$/',$n)!==1)return null; return '+63'.substr($n,1); }

/** Semaphore documents Philippine API numbers without a leading plus sign. */
function semaphore_phone_number(string $phone): ?string
{
    $canonical = normalize_ph_phone($phone);
    return $canonical === null ? null : ltrim($canonical, '+');
}
