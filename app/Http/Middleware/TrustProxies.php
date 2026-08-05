<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * Trust ALL proxies (Cloud Run runs behind Google Frontend proxy).
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * Use all forwarded headers
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
