<?php

use App\Modules\Kern\KernServiceProvider;
use App\Modules\Ticket\TicketServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    KernServiceProvider::class,
    TicketServiceProvider::class,
];
